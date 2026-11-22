<?php
/**
 * Tests for the read abilities and the ability registration.
 *
 * @package Ketunkoti\AI
 */

namespace Ketunkoti\AI\Tests;

use Ketunkoti\AI\Registrar;

require_once __DIR__ . '/bootstrap.php';

$get_home   = wp_get_ability( 'ketunkoti/get-home' );
$list_homes = wp_get_ability( 'ketunkoti/list-homes' );
if ( ! $get_home || ! $list_homes ) {
	check( 'read abilities registered', true, false );
	finish();
	return;
}

make_user( 'editor' );
$home = make_home(
	'home',
	[
		'home_area' => 54.5,
		'home_rent' => 780,
	],
	[ 'home-city' => [ 'Lahti' ] ]
);
$res  = $get_home->execute( [ 'id' => $home ] );

check( 'get-home area', '54.5', $res['fields']['home_area']['value'] ?? $res );
check( 'get-home label', 'Asuinpinta-ala', $res['fields']['home_area']['label'] ?? $res );
check( 'get-home unset field null', true, array_key_exists( 'value', $res['fields']['home_floor'] ?? [] ) && null === $res['fields']['home_floor']['value'] );
check( 'get-home city', 'Lahti', $res['terms']['home-city'][0]['name'] ?? $res );
check( 'get-home all taxonomies', get_object_taxonomies( 'home' ), array_keys( $res['terms'] ?? [] ) );
check( 'get-home calculated keys', [ 'home_rental_yield', 'home_cash_flow', 'home_equity_return' ], array_keys( $res['calculated'] ?? [] ) );
check( 'get-home has no ai_owned', false, array_key_exists( 'ai_owned', is_array( $res ) ? $res : [] ) );
check( 'get-home page refused', true, is_wp_error( $get_home->execute( [ 'id' => self_page() ] ) ) );

$rental     = make_home( 'rental', [ 'home_rent' => 700 ] );
$rental_res = $get_home->execute( [ 'id' => $rental ] );
check( 'get-home rental calculated null', true, is_array( $rental_res ) && array_key_exists( 'calculated', $rental_res ) && null === $rental_res['calculated'] );

$list = $list_homes->execute( [ 'search' => get_the_title( $home ) ] );
check( 'list finds home', $home, $list['items'][0]['id'] ?? $list );
check( 'list city', 'Lahti', $list['items'][0]['city'] ?? $list );
check( 'list total', 1, $list['total'] ?? $list );
$rentals = $list_homes->execute(
	[
		'post_type' => 'rental',
		'search'    => get_the_title( $rental ),
	]
);
check( 'list by type', 'rental', $rentals['items'][0]['post_type'] ?? $rentals );

$tools = Registrar::add_tools( [ 'tools' => [ 'other/tool' ] ] )['tools'];
check( 'mcp tools keep existing', 'other/tool', $tools[0] );
// Every tool ability in the category is listed; prompts are exposed as MCP prompts instead.
$registered = array_keys(
	array_filter(
		wp_get_abilities( [ 'category' => 'ketunkoti' ] ),
		static fn( $ability ) => 'tool' === ( $ability->get_meta()['mcp']['type'] ?? 'tool' )
	)
);
$listed     = array_values( array_diff( $tools, [ 'other/tool' ] ) );
sort( $registered );
sort( $listed );
check( 'mcp tools list every ability', $registered, $listed );
check( 'no meom filter', false, has_filter( 'meom_ai_mcp_abilities' ) );
check( 'get-home public', true, $get_home->get_meta()['public'] ?? false );
check( 'get-home mcp public', true, $get_home->get_meta()['mcp']['public'] ?? false );
check( 'get-home annotations', [ true, false, true ], array_values( $get_home->get_meta()['annotations'] ?? [] ) );
check( 'show in rest', true, $get_home->get_meta()['show_in_rest'] ?? false );
check( 'category', 'ketunkoti', $get_home->get_category() );

make_user( 'subscriber' );
check( 'subscriber refused', true, is_wp_error( $get_home->execute( [ 'id' => $home ] ) ) );

finish();
