<?php
/**
 * Shared helpers for the ketunkoti-ai tests.
 *
 * Every test file requires this one and runs inside the wp-env tests
 * container:
 *
 *     npx wp-env run tests-cli wp eval-file wp-content/plugins/ketunkoti-ai/tests/php/<file>.php
 *
 * Each check prints one PASS/FAIL line. finish() removes the posts and users
 * the test created, prints ALL PASSED or exits with status 1.
 *
 * @package Ketunkoti\AI
 */

namespace Ketunkoti\AI\Tests;

// Only runnable through WP-CLI, never over HTTP.
if ( ! defined( 'WP_CLI' ) ) {
    exit;
}

// wp eval-file runs the file inside a method, so state lives in $GLOBALS.
$GLOBALS['ketunkoti_ai_test_failures'] = 0;
$GLOBALS['ketunkoti_ai_test_posts']    = [];
$GLOBALS['ketunkoti_ai_test_users']    = [];

/**
 * Compares an expected and an actual value and prints the result.
 *
 * Floats are compared rounded to two decimals, everything else strictly.
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

    ++$GLOBALS['ketunkoti_ai_test_failures'];
    if ( $actual instanceof \WP_Error ) {
        $actual = 'WP_Error ' . $actual->get_error_code() . ': ' . $actual->get_error_message();
    }
    echo 'FAIL ', $name, ': expected ', var_export( $expected, true ), ', got ', var_export( $actual, true ), PHP_EOL;
}

/**
 * Creates a user with the given role and makes it the current user.
 *
 * @param string $role User role.
 * @return int User ID.
 */
function make_user( string $role = 'editor' ): int {
    $user_id = wp_insert_user(
        [
            'user_login' => 'ketunkoti-ai-test-' . wp_generate_password( 8, false ),
            'user_pass'  => wp_generate_password(),
            'role'       => $role,
        ]
    );

    $GLOBALS['ketunkoti_ai_test_users'][] = $user_id;
    wp_set_current_user( $user_id );

    return $user_id;
}

/**
 * Creates a published home or rental with meta and terms.
 *
 * @param string $type  Post type, home or rental.
 * @param array  $meta  Meta key => value.
 * @param array  $terms Taxonomy => list of term names; missing terms are created.
 * @return int Post ID.
 */
function make_home( string $type = 'home', array $meta = [], array $terms = [] ): int {
    $post_id = wp_insert_post(
        [
            'post_type'    => $type,
            'post_status'  => 'publish',
            'post_title'   => 'Testikoti ' . wp_generate_password( 6, false ),
            'post_content' => "<!-- wp:paragraph -->\n<p>Valoisa koti.</p>\n<!-- /wp:paragraph -->",
            'post_excerpt' => 'Lyhyt kuvaus.',
        ]
    );

    foreach ( $meta as $key => $value ) {
        update_post_meta( $post_id, $key, $value );
    }
    foreach ( $terms as $taxonomy => $names ) {
        wp_set_object_terms( $post_id, $names, $taxonomy );
    }

    $GLOBALS['ketunkoti_ai_test_posts'][] = $post_id;

    return $post_id;
}

/**
 * Creates a published page, a post that is not a home.
 *
 * @return int Post ID.
 */
function self_page(): int {
    $post_id = wp_insert_post(
        [
            'post_type'   => 'page',
            'post_status' => 'publish',
            'post_title'  => 'Testisivu',
        ]
    );

    $GLOBALS['ketunkoti_ai_test_posts'][] = $post_id;

    return $post_id;
}

/**
 * Remembers a post created by the code under test, so finish() removes it.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function track_post( int $post_id ): void {
    $GLOBALS['ketunkoti_ai_test_posts'][] = $post_id;
}

/**
 * Returns the error code of a result, so a check on an error never fatals
 * when the code under test returns something else.
 *
 * @param mixed $result Result of the code under test.
 * @return string Error code, or "not an error".
 */
function code( $result ): string {
    return $result instanceof \WP_Error ? (string) $result->get_error_code() : 'not an error';
}

/**
 * Removes test data and reports the result.
 *
 * @return void
 */
function finish(): void {
    foreach ( $GLOBALS['ketunkoti_ai_test_posts'] as $post_id ) {
        wp_delete_post( $post_id, true );
    }

    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ( $GLOBALS['ketunkoti_ai_test_users'] as $user_id ) {
        wp_delete_user( $user_id );
    }

    if ( $GLOBALS['ketunkoti_ai_test_failures'] > 0 ) {
        echo $GLOBALS['ketunkoti_ai_test_failures'], ' check(s) failed.', PHP_EOL;
        exit( 1 );
    }

    echo 'ALL PASSED', PHP_EOL;
}
