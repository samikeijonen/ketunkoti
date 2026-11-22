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
use function Ketunkoti\get_portfolio_overview;
use function Ketunkoti\get_portfolio_snapshot_id;
use function Ketunkoti\get_portfolio_snapshot_month;
use function Ketunkoti\get_portfolio_snapshots;
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

// Teardown.
teardown_test_data();

if ( $GLOBALS['ketunkoti_test_failures'] > 0 ) {
    echo $GLOBALS['ketunkoti_test_failures'], ' check(s) failed.', PHP_EOL;
    exit( 1 );
}

echo 'All checks passed.', PHP_EOL;
