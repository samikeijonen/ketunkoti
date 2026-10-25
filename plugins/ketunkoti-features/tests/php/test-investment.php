<?php
/**
 * Tests for the investment calculations and the related meta registration.
 *
 * Run inside the wp-env CLI container:
 *
 *     wp eval-file wp-content/plugins/ketunkoti-features/tests/php/test-investment.php
 *
 * Prints one PASS/FAIL line per check and exits with status 1 if any failed.
 * The expected values come from the design document
 * `docs/superpowers/specs/2026-10-25-vuokrakodit-ja-tuottolaskelmat-design.md`
 * and must match the JS twin, `src/investment.test.js`.
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti\Tests;

use function Ketunkoti\calculate_cash_flow;
use function Ketunkoti\calculate_equity_return;
use function Ketunkoti\calculate_first_year_interest;
use function Ketunkoti\format_cash_flow;
use function Ketunkoti\format_interest_rate;
use function Ketunkoti\normalise_interest_rate;
use function Ketunkoti\sanitize_positive_number;

// Only runnable through WP-CLI, never over HTTP.
if ( ! defined( 'WP_CLI' ) ) {
    exit;
}

// number_format_i18n() must use the Finnish decimal comma.
switch_to_locale( 'fi' );

// wp eval-file runs this file inside a method, so the counter lives in
// $GLOBALS rather than in a file-level variable.
$GLOBALS['ketunkoti_test_failures'] = 0;

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

// Lahti, worked by hand on 25.10.2026.
check( 'lahti interest', 4800.07, calculate_first_year_interest( 112725, 4.3, 600.72 ) );
check( 'lahti cash flow', 27.98, calculate_cash_flow( 780, 151.30, 600.72 ) );
check( 'lahti equity', 10.56, calculate_equity_return( 780, 151.30, 600.72, 26000, 138725, 4.3 ) );
check( 'lahti cash flow fmt', "+28\u{00A0}€/kk", format_cash_flow( 27.98 ) );

// Finnoo kulmayksiö, post 358.
check( 'finnoo cash flow', -304.30, calculate_cash_flow( 675, 106.40, 872.90 ) );
check( 'finnoo cash flow fmt', "\u{2212}304\u{00A0}€/kk", format_cash_flow( -304.30 ) );
check( 'finnoo equity null', null, calculate_equity_return( 675, 106.40, 872.90, 22000, 166000, null ) );

// No loan: korko is irrelevant whether set or not.
check( 'no loan equity', 7.2, calculate_equity_return( 800, 200, 0, 100000, 100000, null ) );
check( 'no loan equity w/ rate', 7.2, calculate_equity_return( 800, 200, 0, 100000, 100000, 4.3 ) );
check( 'no loan cash flow', 600.0, calculate_cash_flow( 800, 200, 0 ) );

// No rent.
check( 'no rent cash flow', null, calculate_cash_flow( 0, 150, 600 ) );
check( 'no rent equity', null, calculate_equity_return( 0, 150, 600, 26000, 138725, 4.3 ) );

// No selling price.
check( 'no price equity', null, calculate_equity_return( 780, 151.30, 600.72, 0, 138725, 4.3 ) );

// Rounding and zero.
check( 'zero cash flow fmt', "0\u{00A0}€/kk", format_cash_flow( 0.4 ) );
check( 'half away from zero', "\u{2212}1\u{00A0}€/kk", format_cash_flow( -0.5 ) );

// Interest rate format.
check( 'rate 4.3', "4,3\u{00A0}%", format_interest_rate( 4.3 ) );
check( 'rate 4.25', "4,25\u{00A0}%", format_interest_rate( 4.25 ) );
check( 'rate 4', "4\u{00A0}%", format_interest_rate( 4.0 ) );

// Stored korko: the editor saves 0 for an empty field, which must read as
// "not entered", never as 0 %.
check( 'korko 0 is not entered', null, normalise_interest_rate( 0 ) );
check( 'korko "0" is not entered', null, normalise_interest_rate( '0' ) );
check( 'korko empty is not entered', null, normalise_interest_rate( '' ) );
check( 'korko 4.3 is kept', 4.3, normalise_interest_rate( '4.3' ) );

// Plot taxonomy: homes for sale only, bindable as "home_plot".
check( 'plot taxonomy on home', [ 'home' ], get_taxonomy( 'home-plot' ) ? get_taxonomy( 'home-plot' )->object_type : null );
check( 'plot binding key', 'home-plot', \Ketunkoti\TAXONOMY_FIELDS['home_plot'] ?? null );

// Rental fields: lease type, water charge basis, elevator.
check( 'rental has lease type', true, registered_meta_key_exists( 'post', 'home_lease_type', 'rental' ) );
check( 'rental has elevator', true, registered_meta_key_exists( 'post', 'home_elevator', 'rental' ) );
check( 'rental has water basis', true, registered_meta_key_exists( 'post', 'home_water_charge_basis', 'rental' ) );
check( 'home has lease type', true, registered_meta_key_exists( 'post', 'home_lease_type', 'home' ) );
check( 'home has elevator', true, registered_meta_key_exists( 'post', 'home_elevator', 'home' ) );
check( 'home has water basis', true, registered_meta_key_exists( 'post', 'home_water_charge_basis', 'home' ) );
check( 'water basis month kept', 'month', \Ketunkoti\sanitize_water_charge_basis( 'month' ) );
check( 'water basis junk dropped', '', \Ketunkoti\sanitize_water_charge_basis( 'weekly' ) );
check( 'yes kept', 'yes', \Ketunkoti\sanitize_yes_no( 'yes' ) );
check( 'yes/no junk dropped', '', \Ketunkoti\sanitize_yes_no( 'maybe' ) );
check( 'water per person (default)', '25,00 €/hlö/kk', \Ketunkoti\format_water_charge( 25, '' ) );
check( 'water per month', '20,00 €/kk', \Ketunkoti\format_water_charge( 20, 'month' ) );
check( 'water by consumption', '20,00 €/kk, kulutuksen mukaan', \Ketunkoti\format_water_charge( 20, 'consumption' ) );
check( 'water empty', null, \Ketunkoti\format_water_charge( 0, 'month' ) );
check( 'elevator yes', 'Kyllä', \Ketunkoti\format_yes_no( 'yes' ) );
check( 'elevator no', 'Ei', \Ketunkoti\format_yes_no( 'no' ) );
check( 'elevator empty', null, \Ketunkoti\format_yes_no( '' ) );

// Meta registration.
check( 'korko finnish comma', 4.3, sanitize_positive_number( '4,3' ) );
check( 'rental has rent meta', true, registered_meta_key_exists( 'post', 'home_rent', 'rental' ) );
check( 'rental has pets meta', true, registered_meta_key_exists( 'post', 'home_pets', 'rental' ) );
check( 'home has no pets meta', false, registered_meta_key_exists( 'post', 'home_pets', 'home' ) );
check( 'home has loan interest', true, registered_meta_key_exists( 'post', 'home_loan_interest', 'home' ) );
check( 'rental has no selling price', false, registered_meta_key_exists( 'post', 'home_selling_price', 'rental' ) );

if ( $GLOBALS['ketunkoti_test_failures'] > 0 ) {
    echo $GLOBALS['ketunkoti_test_failures'], ' check(s) failed.', PHP_EOL;
    exit( 1 );
}

echo 'All checks passed.', PHP_EOL;
