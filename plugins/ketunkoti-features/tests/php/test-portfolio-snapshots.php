<?php
/**
 * Tests for the Salkku snapshots: post type, builder and save.
 *
 * Run inside the wp-env tests container:
 *
 *     wp eval-file wp-content/plugins/ketunkoti-features/tests/php/test-portfolio-snapshots.php
 *
 * Prints one PASS/FAIL line per check and exits with status 1 if any failed.
 * The test creates its own users, posts and terms and deletes them at the end,
 * so the shared tests database stays clean. Later tasks add their checks
 * between the "Checks" marker and the teardown.
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti\Tests;

use WP_Query;
use WP_REST_Request;

use function Ketunkoti\build_portfolio_snapshot_data;
use function Ketunkoti\get_next_portfolio_snapshot_time;
use function Ketunkoti\get_portfolio_overview;
use function Ketunkoti\get_portfolio_history_html;
use function Ketunkoti\get_portfolio_snapshot_box_html;
use function Ketunkoti\get_portfolio_snapshot_column_html;
use function Ketunkoti\get_portfolio_overview_html;
use function Ketunkoti\get_portfolio_snapshot_form_html;
use function Ketunkoti\get_portfolio_snapshot_id;
use function Ketunkoti\get_portfolio_snapshot_month;
use function Ketunkoti\get_portfolio_snapshots;
use function Ketunkoti\handle_portfolio_snapshot_request;
use function Ketunkoti\run_portfolio_snapshot_cron;
use function Ketunkoti\save_portfolio_snapshot;

// Only runnable through WP-CLI, never over HTTP.
if ( ! defined( 'WP_CLI' ) ) {
    exit;
}

// wp eval-file runs this file inside a method, so shared state lives in
// $GLOBALS rather than in file-level variables.
$GLOBALS['ketunkoti_test_failures'] = 0;
$GLOBALS['ketunkoti_test_users']    = [];
$GLOBALS['ketunkoti_test_posts']    = [];
$GLOBALS['ketunkoti_test_terms']    = [];
$GLOBALS['ketunkoti_test_old_timezone'] = get_option( 'timezone_string' );

/**
 * Compares an expected and an actual value and prints the result.
 *
 * Null and booleans are compared strictly, floats rounded to two decimals and
 * everything else exactly.
 *
 * @param string $name     Check name.
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @return void
 */
function check( string $name, $expected, $actual ): void {
    if ( is_float( $expected ) && ( is_float( $actual ) || is_int( $actual ) ) ) {
        $passed = round( $expected, 2 ) === round( (float) $actual, 2 );
    } else {
        $passed = $expected === $actual;
    }

    if ( $passed ) {
        echo 'PASS ', $name, PHP_EOL;
        return;
    }

    ++$GLOBALS['ketunkoti_test_failures'];
    echo 'FAIL ', $name, ': expected ', var_export( $expected, true ), ', got ', var_export( $actual, true ), PHP_EOL;
}

/**
 * Creates a user with the given role and remembers it for teardown.
 *
 * @param string $role Role slug.
 * @return int User ID.
 */
function create_test_user( string $role ): int {
    $login = 'portfolio-test-' . $role . '-' . wp_generate_password( 6, false );
    $id    = wp_insert_user(
        [
            'user_login' => $login,
            'user_pass'  => wp_generate_password(),
            'user_email' => $login . '@example.invalid',
            'role'       => $role,
        ]
    );

    if ( is_wp_error( $id ) ) {
        echo 'Could not create test user: ', $id->get_error_message(), PHP_EOL;
        exit( 1 );
    }

    $GLOBALS['ketunkoti_test_users'][] = $id;

    return $id;
}

/**
 * Creates a portfolio post and remembers it for teardown.
 *
 * @param string $title Post title.
 * @return int Post ID.
 */
function create_test_portfolio( string $title = 'Testi' ): int {
    $id = wp_insert_post(
        [
            'post_type'   => 'portfolio',
            'post_status' => 'publish',
            'post_title'  => $title,
        ]
    );

    $GLOBALS['ketunkoti_test_posts'][] = (int) $id;

    return (int) $id;
}

/**
 * Runs a callable with `wp_die()` turned into an exception.
 *
 * @param callable $callback Code that is expected to call `wp_die()`.
 * @return bool True when `wp_die()` was called.
 */
