<?php
/**
 * Tests for the Salkku post type, taxonomy, fields and privacy.
 *
 * Run inside the wp-env tests container:
 *
 *     wp eval-file wp-content/plugins/ketunkoti-features/tests/php/test-portfolio.php
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

use function Ketunkoti\calculate_portfolio_totals;
use function Ketunkoti\get_portfolio_field_values;
use function Ketunkoti\get_portfolio_fields;
use function Ketunkoti\get_portfolio_overview;
use function Ketunkoti\get_portfolio_overview_html;
use function Ketunkoti\is_portfolio_loan_check_stale;
use function Ketunkoti\render_portfolio_meta_box;
use function Ketunkoti\save_portfolio_meta_box;
use function Ketunkoti\set_portfolio_owner;
use function Ketunkoti\validate_portfolio_fields;

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

    foreach ( $GLOBALS['ketunkoti_test_users'] as $user_id ) {
        wp_delete_user( $user_id );
    }

    wp_set_current_user( 0 );
}

// Setup.
$admin  = create_test_user( 'administrator' );
$editor = create_test_user( 'editor' );

// Fixtures for the home ID checks: a page and a real home listing.
$page_id = wp_insert_post(
    [
        'post_type'   => 'page',
        'post_status' => 'draft',
        'post_title'  => 'Portfolio test page',
    ]
);
$home_id = wp_insert_post(
    [
        'post_type'   => 'home',
        'post_status' => 'draft',
        'post_title'  => 'Portfolio test home',
    ]
);
$GLOBALS['ketunkoti_test_posts'][] = (int) $page_id;
$GLOBALS['ketunkoti_test_posts'][] = (int) $home_id;

// Checks: registration and privacy.
check( 'post type registered', true, post_type_exists( 'portfolio' ) );
check( 'not viewable', false, is_post_type_viewable( 'portfolio' ) );
check( 'not in public types', false, in_array( 'portfolio', get_post_types( [ 'public' => true ] ), true ) );
check( 'not in REST types', false, array_key_exists( 'portfolio', rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/types' ) )->get_data() ) );
check( 'no REST route', 404, rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/portfolio' ) )->get_status() );
check( 'not in sitemap', false, array_key_exists( 'portfolio', ( new \WP_Sitemaps_Posts() )->get_object_subtypes() ) );
check( 'taxonomy closed', false, is_taxonomy_viewable( 'portfolio-owner' ) );
check( 'taxonomy not in REST', false, get_taxonomy( 'portfolio-owner' )->show_in_rest );
check( 'fields order first', 'portfolio_address', array_key_first( get_portfolio_fields() ) );
check( 'field count', 14, count( get_portfolio_fields() ) );
check( 'year built is integer', 'integer', get_portfolio_fields()['portfolio_year_built']['type'] );

// Checks: capabilities.
$id = create_test_portfolio();
check( 'editor cannot edit', false, user_can( $editor, 'edit_post', $id ) );
check( 'admin can edit', true, user_can( $admin, 'edit_post', $id ) );
check( 'editor cannot create', false, user_can( $editor, get_post_type_object( 'portfolio' )->cap->create_posts ) );

wp_set_current_user( 0 );
check( "excluded from 'any' queries", 0, ( new WP_Query( [ 'p' => $id, 'post_type' => 'any' ] ) )->found_posts );

// Checks: field values.
update_post_meta( $id, 'portfolio_area', '44.5' );
check( 'typed value', 44.5, get_portfolio_field_values( $id )['portfolio_area'] );
check( 'unset is null', null, get_portfolio_field_values( $id )['portfolio_bank_loan'] );

// Checks: registration details.
check( 'owner taxonomy not in quick edit', false, get_taxonomy( 'portfolio-owner' )->show_in_quick_edit );
$registered_keys = array_filter(
    get_registered_meta_keys( 'post', 'portfolio' ),
    static fn ( $key ) => str_starts_with( $key, 'portfolio_' ),
    ARRAY_FILTER_USE_KEY
);
check( 'all portfolio meta keys registered', 14, count( $registered_keys ) );
check( 'no portfolio meta key in REST', [], array_keys( array_filter( $registered_keys, static fn ( $args ) => ! empty( $args['show_in_rest'] ) ) ) );

// Checks: validation.
check( 'finnish number', 1285.5, validate_portfolio_fields( [ 'portfolio_charges' => '1 285,50' ] )['portfolio_charges'] );
check( 'nbsp thousands', 189000.0, validate_portfolio_fields( [ 'portfolio_estimated_value' => "189\u{00A0}000" ] )['portfolio_estimated_value'] );
check( 'blank deletes', [ 'portfolio_floor' => null ], validate_portfolio_fields( [ 'portfolio_floor' => '  ' ] ) );
check( 'negative refused', 'ketunkoti_portfolio_invalid_value', validate_portfolio_fields( [ 'portfolio_bank_loan' => '-5' ] )->get_error_code() );
check( 'text number refused', 'ketunkoti_portfolio_invalid_value', validate_portfolio_fields( [ 'portfolio_bank_loan' => 'paljon' ] )->get_error_code() );
check( 'rate over 100 refused', 'ketunkoti_portfolio_invalid_value', validate_portfolio_fields( [ 'portfolio_collateral_rate' => 120 ] )->get_error_code() );
check( 'rate zero refused', 'ketunkoti_portfolio_invalid_value', validate_portfolio_fields( [ 'portfolio_collateral_rate' => 0 ] )->get_error_code() );
check( 'rate 70 ok', 70.0, validate_portfolio_fields( [ 'portfolio_collateral_rate' => '70' ] )['portfolio_collateral_rate'] );
check( 'bad date refused', 'ketunkoti_portfolio_invalid_value', validate_portfolio_fields( [ 'portfolio_company_loan_checked' => '2026-02-31' ] )->get_error_code() );
check( 'good date ok', '2026-02-28', validate_portfolio_fields( [ 'portfolio_company_loan_checked' => '2026-02-28' ] )['portfolio_company_loan_checked'] );
check( 'year ok', 1985, validate_portfolio_fields( [ 'portfolio_year_built' => '1985' ] )['portfolio_year_built'] );
check( 'year zero refused', 'ketunkoti_portfolio_invalid_value', validate_portfolio_fields( [ 'portfolio_year_built' => '0' ] )->get_error_code() );
check( 'decimal year refused', 'ketunkoti_portfolio_invalid_value', validate_portfolio_fields( [ 'portfolio_year_built' => '1985,5' ] )->get_error_code() );
check( 'unknown refused', 'ketunkoti_portfolio_unknown_field', validate_portfolio_fields( [ 'home_area' => 1 ] )->get_error_code() );
check( 'invalid value names the field', 'portfolio_bank_loan', validate_portfolio_fields( [ 'portfolio_bank_loan' => 'x' ] )->get_error_data()['field'] );
check( 'home id of a page refused', 'ketunkoti_portfolio_invalid_value', validate_portfolio_fields( [ 'portfolio_home_id' => $page_id ] )->get_error_code() );
check( 'home id of deleted post refused', 'ketunkoti_portfolio_invalid_value', validate_portfolio_fields( [ 'portfolio_home_id' => 999999 ] )->get_error_code() );
check( 'home id of a home ok', (int) $home_id, validate_portfolio_fields( [ 'portfolio_home_id' => $home_id ] )['portfolio_home_id'] );
check( 'home id zero means none', [ 'portfolio_home_id' => null ], validate_portfolio_fields( [ 'portfolio_home_id' => '0' ] ) );

check( 'array on string field refused', 'ketunkoti_portfolio_invalid_value', validate_portfolio_fields( [ 'portfolio_address' => [ 'x' ] ] )->get_error_code() );
check( 'array on date field refused', 'ketunkoti_portfolio_invalid_value', validate_portfolio_fields( [ 'portfolio_company_loan_checked' => [ '2026-01-01' ] ] )->get_error_code() );
check( 'true on number field refused', 'ketunkoti_portfolio_invalid_value', validate_portfolio_fields( [ 'portfolio_area' => true ] )->get_error_code() );
check( 'true on string field refused', 'ketunkoti_portfolio_invalid_value', validate_portfolio_fields( [ 'portfolio_floor' => true ] )->get_error_code() );
check( 'object refused', 'ketunkoti_portfolio_invalid_value', validate_portfolio_fields( [ 'portfolio_area' => new \stdClass() ] )->get_error_code() );
check( 'exponent refused', 'ketunkoti_portfolio_invalid_value', validate_portfolio_fields( [ 'portfolio_area' => '1e3' ] )->get_error_code() );
check( 'huge exponent refused', 'ketunkoti_portfolio_invalid_value', validate_portfolio_fields( [ 'portfolio_area' => '1e999' ] )->get_error_code() );
check( 'infinite float refused', 'ketunkoti_portfolio_invalid_value', validate_portfolio_fields( [ 'portfolio_area' => INF ] )->get_error_code() );
$trashed_home = wp_insert_post( [ 'post_type' => 'home', 'post_status' => 'trash', 'post_title' => 'Portfolio test trashed home' ] );
$GLOBALS['ketunkoti_test_posts'][] = (int) $trashed_home;
check( 'trashed home refused', 'ketunkoti_portfolio_invalid_value', validate_portfolio_fields( [ 'portfolio_home_id' => $trashed_home ] )->get_error_code() );

// Checks: owner.
$term = set_portfolio_owner( $id, 'Testiomistaja' );
check( 'owner created', true, is_int( $term ) );
set_portfolio_owner( $id, 'Toinen omistaja' );
check( 'only one owner', 1, count( wp_get_object_terms( $id, 'portfolio-owner' ) ) );
check( 'owner matched by slug', $term, set_portfolio_owner( $id, 'testiomistaja' ) );
check( 'empty owner refused', 'ketunkoti_portfolio_invalid_owner', set_portfolio_owner( $id, ' ' )->get_error_code() );
foreach ( [ 'Testiomistaja', 'Toinen omistaja' ] as $owner_name ) {
    $owner_term = get_term_by( 'name', $owner_name, 'portfolio-owner' );
    if ( $owner_term ) {
        $GLOBALS['ketunkoti_test_terms'][] = [
            'id'       => $owner_term->term_id,
            'taxonomy' => 'portfolio-owner',
        ];
    }
}

// Checks: meta box save, as admin with a valid nonce.
wp_set_current_user( $admin );
$_POST = [
    'ketunkoti_portfolio_nonce' => wp_create_nonce( 'ketunkoti_portfolio_save' ),
    'portfolio_area'            => '46,5',
    'portfolio_owner'           => 'Testiomistaja',
];
save_portfolio_meta_box( $id );
check( 'meta box saved area', 46.5, get_portfolio_field_values( $id )['portfolio_area'] );
check( 'meta box saved owner', 'Testiomistaja', wp_get_object_terms( $id, 'portfolio-owner' )[0]->name );

$_POST = [
    'ketunkoti_portfolio_nonce' => wp_create_nonce( 'ketunkoti_portfolio_save' ),
    'portfolio_owner'           => 'Testiomistaja',
    'portfolio_owner_new'       => 'Toinen omistaja',
];
save_portfolio_meta_box( $id );
check( 'typed new owner wins over radio', 'Toinen omistaja', wp_get_object_terms( $id, 'portfolio-owner' )[0]->name );

$_POST = [
    'ketunkoti_portfolio_nonce' => wp_create_nonce( 'ketunkoti_portfolio_save' ),
    'portfolio_area'            => '50',
    'portfolio_bank_loan'       => 'paljon',
];
save_portfolio_meta_box( $id );
check( 'invalid save writes nothing', 46.5, get_portfolio_field_values( $id )['portfolio_area'] );
check( 'invalid save leaves notice', true, (bool) get_transient( 'ketunkoti_portfolio_error_' . $admin ) );
$kept = get_transient( 'ketunkoti_portfolio_input_' . $admin );
check( 'refused save keeps raw input', [ 'portfolio_area' => '50', 'portfolio_bank_loan' => 'paljon' ], $kept );
ob_start();
render_portfolio_meta_box( get_post( $id ) );
$prefilled = ob_get_clean();
check( 'render prefills posted area', true, str_contains( $prefilled, 'id="portfolio_area" value="50"' ) );
check( 'render prefills invalid text as typed', true, str_contains( $prefilled, 'id="portfolio_bank_loan" value="paljon"' ) );
check( 'input transient deleted after render', false, get_transient( 'ketunkoti_portfolio_input_' . $admin ) );
ob_start();
render_portfolio_meta_box( get_post( $id ) );
$second = ob_get_clean();
check( 'prefill only once', true, str_contains( $second, 'id="portfolio_area" value="46,5"' ) );
delete_transient( 'ketunkoti_portfolio_error_' . $admin );

$_POST = [ 'portfolio_area' => '1' ];
save_portfolio_meta_box( $id );
check( 'no nonce no save', 46.5, get_portfolio_field_values( $id )['portfolio_area'] );

wp_set_current_user( $editor );
$_POST = [
    'ketunkoti_portfolio_nonce' => wp_create_nonce( 'ketunkoti_portfolio_save' ),
    'portfolio_area'            => '2',
];
save_portfolio_meta_box( $id );
check( 'editor cannot save', 46.5, get_portfolio_field_values( $id )['portfolio_area'] );

wp_set_current_user( $admin );
$revision_id = wp_save_post_revision( $id );
check( 'revision exists', true, (bool) $revision_id );
$_POST = [
    'ketunkoti_portfolio_nonce' => wp_create_nonce( 'ketunkoti_portfolio_save' ),
    'portfolio_area'            => '3',
];
save_portfolio_meta_box( (int) $revision_id );
check( 'revision not written', '', (string) get_post_meta( (int) $revision_id, 'portfolio_area', true ) );

// An invalid save leaves both the fields and the owner untouched.
$_POST = [
    'ketunkoti_portfolio_nonce' => wp_create_nonce( 'ketunkoti_portfolio_save' ),
    'portfolio_bank_loan'       => 'paljon',
    'portfolio_owner_new'       => 'Kolmas omistaja',
];
save_portfolio_meta_box( $id );
check( 'invalid save keeps owner', 'Toinen omistaja', wp_get_object_terms( $id, 'portfolio-owner' )[0]->name );
check( 'invalid save creates no owner term', false, (bool) get_term_by( 'name', 'Kolmas omistaja', 'portfolio-owner' ) );
delete_transient( 'ketunkoti_portfolio_error_' . $admin );
delete_transient( 'ketunkoti_portfolio_input_' . $admin );

// A tampered non-scalar value refuses the whole save.
$_POST = [
    'ketunkoti_portfolio_nonce' => wp_create_nonce( 'ketunkoti_portfolio_save' ),
    'portfolio_area'            => '77',
    'portfolio_address'         => [ 'x' ],
];
save_portfolio_meta_box( $id );
check( 'tampered array writes nothing', 46.5, get_portfolio_field_values( $id )['portfolio_area'] );
check( 'tampered array leaves notice', true, (bool) get_transient( 'ketunkoti_portfolio_error_' . $admin ) );
delete_transient( 'ketunkoti_portfolio_error_' . $admin );
delete_transient( 'ketunkoti_portfolio_input_' . $admin );
$_POST = [];

// Checks: meta box markup.
update_post_meta( $id, 'portfolio_estimated_value', '189000' );
update_post_meta( $id, 'portfolio_home_id', (string) $home_id );
ob_start();
render_portfolio_meta_box( get_post( $id ) );
$html = ob_get_clean();
$positions = [];
foreach ( [ 'Perustiedot', 'Velat', 'Vuokra ja vastikkeet', 'Arvo ja vakuudet', 'Myynti-ilmoitus' ] as $heading ) {
    check( 'heading once: ' . $heading, 1, preg_match_all( '#<h3[^>]*>' . preg_quote( $heading, '#' ) . '</h3>#', $html ) );
    preg_match( '#<h3[^>]*>' . preg_quote( $heading, '#' ) . '</h3>#', $html, $m, PREG_OFFSET_CAPTURE );
    $positions[] = $m[0][1] ?? -1;
}
$sorted = $positions;
sort( $sorted );
check( 'headings in order', $sorted, $positions );
check( 'nonce field', true, str_contains( $html, 'name="ketunkoti_portfolio_nonce"' ) );
check( 'label tied to input', true, 1 === preg_match( '#<label for="portfolio_area">#', $html ) && str_contains( $html, 'id="portfolio_area"' ) );
check( 'number input is text decimal', true, str_contains( $html, 'inputmode="decimal"' ) );
check( 'owner radios in fieldset', true, str_contains( $html, '<fieldset' ) && str_contains( $html, '<legend' ) && str_contains( $html, 'type="radio"' ) );
check( 'new owner input', true, str_contains( $html, 'name="portfolio_owner_new"' ) );
check( 'home select with none option', true, str_contains( $html, '<select' ) && str_contains( $html, 'Ei ilmoitusta' ) );
wp_update_post( [ 'ID' => $home_id, 'post_status' => 'trash' ] );
ob_start();
render_portfolio_meta_box( get_post( $id ) );
$trashed_html = ob_get_clean();
check( 'trashed saved home stays selected', true, 1 === preg_match( '#<option value="' . $home_id . '"[^>]* selected[^>]*>\s*Portfolio test home \(roskakorissa\)#', $trashed_html ) );
wp_update_post( [ 'ID' => $home_id, 'post_status' => 'draft' ] );
check( 'home select preselected', true, 1 === preg_match( '#<option value="' . $home_id . '"[^>]* selected#', $html ) );
check( 'finnish formatted value', true, str_contains( $html, 'value="46,5"' ) );
check( 'calculated line', true, str_contains( $html, 'Nettoarvo' ) && str_contains( $html, 'Vakuusarvo' ) && str_contains( $html, 'Vapaata vakuutta' ) );
check( 'meta box hooked', 10, has_action( 'add_meta_boxes_portfolio', 'Ketunkoti\\add_portfolio_meta_box' ) );
check( 'save hooked', 10, has_action( 'save_post_portfolio', 'Ketunkoti\\save_portfolio_meta_box' ) );

// Overview page. Clear fixtures of the earlier checks so the counts are exact.
foreach ( $GLOBALS['ketunkoti_test_posts'] as $old_post_id ) {
    wp_delete_post( $old_post_id, true );
}
$GLOBALS['ketunkoti_test_posts'] = [];

$overview_owner = wp_insert_term( 'Overview test owner A', 'portfolio-owner' );
$GLOBALS['ketunkoti_test_terms'][] = [ 'id' => $overview_owner['term_id'], 'taxonomy' => 'portfolio-owner' ];

$p1 = create_test_portfolio( 'Overview apartment one' );
$p2 = create_test_portfolio( 'Overview apartment two' );
$p3 = create_test_portfolio( 'Overview apartment draft' );
wp_set_object_terms( $p1, [ (int) $overview_owner['term_id'] ], 'portfolio-owner' );
wp_set_object_terms( $p3, [ (int) $overview_owner['term_id'] ], 'portfolio-owner' );
wp_update_post( [ 'ID' => $p3, 'post_status' => 'draft' ] );
update_post_meta( $p1, 'portfolio_estimated_value', '200000' );
update_post_meta( $p1, 'portfolio_company_loan', '30000' );
update_post_meta( $p1, 'portfolio_company_loan_checked', '2024-01-01' );
update_post_meta( $p1, 'portfolio_bank_loan', '90000' );
update_post_meta( $p1, 'portfolio_used_collateral', '50000' );
update_post_meta( $p2, 'portfolio_estimated_value', '100000' );
update_post_meta( $p3, 'portfolio_estimated_value', '999000' );

$o = get_portfolio_overview();
check( 'no owner group exists', true, in_array( 'Ei omistajaa', wp_list_pluck( $o['groups'], 'name' ), true ) );
check( 'no owner group is last', 'Ei omistajaa', end( $o['groups'] )['name'] );
check( 'combined counts no-owner', 2, $o['combined']['count'] );
check( 'combined value excludes draft', 300000.0, $o['combined']['value'] );
check( 'draft not counted', 1, $o['groups'][0]['totals']['count'] );
check( 'draft listed', 1, count( $o['groups'][0]['drafts'] ) );
check( 'owner group stale flag', true, $o['groups'][0]['apartments'][0]['loan_check_stale'] );
check( 'stale old', true, is_portfolio_loan_check_stale( '2024-01-01', strtotime( '2026-06-01' ) ) );
check( 'stale fresh', false, is_portfolio_loan_check_stale( '2026-05-01', strtotime( '2026-06-01' ) ) );
check( 'stale empty', true, is_portfolio_loan_check_stale( null, time() ) );
$html = get_portfolio_overview_html( $o );
check( 'html has drafts line', true, str_contains( $html, 'Luonnokset (ei mukana summissa)' ) );
check( 'html has caption', true, str_contains( $html, '<caption' ) );
check( 'html has stale marker', true, str_contains( $html, '<span class="ketunkoti-portfolio-stale">tarkista</span>' ) );
check( 'html has no negative styling', false, str_contains( $html, 'ketunkoti-portfolio-negative' ) );
check( 'over-used free collateral floors at zero', 0.0, $o['groups'][0]['apartments'][0]['results']['free_collateral'] );
check( 'group free collateral floors at zero', 0.0, $o['groups'][0]['totals']['free_collateral'] );
check( 'html has no minus sign', false, str_contains( $html, "\u{2212}" ) );
check( 'html scroll wrapper focusable region', true, str_contains( $html, 'tabindex="0" role="region" aria-labelledby="ketunkoti-portfolio-caption-summary"' ) );
check( 'html summary caption id', true, str_contains( $html, '<caption id="ketunkoti-portfolio-caption-summary">' ) );
check( 'html group caption ids unique', true, str_contains( $html, 'aria-labelledby="ketunkoti-portfolio-caption-0"' ) && str_contains( $html, '<caption id="ketunkoti-portfolio-caption-1">' ) );
check( 'html links to edit screen', true, str_contains( $html, 'post=' . $p1 ) );
$empty = get_portfolio_overview_html( [ 'combined' => calculate_portfolio_totals( [] ), 'groups' => [] ] );
check( 'empty message', true, str_contains( $empty, 'Salkussa ei ole vielä asuntoja.' ) );
check( 'empty leverage dash', true, str_contains( $empty, '–' ) );
check( 'overview page hooked', 10, has_action( 'admin_menu', 'Ketunkoti\\add_portfolio_overview_page' ) );

// Statuses: private counts like publish; pending and future are listed, not counted.
$p4 = create_test_portfolio( 'Overview apartment private' );
$p5 = create_test_portfolio( 'Overview apartment pending' );
$p6 = create_test_portfolio( 'Overview apartment future' );
foreach ( [ $p4, $p5, $p6 ] as $status_post ) {
    wp_set_object_terms( $status_post, [ (int) $overview_owner['term_id'] ], 'portfolio-owner' );
    update_post_meta( $status_post, 'portfolio_estimated_value', '1000' );
}
wp_update_post( [ 'ID' => $p4, 'post_status' => 'private' ] );
wp_update_post( [ 'ID' => $p5, 'post_status' => 'pending' ] );
wp_update_post( [ 'ID' => $p6, 'post_status' => 'future', 'post_date' => gmdate( 'Y-m-d H:i:s', time() + YEAR_IN_SECONDS ), 'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() + YEAR_IN_SECONDS ) ] );
check( 'future status set', 'future', get_post_status( $p6 ) );

$o2    = get_portfolio_overview();
$owner = $o2['groups'][0];
check( 'private counted in group', 2, $owner['totals']['count'] );
check( 'private value in totals', 201000.0, $owner['totals']['value'] );
check( 'private in apartments table', true, in_array( $p4, wp_list_pluck( $owner['apartments'], 'id' ), true ) );
check( 'pending and future listed with draft', 3, count( $owner['drafts'] ) );
$draft_statuses = wp_list_pluck( $owner['drafts'], 'status', 'id' );
check( 'draft status key', 'draft', $draft_statuses[ $p3 ] );
check( 'pending status key', 'pending', $draft_statuses[ $p5 ] );
check( 'future status key', 'future', $draft_statuses[ $p6 ] );
$html2 = get_portfolio_overview_html( $o2 );
check( 'html draft label', true, str_contains( $html2, 'Overview apartment draft</a> (luonnos)' ) );
check( 'html pending label', true, str_contains( $html2, 'Overview apartment pending</a> (odottaa)' ) );
check( 'html future label', true, str_contains( $html2, 'Overview apartment future</a> (ajastettu)' ) );
check( 'private title has no prefix', 'Overview apartment private', wp_list_pluck( $owner['apartments'], 'title', 'id' )[ $p4 ] );

// Titles come back raw: no texturize, no "Private:" prefix.
$dash_id = create_test_portfolio( 'Kalle - Kuja 5 & "A"' );
$o3      = get_portfolio_overview();
$titles  = [];
foreach ( $o3['groups'] as $g ) {
    foreach ( $g['apartments'] as $a ) {
        $titles[ $a['id'] ] = $a['title'];
    }
}
check( 'title with dash unchanged', 'Kalle - Kuja 5 & "A"', $titles[ $dash_id ] ?? null );

// Summary rows: an owner named like a fixed row cannot overwrite it.
$clash = wp_insert_term( 'Yhteensä', 'portfolio-owner' );
$GLOBALS['ketunkoti_test_terms'][] = [ 'id' => $clash['term_id'], 'taxonomy' => 'portfolio-owner' ];
wp_set_object_terms( $dash_id, [ (int) $clash['term_id'] ], 'portfolio-owner' );
$o4    = get_portfolio_overview();
$html4 = get_portfolio_overview_html( $o4 );
check( 'summary keeps combined and owner rows', true, preg_match_all( '#<th scope="row">Yhteensä</th>#', substr( $html4, 0, (int) strpos( $html4, '</table>' ) ) ) >= 2 );

// Teardown.
teardown_test_data();

if ( $GLOBALS['ketunkoti_test_failures'] > 0 ) {
    echo $GLOBALS['ketunkoti_test_failures'], ' check(s) failed.', PHP_EOL;
    exit( 1 );
}

echo 'All checks passed.', PHP_EOL;
