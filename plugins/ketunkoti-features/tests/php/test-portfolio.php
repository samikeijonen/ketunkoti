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
check( 'logged out query finds nothing', 0, ( new WP_Query( [ 'p' => $id, 'post_type' => 'any' ] ) )->found_posts );

// Checks: field values.
update_post_meta( $id, 'portfolio_area', '44.5' );
check( 'typed value', 44.5, get_portfolio_field_values( $id )['portfolio_area'] );
check( 'unset is null', null, get_portfolio_field_values( $id )['portfolio_bank_loan'] );

// Teardown.
teardown_test_data();

if ( $GLOBALS['ketunkoti_test_failures'] > 0 ) {
    echo $GLOBALS['ketunkoti_test_failures'], ' check(s) failed.', PHP_EOL;
    exit( 1 );
}

echo 'All checks passed.', PHP_EOL;
