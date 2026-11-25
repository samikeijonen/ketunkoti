<?php
/**
 * Tests for the custom post types' exposure to the Abilities API.
 *
 * Run inside the wp-env CLI container:
 *
 *     wp eval-file wp-content/plugins/ketunkoti-features/tests/php/test-post-types.php
 *
 * Prints one PASS/FAIL line per check and exits with status 1 if any failed.
 * `home` and `rental` opt in with `show_in_abilities` so that core's
 * `core/content-query` (WordPress 7.2+) can find them; the private portfolio
 * types must stay out.
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti\Tests;

use const Ketunkoti\PORTFOLIO_POST_TYPE;
use const Ketunkoti\PORTFOLIO_SNAPSHOT_POST_TYPE;

// Only runnable through WP-CLI, never over HTTP.
if ( ! defined( 'WP_CLI' ) ) {
	exit;
}

// wp eval-file runs this file inside a method, so the counter lives in
// $GLOBALS rather than in a file-level variable.
$GLOBALS['ketunkoti_test_failures'] = 0;

/**
 * Compares an expected and an actual value strictly and prints the result.
 *
 * @param string $name     Check name.
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @return void
 */
function check( string $name, $expected, $actual ): void {
	if ( $expected === $actual ) {
		echo 'PASS ', $name, PHP_EOL;
		return;
	}

	++$GLOBALS['ketunkoti_test_failures'];
	echo 'FAIL ', $name, ': expected ', var_export( $expected, true ), ', got ', var_export( $actual, true ), PHP_EOL;
}

/**
 * Reads a post type's `show_in_abilities` argument.
 *
 * @param string $post_type Post type name.
 * @return bool|null The argument, or null when the post type is not registered.
 */
function shows_in_abilities( string $post_type ): ?bool {
	$object = get_post_type_object( $post_type );

	return $object ? (bool) ( $object->show_in_abilities ?? false ) : null;
}

check( 'home shows in abilities', true, shows_in_abilities( 'home' ) );
check( 'rental shows in abilities', true, shows_in_abilities( 'rental' ) );
check( 'portfolio stays out of abilities', false, shows_in_abilities( PORTFOLIO_POST_TYPE ) );
check( 'portfolio snapshot stays out of abilities', false, shows_in_abilities( PORTFOLIO_SNAPSHOT_POST_TYPE ) );

// core/content-query only exists from WordPress 7.2 on.
$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'core/content-query' ) : null;

if ( $ability ) {
	$admins = get_users(
		[
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		]
	);
	wp_set_current_user( (int) ( $admins[0] ?? 0 ) );

	foreach ( [ 'home', 'rental' ] as $post_type ) {
		$result = $ability->execute( [ 'where' => [ 'type' => $post_type ] ] );
		check( "content-query accepts {$post_type}", false, is_wp_error( $result ) );
	}

	$result = $ability->execute( [ 'where' => [ 'type' => PORTFOLIO_POST_TYPE ] ] );
	check( 'content-query refuses portfolio', true, is_wp_error( $result ) );
} else {
	echo 'SKIP content-query checks: core/content-query is not registered (WordPress < 7.2).', PHP_EOL;
}

if ( $GLOBALS['ketunkoti_test_failures'] > 0 ) {
	echo $GLOBALS['ketunkoti_test_failures'], ' check(s) failed.', PHP_EOL;
	exit( 1 );
}

echo 'All checks passed.', PHP_EOL;