function portfolio_test_dies( callable $callback ): bool {
    $handler = static fn () => static function () {
        throw new \RuntimeException( 'wp_die called' );
    };

    add_filter( 'wp_die_handler', $handler );
    add_filter( 'wp_die_ajax_handler', $handler );

    try {
        $callback();
        $died = false;
    } catch ( \RuntimeException $e ) {
        $died = 'wp_die called' === $e->getMessage();
    }

    remove_filter( 'wp_die_handler', $handler );
    remove_filter( 'wp_die_ajax_handler', $handler );

    return $died;
}

/**
 * Builds an invented snapshot data array for the history table tests.
 *
 * @param string     $month    Month as `YYYY-MM`.
 * @param float      $value    Combined apartment value.
 * @param float|null $leverage Combined leverage, or null.
 * @return array<string, mixed> Snapshot data.
 */
function make_history_snapshot( string $month, float $value, ?float $leverage ): array {
    return [
        'month'      => $month,
        'taken_at'   => $month . '-01 00:30:00',
        'trigger'    => 'cron',
        'combined'   => [
            'count'           => 2,
            'value'           => $value,
            'debts'           => 100000.0,
            'net_worth'       => $value - 100000.0,
            'free_collateral' => 5000.0,
            'leverage'        => $leverage,
        ],
        'groups'     => [],
        'apartments' => [],
    ];
}

/**
 * Deletes everything the test created.
 *
 * @return void
 */
function teardown_test_data(): void {
    require_once ABSPATH . 'wp-admin/includes/user.php';

    foreach ( $GLOBALS['ketunkoti_test_posts'] as $post_id ) {
        wp_delete_post( $post_id, true );
    }

    foreach ( $GLOBALS['ketunkoti_test_terms'] as $term ) {
        wp_delete_term( $term['id'], $term['taxonomy'] );
    }

    wp_clear_scheduled_hook( 'ketunkoti_portfolio_monthly_snapshot' );
    update_option( 'timezone_string', $GLOBALS['ketunkoti_test_old_timezone'] );

    foreach ( $GLOBALS['ketunkoti_test_users'] as $user_id ) {
        wp_delete_user( $user_id );
    }

    wp_set_current_user( 0 );
}

// Setup.
update_option( 'timezone_string', 'Europe/Helsinki' );

$admin  = create_test_user( 'administrator' );
$editor = create_test_user( 'editor' );

$ts_nov = strtotime( '2026-11-30 21:59:00 UTC' );
$ts_dec = strtotime( '2026-11-30 22:01:00 UTC' );

$owner_term = wp_insert_term( 'Snapshot test owner', 'portfolio-owner' );
$GLOBALS['ketunkoti_test_terms'][] = [ 'id' => $owner_term['term_id'], 'taxonomy' => 'portfolio-owner' ];

$apartment_id = create_test_portfolio( 'Snapshot apartment' );
wp_set_object_terms( $apartment_id, [ (int) $owner_term['term_id'] ], 'portfolio-owner' );
update_post_meta( $apartment_id, 'portfolio_address', 'Testikatu 1' );
update_post_meta( $apartment_id, 'portfolio_estimated_value', 200000 );
update_post_meta( $apartment_id, 'portfolio_company_loan', 20000 );
update_post_meta( $apartment_id, 'portfolio_bank_loan', 30000 );

$draft_id = wp_insert_post(
    [
        'post_type'   => 'portfolio',
        'post_status' => 'draft',
        'post_title'  => 'Snapshot draft apartment',
    ]
);
$GLOBALS['ketunkoti_test_posts'][] = (int) $draft_id;
update_post_meta( $draft_id, 'portfolio_estimated_value', 100000 );
update_post_meta( $draft_id, 'portfolio_bank_loan', 10000 );

