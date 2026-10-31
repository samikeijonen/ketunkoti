<?php
/**
 * Tests for the home field rules: discovery, validation and writing.
 *
 * @package Ketunkoti\AI
 */

namespace Ketunkoti\AI\Tests;

use Ketunkoti\AI\Home_Fields;

require_once __DIR__ . '/bootstrap.php';

make_user( 'editor' );
$home   = make_home( 'home', [ 'home_area' => 54.5 ] );
$rental = make_home( 'rental' );

// Discovery.
check( 'home keys include price', true, isset( Home_Fields::keys( 'home' )['home_debt_free_price'] ) );
check( 'rental keys exclude price', false, isset( Home_Fields::keys( 'rental' )['home_debt_free_price'] ) );
check( 'rental keys include pets', true, isset( Home_Fields::keys( 'rental' )['home_pets'] ) );
check( 'computed not a key', false, isset( Home_Fields::keys( 'home' )['home_rental_yield'] ) );
check( 'key has label', 'Asuinpinta-ala', Home_Fields::keys( 'home' )['home_area']['label'] );

// Refusals.
check( 'not a home', 'ketunkoti_ai_not_a_home', code( Home_Fields::prepare( self_page(), [ 'home_area' => 1 ] ) ) );
check( 'unknown field', 'ketunkoti_ai_unknown_field', code( Home_Fields::prepare( $home, [ 'home_rental_yield' => 5 ] ) ) );
check( 'home-only key on rental', 'ketunkoti_ai_unknown_field', code( Home_Fields::prepare( $rental, [ 'home_debt_free_price' => 1 ] ) ) );
check( 'bad date refused', 'ketunkoti_ai_invalid_value', code( Home_Fields::prepare( $home, [ 'home_available_from' => '2026-02-31' ] ) ) );
check( 'bad year refused', 'ketunkoti_ai_invalid_value', code( Home_Fields::prepare( $home, [ 'home_year_built' => 'vanha' ] ) ) );
check( 'one bad key fails all', 'ketunkoti_ai_unknown_field', code( Home_Fields::prepare( $home, [ 'home_area' => 60, 'nope' => 1 ] ) ) );

// Accepted values.
$prepared = Home_Fields::prepare( $home, [ 'home_maintenance_charge' => '1 285,50' ] );
check( 'finnish number', 1285.5, is_array( $prepared ) ? $prepared['home_maintenance_charge'] : $prepared );
$prepared = Home_Fields::prepare( $home, [ 'home_capital_charge' => '0,00' ] );
check( 'zero is allowed', 0.0, is_array( $prepared ) ? (float) $prepared['home_capital_charge'] : $prepared );
check( 'null deletes', [ 'home_area' => null ], Home_Fields::prepare( $home, [ 'home_area' => null ] ) );
check( 'blank deletes', [ 'home_floor' => null ], Home_Fields::prepare( $home, [ 'home_floor' => '  ' ] ) );

// Numbers in Finnish format, and values the sanitizers would silently change.
$prepared = Home_Fields::prepare( $home, [ 'home_debt_free_price' => '250 000' ] );
check( 'price with spaces', 250000, is_array( $prepared ) ? $prepared['home_debt_free_price'] : $prepared );
$prepared = Home_Fields::prepare( $home, [ 'home_selling_price' => "189\u{00A0}000" ] );
check( 'price with nbsp', 189000, is_array( $prepared ) ? $prepared['home_selling_price'] : $prepared );
check( 'decimals on integer refused', 'ketunkoti_ai_invalid_value', code( Home_Fields::prepare( $home, [ 'home_selling_price' => '1 285,50' ] ) ) );
check( 'negative refused', 'ketunkoti_ai_invalid_value', code( Home_Fields::prepare( $home, [ 'home_debt_free_price' => '-5' ] ) ) );
check( 'text on number refused', 'ketunkoti_ai_invalid_value', code( Home_Fields::prepare( $home, [ 'home_area' => 'iso' ] ) ) );
check( 'zero on date refused', 'ketunkoti_ai_invalid_value', code( Home_Fields::prepare( $home, [ 'home_available_from' => '0' ] ) ) );
check( 'zero on yes/no refused', 'ketunkoti_ai_invalid_value', code( Home_Fields::prepare( $home, [ 'home_elevator' => 0 ] ) ) );

// Writing and reading back.
Home_Fields::write( $home, Home_Fields::prepare( $home, [ 'home_area' => 61, 'home_floor' => '2/4' ] ) );
check( 'write then values', [ 'home_area' => '61', 'home_floor' => '2/4' ], Home_Fields::values( $home, [ 'home_area', 'home_floor' ] ) );
Home_Fields::write( $home, Home_Fields::prepare( $home, [ 'home_floor' => null ] ) );
check( 'delete then values', [ 'home_floor' => null ], Home_Fields::values( $home, [ 'home_floor' ] ) );
check( 'is_home', [ true, true, false ], [ Home_Fields::is_home( $home ), Home_Fields::is_home( $rental ), Home_Fields::is_home( self_page() ) ] );

finish();
