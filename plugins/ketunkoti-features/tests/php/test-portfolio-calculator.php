<?php
/**
 * Tests for the Salkku portfolio calculations.
 *
 * Run inside the wp-env CLI container:
 *
 *     wp eval-file wp-content/plugins/ketunkoti-features/tests/php/test-portfolio-calculator.php
 *
 * Prints one PASS/FAIL line per check and exits with status 1 if any failed.
 * The apartments below are invented.
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti\Tests;

use function Ketunkoti\calculate_portfolio_apartment;
use function Ketunkoti\calculate_portfolio_totals;
use function Ketunkoti\format_portfolio_euros;
use function Ketunkoti\format_portfolio_percent;

// Only runnable through WP-CLI, never over HTTP.
if ( ! defined( 'WP_CLI' ) ) {
    exit;
}

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

$a = [ 'portfolio_estimated_value' => 200000, 'portfolio_company_loan' => 100000, 'portfolio_bank_loan' => 20000, 'portfolio_used_collateral' => 5000, 'portfolio_collateral_rate' => '', 'portfolio_rent' => 900, 'portfolio_charges' => 400, 'portfolio_debt_free_price' => 180000 ];
$b = [ 'portfolio_estimated_value' => 100000, 'portfolio_company_loan' => 80000, 'portfolio_collateral_rate' => 80 ];
$c = [ 'portfolio_estimated_value' => 100000, 'portfolio_company_loan' => 76000, 'portfolio_collateral_rate' => 75 ];

check( 'a debts', 120000.0, calculate_portfolio_apartment( $a )['debts'] );
check( 'a net', 80000.0, calculate_portfolio_apartment( $a )['net_value'] );
check( 'a collateral default 75', 150000.0, calculate_portfolio_apartment( $a )['collateral_value'] );
check( 'a free', 25000.0, calculate_portfolio_apartment( $a )['free_collateral'] );
check( 'b collateral 80', 80000.0, calculate_portfolio_apartment( $b )['collateral_value'] );
check( 'b free zero', 0.0, calculate_portfolio_apartment( $b )['free_collateral'] );
check( 'c free floored at zero', 0.0, calculate_portfolio_apartment( $c )['free_collateral'] );
check( 'zero rate means 75', 75000.0, calculate_portfolio_apartment( [ 'portfolio_estimated_value' => 100000, 'portfolio_collateral_rate' => 0 ] )['collateral_value'] );

$t = calculate_portfolio_totals( [ $a, $c ] );
check( 'totals free floors each apartment', 25000.0, $t['free_collateral'] );
check( 'totals debts', 196000.0, $t['debts'] );
check( 'totals net worth', 104000.0, $t['net_worth'] );
check( 'totals value', 300000.0, $t['value'] );
check( 'totals leverage', 0.65, round( $t['leverage'], 2 ) );
check( 'totals rent', 900.0, $t['rent'] );
check( 'totals count', 2, $t['count'] );
check( 'empty leverage null', null, calculate_portfolio_totals( [] )['leverage'] );
check( 'empty value zero', 0.0, calculate_portfolio_totals( [] )['value'] );

check( 'euros', "1\u{00A0}234\u{00A0}567\u{00A0}€", format_portfolio_euros( 1234567.4 ) );
check( 'euros negative', "\u{2212}25\u{00A0}€", format_portfolio_euros( -24.57 ) );
check( 'percent', "65,3\u{00A0}%", format_portfolio_percent( 0.65333 ) );
check( 'percent null', '–', format_portfolio_percent( null ) );

if ( $GLOBALS['ketunkoti_test_failures'] > 0 ) {
    echo $GLOBALS['ketunkoti_test_failures'], ' check(s) failed.', PHP_EOL;
    exit( 1 );
}

echo 'All checks passed.', PHP_EOL;