// Checks: registration and privacy.
check( 'post type registered', true, post_type_exists( 'portfolio_snapshot' ) );
check( 'not viewable', false, is_post_type_viewable( 'portfolio_snapshot' ) );
check( 'not in public types', false, in_array( 'portfolio_snapshot', get_post_types( [ 'public' => true ] ), true ) );
check( 'not in REST types', false, array_key_exists( 'portfolio_snapshot', rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/types' ) )->get_data() ) );
check( 'no REST route', 404, rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/portfolio_snapshot' ) )->get_status() );
check( 'admin cannot create by hand', false, user_can( $admin, get_post_type_object( 'portfolio_snapshot' )->cap->create_posts ) );
check( 'shown under Salkku menu', 'edit.php?post_type=portfolio', get_post_type_object( 'portfolio_snapshot' )->show_in_menu );
check( 'meta month not in REST', false, get_registered_meta_keys( 'post', 'portfolio_snapshot' )['portfolio_snapshot_month']['show_in_rest'] );
check( 'meta data not in REST', false, get_registered_meta_keys( 'post', 'portfolio_snapshot' )['portfolio_snapshot_data']['show_in_rest'] );

// Checks: month in the site timezone.
check( 'month helsinki late', '2026-11', get_portfolio_snapshot_month( $ts_nov ) );
check( 'month helsinki next', '2026-12', get_portfolio_snapshot_month( $ts_dec ) );

// Checks: the builder.
$data = build_portfolio_snapshot_data( 'manual', $ts_nov );
check( 'data version', 1, $data['version'] );
check( 'data month', '2026-11', $data['month'] );
check( 'data trigger', 'manual', $data['trigger'] );
check( 'taken_at is ISO 8601 UTC', '2026-11-30T21:59:00+00:00', $data['taken_at'] );
check( 'combined equals overview', get_portfolio_overview()['combined'], $data['combined'] );
check( 'groups hold name and totals', [ 'name', 'totals' ], array_keys( $data['groups'][0] ) );

$rows = [];
foreach ( $data['apartments'] as $row ) {
    $rows[ $row['id'] ] = $row;
}
$counted_row = $rows[ $apartment_id ];
$draft_row   = $rows[ $draft_id ];

check( 'both apartments stored', 2, count( $data['apartments'] ) );
check( 'no address stored', false, array_key_exists( 'portfolio_address', $counted_row ) || array_key_exists( 'address', $counted_row ) );
check( 'no area, year or floor stored', false, array_key_exists( 'area', $counted_row ) || array_key_exists( 'year_built', $counted_row ) || array_key_exists( 'floor', $counted_row ) );
check( 'owner name stored', 'Snapshot test owner', $counted_row['owner'] );
check( 'counted flag', true, $counted_row['counted'] );
check( 'counted debts', 50000.0, $counted_row['debts'] );
check( 'counted net value', 150000.0, $counted_row['net_value'] );
check( 'draft stored not counted', false, $draft_row['counted'] );
check( 'draft status stored', 'draft', $draft_row['status'] );
check( 'draft has no owner label', 'Ei omistajaa', $draft_row['owner'] );
check( 'draft has figures', true, isset( $draft_row['free_collateral'] ) );
check( 'draft net value', 90000.0, $draft_row['net_value'] );
check( 'draft not in combined', 1, $data['combined']['count'] );

// Checks: saving.
$id1 = save_portfolio_snapshot( 'manual', $ts_nov );
$GLOBALS['ketunkoti_test_posts'][] = (int) $id1;
$id2 = save_portfolio_snapshot( 'cron', $ts_nov );
check( 'same month replaces', $id1, $id2 );
check( 'trigger updated', 'cron', get_post_meta( $id1, 'portfolio_snapshot_data', true )['trigger'] );
check( 'title is the month', '2026-11', get_post_field( 'post_title', $id1 ) );
check( 'status is publish', 'publish', get_post_status( $id1 ) );
check( 'post_date in site time', '2026-11-30 23:59:00', get_post_field( 'post_date', $id1 ) );
check( 'month meta stored', '2026-11', get_post_meta( $id1, 'portfolio_snapshot_month', true ) );
check( 'lookup by month', $id1, get_portfolio_snapshot_id( '2026-11' ) );
check( 'lookup of missing month', 0, get_portfolio_snapshot_id( '2020-01' ) );

wp_set_current_user( $admin );
$id3 = save_portfolio_snapshot( 'manual', $ts_dec );
$GLOBALS['ketunkoti_test_posts'][] = (int) $id3;
check( 'new month new post', true, $id3 !== $id1 );
check( 'author is current user', $admin, (int) get_post_field( 'post_author', $id3 ) );
check( 'author is 0 without user', 0, (int) get_post_field( 'post_author', $id1 ) );
check( 'editor cannot read', false, user_can( $editor, 'read_post', $id1 ) );
check( 'admin can read', true, user_can( $admin, 'read_post', $id1 ) );

// Checks: stored figures stay frozen.
$value_before = get_portfolio_snapshots( '2026-11', '2026-11' )[0]['combined']['value'];
update_post_meta( $apartment_id, 'portfolio_estimated_value', 999999 );
check( 'stored month frozen', $value_before, get_portfolio_snapshots( '2026-11', '2026-11' )[0]['combined']['value'] );
check( 'frozen value is the saved one', 200000.0, $value_before );

// Checks: listing.
$list = get_portfolio_snapshots();
check( 'list oldest first', [ '2026-11', '2026-12' ], wp_list_pluck( $list, 'month' ) );
check( 'range from', [ '2026-12' ], wp_list_pluck( get_portfolio_snapshots( '2026-12' ), 'month' ) );
check( 'range to', [ '2026-11' ], wp_list_pluck( get_portfolio_snapshots( null, '2026-11' ), 'month' ) );
check( 'range outside', [], get_portfolio_snapshots( '2027-01' ) );

wp_trash_post( $id3 );
check( 'trashed ignored by lookup', 0, get_portfolio_snapshot_id( '2026-12' ) );
check( 'trashed ignored by list', 1, count( get_portfolio_snapshots() ) );

update_post_meta( $id1, 'portfolio_snapshot_data', 'broken' );
check( 'malformed skipped', 0, count( get_portfolio_snapshots() ) );

$bad = save_portfolio_snapshot( 'nope', $ts_nov );
check( 'bad trigger', 'ketunkoti_portfolio_invalid_trigger', is_wp_error( $bad ) ? $bad->get_error_code() : null );

// Checks: the save leaves no filter behind.
$filter_count_before = count( $GLOBALS['wp_filter']['wp_insert_post_data']->callbacks[10] ?? [] );
save_portfolio_snapshot( 'manual', strtotime( '2027-02-10 10:00:00 UTC' ) );
$GLOBALS['ketunkoti_test_posts'][] = get_portfolio_snapshot_id( '2027-02' );
check( 'save removes its insert filter', $filter_count_before, count( $GLOBALS['wp_filter']['wp_insert_post_data']->callbacks[10] ?? [] ) );

// A save_post hook that throws must not leave the filter attached.
$thrower = static function ( int $post_id, \WP_Post $post ): void {
    if ( 'portfolio_snapshot' === $post->post_type ) {
        $GLOBALS['ketunkoti_test_posts'][] = $post_id;
        throw new \RuntimeException( 'boom' );
    }
};
add_action( 'save_post', $thrower, 10, 2 );
try {
    save_portfolio_snapshot( 'manual', strtotime( '2027-02-11 10:00:00 UTC' ) );
} catch ( \RuntimeException $e ) {
    echo 'PASS exception propagates', PHP_EOL;
}
remove_action( 'save_post', $thrower, 10 );
check( 'filter removed after exception', $filter_count_before, count( $GLOBALS['wp_filter']['wp_insert_post_data']->callbacks[10] ?? [] ) );
$GLOBALS['ketunkoti_test_posts'][] = get_portfolio_snapshot_id( '2027-02' );

// Checks: one entry per month, newest post_date wins (dated in the past so they stay published).
foreach ( [ '2026-05-01 08:00:00' => 'older', '2026-05-01 09:00:00' => 'newer' ] as $date => $label ) {
    $dup_id = wp_insert_post(
        [
            'post_type'   => 'portfolio_snapshot',
            'post_status' => 'publish',
            'post_title'  => '2027-05',
            'post_date'   => $date,
        ]
    );
    $GLOBALS['ketunkoti_test_posts'][] = (int) $dup_id;
    update_post_meta( $dup_id, 'portfolio_snapshot_data', [ 'month' => '2027-05', 'combined' => [], 'trigger' => $label ] );
}
$dups = get_portfolio_snapshots( '2027-05', '2027-05' );
check( 'one entry per month', 1, count( $dups ) );
check( 'newest post_date wins', 'newer', $dups[0]['trigger'] ?? null );

// Equal post_date: the higher post ID wins.
foreach ( [ 'first', 'second' ] as $label ) {
    $tie_id = wp_insert_post( [ 'post_type' => 'portfolio_snapshot', 'post_status' => 'publish', 'post_title' => '2027-07', 'post_date' => '2026-05-02 08:00:00' ] );
    $GLOBALS['ketunkoti_test_posts'][] = (int) $tie_id;
    update_post_meta( $tie_id, 'portfolio_snapshot_data', [ 'month' => '2027-07', 'combined' => [], 'trigger' => $label ] );
}
check( 'equal dates: higher ID wins', 'second', get_portfolio_snapshots( '2027-07', '2027-07' )[0]['trigger'] ?? null );

// Checks: the monthly schedule.
wp_clear_scheduled_hook( 'ketunkoti_portfolio_monthly_snapshot' );
$t = get_next_portfolio_snapshot_time( strtotime( '2026-11-15 10:00:00 UTC' ) );
check( 'next is 1 dec 00:30 helsinki', '2026-12-01 00:30', wp_date( 'Y-m-d H:i', $t ) );
check( 'next from late 30 nov local', '2027-01-01 00:30', wp_date( 'Y-m-d H:i', get_next_portfolio_snapshot_time( strtotime( '2026-11-30 22:01:00 UTC' ) ) ) );
check( 'dst spring: 1 apr 00:30 helsinki', '2027-03-31 21:30', gmdate( 'Y-m-d H:i', get_next_portfolio_snapshot_time( strtotime( '2027-03-15 10:00:00 UTC' ) ) ) );
check( 'dst autumn: 1 nov 00:30 helsinki', '2026-10-31 22:30', gmdate( 'Y-m-d H:i', get_next_portfolio_snapshot_time( strtotime( '2026-10-15 10:00:00 UTC' ) ) ) );

$event = 'ketunkoti_portfolio_monthly_snapshot';
check( 'init hook schedules', true, false !== has_action( 'init', 'Ketunkoti\schedule_portfolio_snapshot_event' ) );
\Ketunkoti\schedule_portfolio_snapshot_event();
check( 'event scheduled when missing', true, false !== wp_next_scheduled( $event ) );
check( 'cron callback hooked', true, false !== has_action( $event, 'Ketunkoti\run_portfolio_snapshot_cron' ) );

$now           = strtotime( '2027-03-10 10:00:00 UTC' );
$next_month_ts = strtotime( '2027-04-10 10:00:00 UTC' );

// Empty portfolio: trash every apartment.
wp_trash_post( $apartment_id );
wp_trash_post( $draft_id );
check( 'empty skips', 'empty', run_portfolio_snapshot_cron( $now ) );
check( 'empty retries tomorrow', $now + DAY_IN_SECONDS, wp_next_scheduled( $event ) );
check( 'empty saved nothing', 0, get_portfolio_snapshot_id( '2027-03' ) );
wp_update_post( [ 'ID' => $apartment_id, 'post_status' => 'publish' ] );
wp_update_post( [ 'ID' => $draft_id, 'post_status' => 'draft' ] );

check( 'first run saves', 'saved', run_portfolio_snapshot_cron( $now ) );
check( 'saved schedules next 1st', '2027-04-01 00:30', wp_date( 'Y-m-d H:i', wp_next_scheduled( $event ) ) );
$GLOBALS['ketunkoti_test_posts'][] = get_portfolio_snapshot_id( '2027-03' );
check( 'cron trigger stored', 'cron', get_portfolio_snapshots( '2027-03', '2027-03' )[0]['trigger'] );
check( 'second run exists', 'exists', run_portfolio_snapshot_cron( $now ) );
check( 'exists schedules next 1st', '2027-04-01 00:30', wp_date( 'Y-m-d H:i', wp_next_scheduled( $event ) ) );
check( 'one event only', 1, count( array_filter( _get_cron_array(), fn( $e ) => isset( $e[ $event ] ) ) ) );

// Stray extra event is cleared when the cron callback reschedules.
wp_schedule_single_event( $now + 5000, $event );
run_portfolio_snapshot_cron( $now );
check( 'stray events cleared', 1, count( array_filter( _get_cron_array(), fn( $e ) => isset( $e[ $event ] ) ) ) );

$march_id = get_portfolio_snapshot_id( '2027-03' );
wp_trash_post( $march_id );
check( 'after trash saves again', 'saved', run_portfolio_snapshot_cron( $now ) );
$GLOBALS['ketunkoti_test_posts'][] = get_portfolio_snapshot_id( '2027-03' );

// April has no snapshot yet, so the forced failure takes the insert path.
add_filter( 'wp_insert_post_empty_content', '__return_true' );
$log_file = tempnam( sys_get_temp_dir(), 'snaplog' );
$old_log  = ini_set( 'error_log', $log_file );
check( 'failure retries in an hour', 'failed', run_portfolio_snapshot_cron( $next_month_ts ) );
ini_set( 'error_log', $old_log );
remove_filter( 'wp_insert_post_empty_content', '__return_true' );
check( 'retry scheduled', $next_month_ts + HOUR_IN_SECONDS, wp_next_scheduled( $event ) );
check( 'failure logged', true, '' !== trim( (string) file_get_contents( $log_file ) ) );
unlink( $log_file );
check( 'failure saved nothing', 0, get_portfolio_snapshot_id( '2027-04' ) );

// An exception also counts as a failure.
add_action( 'save_post', $thrower, 10, 2 );
$old_log = ini_set( 'error_log', '/dev/null' );
check( 'exception is failed', 'failed', run_portfolio_snapshot_cron( $next_month_ts ) );
ini_set( 'error_log', $old_log );
remove_action( 'save_post', $thrower, 10 );
check( 'exception retry in an hour', $next_month_ts + HOUR_IN_SECONDS, wp_next_scheduled( $event ) );
check( 'failed save still holds its month', true, get_portfolio_snapshot_id( '2027-04' ) > 0 );
check( 'retry after exception finds the month', 'exists', run_portfolio_snapshot_cron( $next_month_ts ) );
check( 'one published April post', 1, count( get_posts( [ 'post_type' => 'portfolio_snapshot', 'post_status' => 'publish', 'meta_key' => 'portfolio_snapshot_month', 'meta_value' => '2027-04', 'fields' => 'ids', 'posts_per_page' => -1 ] ) ) );

// A saved snapshot that cannot be found by its month counts as failed.
$strip_month = static function ( $check, $object_id, $meta_key ) {
    return 'portfolio_snapshot_month' === $meta_key ? false : $check;
};
$orphan_ts = strtotime( '2027-06-10 10:00:00 UTC' );
add_filter( 'update_post_metadata', $strip_month, 10, 3 );
add_filter( 'add_post_metadata', $strip_month, 10, 3 );
$old_log = ini_set( 'error_log', '/dev/null' );
check( 'unfindable save is failed', 'failed', run_portfolio_snapshot_cron( $orphan_ts ) );
ini_set( 'error_log', $old_log );
remove_filter( 'update_post_metadata', $strip_month, 10 );
remove_filter( 'add_post_metadata', $strip_month, 10 );
check( 'unfindable save retries in an hour', $orphan_ts + HOUR_IN_SECONDS, wp_next_scheduled( $event ) );
foreach ( get_posts( [ 'post_type' => 'portfolio_snapshot', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1 ] ) as $leftover_id ) {
    $GLOBALS['ketunkoti_test_posts'][] = (int) $leftover_id;
}

// Checks: Kehitys table.
$snap_oct = make_history_snapshot( '2026-10', 300000.0, 40.0 );
$snap_nov = make_history_snapshot( '2026-11', 310000.0, 41.2 );
$snap_dec = make_history_snapshot( '2026-12', 310000.0, 41.2 );
$snap_low = make_history_snapshot( '2027-01', 295000.0, 39.0 );
$html     = get_portfolio_history_html( [] );
check( 'empty message', true, str_contains( $html, 'Ei vielä tilannekuvia.' ) );
check( 'heading', true, str_contains( $html, '<h2>Kehitys</h2>' ) );
$html = get_portfolio_history_html( [ $snap_oct, $snap_nov, $snap_dec, $snap_low ] );
check( 'newest first', true, strpos( $html, '2027-01' ) < strpos( $html, '2026-11' ) && strpos( $html, '2026-11' ) < strpos( $html, '2026-10' ) );
check( 'positive change', true, str_contains( $html, "+10\u{00A0}000\u{00A0}€" ) );
check( 'negative change uses minus sign', true, str_contains( $html, "\u{2212}15\u{00A0}000\u{00A0}€" ) );
check( 'unchanged shows zero', true, str_contains( $html, "±0\u{00A0}€" ) );
check( 'leverage change in points', true, str_contains( $html, "+1,2\u{00A0}%-yks." ) );
check( 'leverage decrease in points', true, str_contains( $html, "\u{2212}2,2\u{00A0}%-yks." ) );
check( 'change span class', true, str_contains( $html, '<span class="ketunkoti-portfolio-change">' ) );
check( 'caption', true, str_contains( $html, '<caption' ) );
check( 'keyboard region', true, str_contains( $html, 'tabindex="0" role="region" aria-labelledby="ketunkoti-portfolio-caption-history"' ) );
check( 'month row header', true, str_contains( $html, '<th scope="row">2026-10</th>' ) );
check( 'oldest row has no change', 1, preg_match_all( '/2026-10<\/th>(.*?)<\/tr>/s', $html, $oldest ) && ! str_contains( $oldest[1][0], 'ketunkoti-portfolio-change' ) ? 1 : 0 );
$null_html = get_portfolio_history_html( [ make_history_snapshot( '2026-08', 0.0, null ), make_history_snapshot( '2026-09', 0.0, null ) ] );
check( 'null leverage dash', true, str_contains( $null_html, '<td>–</td>' ) );
$mixed_html = get_portfolio_history_html( [ make_history_snapshot( '2026-08', 100000.0, null ), make_history_snapshot( '2026-09', 100000.0, 30.0 ) ] );
check( 'no leverage change when previous is null', false, str_contains( $mixed_html, '%-yks.' ) );

// Checks: save-now form.
$form_html = get_portfolio_snapshot_form_html();
check( 'form posts to admin-post', true, str_contains( $form_html, 'method="post"' ) && str_contains( $form_html, 'admin-post.php' ) );
check( 'form action field', true, str_contains( $form_html, 'name="action" value="ketunkoti_portfolio_snapshot"' ) );
check( 'form nonce', true, str_contains( $form_html, 'name="_wpnonce"' ) );
check( 'form button label', true, str_contains( $form_html, 'Tallenna tilannekuva nyt' ) );
check( 'form explanation', true, str_contains( $form_html, 'Painike korvaa kuluvan kuukauden tilannekuvan.' ) );

// Checks: save-now handler.
$admin_id = create_test_user( 'administrator' );
wp_set_current_user( $admin_id );
$_REQUEST['_wpnonce'] = 'bad';
check( 'bad nonce refused', true, portfolio_test_dies( static fn() => handle_portfolio_snapshot_request() ) );
$_REQUEST['_wpnonce'] = wp_create_nonce( 'ketunkoti_portfolio_snapshot' );
wp_set_current_user( create_test_user( 'editor' ) );
$_REQUEST['_wpnonce'] = wp_create_nonce( 'ketunkoti_portfolio_snapshot' );
check( 'non-admin refused', true, portfolio_test_dies( static fn() => handle_portfolio_snapshot_request() ) );
wp_set_current_user( $admin_id );
$_REQUEST['_wpnonce'] = wp_create_nonce( 'ketunkoti_portfolio_snapshot' );
$redirect_to = '';
$catch_redirect = static function ( $location ) {
    $GLOBALS['ketunkoti_test_redirect'] = $location;
    throw new \RuntimeException( 'redirect caught' );
};
add_filter( 'wp_redirect', $catch_redirect, 0 );
try {
    handle_portfolio_snapshot_request();
} catch ( \RuntimeException $e ) {
    $redirect_to = (string) ( $GLOBALS['ketunkoti_test_redirect'] ?? '' );
}
remove_filter( 'wp_redirect', $catch_redirect, 0 );
check( 'handler redirects to overview', true, str_contains( $redirect_to, 'page=portfolio-overview' ) );
$GLOBALS['ketunkoti_test_posts'] = array_merge( $GLOBALS['ketunkoti_test_posts'], get_posts( [ 'post_type' => 'portfolio_snapshot', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1 ] ) );
$notice_html = get_portfolio_overview_html( get_portfolio_overview() );
check( 'success notice shown', true, str_contains( $notice_html, 'Tilannekuva tallennettu kuukaudelle ' . get_portfolio_snapshot_month( time() ) . '.' ) );
check( 'overview has form and history', true, str_contains( $notice_html, 'Tallenna tilannekuva nyt' ) && str_contains( $notice_html, '<h2>Kehitys</h2>' ) );
$again_html = get_portfolio_overview_html( get_portfolio_overview() );
check( 'notice shown only once', false, str_contains( $again_html, 'Tilannekuva tallennettu' ) );
wp_set_current_user( 0 );

// Checks: snapshot meta box and list columns.
$box_ts = strtotime( '2027-09-15 09:00:00 UTC' );
update_post_meta( $apartment_id, 'portfolio_estimated_value', 250000 );
$box_id = save_portfolio_snapshot( 'cron', $box_ts );
$GLOBALS['ketunkoti_test_posts'][] = (int) $box_id;
update_post_meta( $apartment_id, 'portfolio_estimated_value', 777777 );
$box_data = get_post_meta( $box_id, 'portfolio_snapshot_data', true );
$box_html = get_portfolio_snapshot_box_html( $box_id );
$stored_apartment = null;
$stored_draft     = null;
foreach ( $box_data['apartments'] as $row ) {
    if ( $row['counted'] ) {
        $stored_apartment = $row;
    } else {
        $stored_draft = $row;
    }
}
$stored_value = number_format( $stored_apartment['estimated_value'], 0, ',', "\u{00A0}" ) . "\u{00A0}€";
$live_value   = number_format( (float) get_post_meta( $apartment_id, 'portfolio_estimated_value', true ), 0, ',', "\u{00A0}" ) . "\u{00A0}€";
check( 'box live value differs from stored', true, $stored_value !== $live_value );
check( 'box shows stored value', true, str_contains( $box_html, $stored_value ) );
check( 'box does not show live value', false, str_contains( $box_html, $live_value ) );
check( 'box taken line', true, str_contains( $box_html, 'Tallennettu: 15.09.2027 12.00' ) );
check( 'box trigger label', true, str_contains( $box_html, 'Automaattinen' ) );
check( 'box owner name', true, str_contains( $box_html, 'Snapshot test owner' ) );
check( 'box apartment title', true, str_contains( $box_html, $stored_apartment['title'] ) );
$first_table = substr( $box_html, 0, strpos( $box_html, '</table>' ) );
check( 'box Yhteensä row is last', true, 1 === substr_count( $first_table, 'Yhteensä' ) && strpos( $first_table, 'Yhteensä' ) > strpos( $first_table, 'Snapshot test owner' ) );
check( 'box uncounted line', true, str_contains( $box_html, 'Ei mukana summissa: ' . $stored_draft['title'] . ' (luonnos)' ) );
check( 'box caption and region', true, str_contains( $box_html, '<caption' ) && str_contains( $box_html, 'role="region"' ) );
check( 'box has no form fields', 0, preg_match( '/<(input|select|textarea)/i', $box_html ) );
check( 'column value', true, str_contains( get_portfolio_snapshot_column_html( 'portfolio_snapshot_value', $box_id ), number_format( $box_data['combined']['value'], 0, ',', "\u{00A0}" ) ) );
check( 'column unknown key is empty', '', get_portfolio_snapshot_column_html( 'other', $box_id ) );
$bad_id = wp_insert_post( [ 'post_type' => 'portfolio_snapshot', 'post_status' => 'publish', 'post_title' => '2020-01' ] );
$GLOBALS['ketunkoti_test_posts'][] = (int) $bad_id;
update_post_meta( $bad_id, 'portfolio_snapshot_data', 'garbage' );
check( 'malformed box notice', true, str_contains( get_portfolio_snapshot_box_html( $bad_id ), 'Tilannekuvan tiedot puuttuvat tai ovat virheelliset.' ) );
check( 'malformed box has no table', false, str_contains( get_portfolio_snapshot_box_html( $bad_id ), '<table' ) );
check( 'malformed column dash', '–', get_portfolio_snapshot_column_html( 'portfolio_snapshot_value', $bad_id ) );
$partial_id = wp_insert_post( [ 'post_type' => 'portfolio_snapshot', 'post_status' => 'publish', 'post_title' => '2020-02' ] );
$GLOBALS['ketunkoti_test_posts'][] = (int) $partial_id;
update_post_meta( $partial_id, 'portfolio_snapshot_data', [ 'month' => '2020-02', 'taken_at' => '2020-02-01T00:00:00+00:00', 'trigger' => 'cron', 'groups' => [ [ 'name' => 'X', 'totals' => [ 'count' => 1 ] ] ], 'combined' => [ 'count' => 1 ], 'apartments' => [ [ 'title' => 'A', 'owner' => 'X', 'counted' => true ] ] ] );
$partial_html = get_portfolio_snapshot_box_html( $partial_id );
check( 'missing numbers render dash', true, str_contains( $partial_html, '<td>–</td>' ) && ! str_contains( $partial_html, '0&nbsp;€' ) && ! str_contains( $partial_html, "0\u{00A0}€" ) );

// Teardown.
teardown_test_data();

if ( $GLOBALS['ketunkoti_test_failures'] > 0 ) {
    echo $GLOBALS['ketunkoti_test_failures'], ' check(s) failed.', PHP_EOL;
    exit( 1 );
}

echo 'All checks passed.', PHP_EOL;
