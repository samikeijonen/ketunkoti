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

use function Ketunkoti\get_portfolio_field_values;
use function Ketunkoti\get_portfolio_fields;
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
$_POST       = [
    'ketunkoti_portfolio_nonce' => wp_create_nonce( 'ketunkoti_portfolio_save' ),
    'portfolio_area'            => '3',
];
if ( $revision_id ) {
    save_portfolio_meta_box( (int) $revision_id );
    check( 'revision not written', '', (string) get_post_meta( (int) $revision_id, 'portfolio_area', true ) );
}
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
check( 'home select preselected', true, 1 === preg_match( '#<option value="' . $home_id . '"[^>]* selected#', $html ) );
check( 'finnish formatted value', true, str_contains( $html, 'value="46,5"' ) );
check( 'calculated line', true, str_contains( $html, 'Nettoarvo' ) && str_contains( $html, 'Vakuusarvo' ) && str_contains( $html, 'Vapaata vakuutta' ) );
check( 'meta box hooked', 10, has_action( 'add_meta_boxes_portfolio', 'Ketunkoti\\add_portfolio_meta_box' ) );
check( 'save hooked', 10, has_action( 'save_post_portfolio', 'Ketunkoti\\save_portfolio_meta_box' ) );

// Teardown.
teardown_test_data();

if ( $GLOBALS['ketunkoti_test_failures'] > 0 ) {
    echo $GLOBALS['ketunkoti_test_failures'], ' check(s) failed.', PHP_EOL;
    exit( 1 );
}

echo 'All checks passed.', PHP_EOL;
