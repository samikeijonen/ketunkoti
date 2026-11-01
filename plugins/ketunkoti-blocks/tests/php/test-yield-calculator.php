<?php
/**
 * Tests for the yield calculator layer.
 *
 * Run inside the wp-env CLI container:
 *
 *     wp eval-file wp-content/plugins/ketunkoti-blocks/tests/php/test-yield-calculator.php
 *
 * Mirrors `src/blocks/yield-calculator/calculator.test.js` one to one: the
 * server's first paint and the live JS updates must produce identical strings.
 *
 * @package Ketunkoti\Blocks
 */

namespace Ketunkoti\Blocks\Tests;

use function Ketunkoti\Blocks\calculate_calculator_results;
use function Ketunkoti\Blocks\get_calculator_defaults;
use function Ketunkoti\Blocks\get_calculator_home;
use function Ketunkoti\Blocks\parse_decimal;

// Only runnable through WP-CLI, never over HTTP.
if ( ! defined( 'WP_CLI' ) ) {
    exit;
}

// number_format_i18n() must use the Finnish decimal comma.
switch_to_locale( 'fi' );

$GLOBALS['ketunkoti_blocks_test_failures'] = 0;

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

    ++$GLOBALS['ketunkoti_blocks_test_failures'];
    echo 'FAIL ', $name, ': expected ', var_export( $expected, true ), ', got ', var_export( $actual, true ), PHP_EOL;
}

$dash  = '—';
$lahti = [
    'rent'            => 780.0,
    'maintenance'     => 151.30,
    'capital'         => 600.72,
    'selling_price'   => 26000.0,
    'debt_free_price' => 138725.0,
    'interest_rate'   => 4.3,
];

$defaults = [
    'rent'            => '780',
    'years'           => '25',
    'rate'            => '3,85',
    'ownCapitalShare' => '30',
];

// parse_decimal().
check( 'decimal comma', 3.85, parse_decimal( '3,85' ) );
check( 'space as thousands separator', 6500.0, parse_decimal( '6 500' ) );
check( 'non-breaking space as thousands separator', 6500.0, parse_decimal( "6\u{00A0}500" ) );
check( 'empty', null, parse_decimal( '' ) );
check( 'not a number', null, parse_decimal( 'abc' ) );

// calculate_calculator_results().
check(
    'defaults',
    [
        'ownCapital'   => "41\u{00A0}618\u{00A0}€",
        'rentalYield'  => "5,4\u{00A0}%",
        'cashFlow'     => "+124\u{00A0}€/kk",
        'equityReturn' => "9,2\u{00A0}%",
        'notice'       => '',
    ],
    calculate_calculator_results( $lahti, $defaults )
);

check(
    'no bank loan pays off the company loan',
    [
        'ownCapital'   => "138\u{00A0}725\u{00A0}€",
        'rentalYield'  => "5,4\u{00A0}%",
        'cashFlow'     => "+629\u{00A0}€/kk",
        'equityReturn' => "5,4\u{00A0}%",
        'notice'       => '',
    ],
    calculate_calculator_results( $lahti, array_merge( $defaults, [ 'ownCapitalShare' => '100' ] ) )
);

check(
    'negative equity return uses a real minus sign',
    "\u{2212}0,4\u{00A0}%",
    calculate_calculator_results( $lahti, array_merge( $defaults, [ 'rent' => '100', 'ownCapitalShare' => '100' ] ) )['equityReturn']
);

$results = calculate_calculator_results( $lahti, array_merge( $defaults, [ 'rate' => '' ] ) );
check( 'missing rate cash flow', $dash, $results['cashFlow'] );
check( 'missing rate equity', $dash, $results['equityReturn'] );
check( 'notice for missing rate', 'Syötä korko ja laina-aika.', $results['notice'] );

$results = calculate_calculator_results( $lahti, array_merge( $defaults, [ 'years' => '0' ] ) );
check( 'missing term cash flow', $dash, $results['cashFlow'] );
check( 'missing term equity', $dash, $results['equityReturn'] );
check( 'notice for missing term', 'Syötä korko ja laina-aika.', $results['notice'] );

$results = calculate_calculator_results( $lahti, array_merge( $defaults, [ 'years' => '0.5' ] ) );
check( 'short term cash flow', $dash, $results['cashFlow'] );
check( 'short term equity', $dash, $results['equityReturn'] );
check( 'term under a year counts as missing', 'Syötä korko ja laina-aika.', $results['notice'] );

$results = calculate_calculator_results( $lahti, array_merge( $defaults, [ 'rate' => '0.00000000000001' ] ) );
check( 'tiny rate never shows infinity', 0, preg_match( '/∞|NaN|INF/', $results['cashFlow'] . $results['equityReturn'] ) );

$results = calculate_calculator_results( $lahti, array_merge( $defaults, [ 'ownCapitalShare' => '' ] ) );
check( 'missing own capital euros', $dash, $results['ownCapital'] );
check( 'missing own capital equity', $dash, $results['equityReturn'] );
check( 'notice for missing own capital', 'Syötä omarahoitusosuus.', $results['notice'] );

check(
    'notice for missing rent',
    [
        'ownCapital'   => "41\u{00A0}618\u{00A0}€",
        'rentalYield'  => $dash,
        'cashFlow'     => $dash,
        'equityReturn' => $dash,
        'notice'       => 'Syötä vuokra.',
    ],
    calculate_calculator_results( $lahti, array_merge( $defaults, [ 'rent' => '' ] ) )
);

$results = calculate_calculator_results( array_merge( $lahti, [ 'interest_rate' => null ] ), $defaults );
check( 'company rate not needed cash flow', "+124\u{00A0}€/kk", $results['cashFlow'] );
check( 'company rate not needed equity', "9,2\u{00A0}%", $results['equityReturn'] );
check( 'company rate not needed notice', '', $results['notice'] );

// get_calculator_defaults(). Euribor is pinned through the transient.
set_transient( 'ketunkoti_euribor_12m', 3.247, MINUTE_IN_SECONDS );
$home_defaults = get_calculator_defaults( $lahti );
check( 'default own capital share', '30', $home_defaults['ownCapitalShare'] );
check( 'default rent', '780', $home_defaults['rent'] );
check( 'default years', '25', $home_defaults['years'] );
check( 'default rate', '3.8', $home_defaults['rate'] );
check( 'default decimal rent', '151.3', get_calculator_defaults( array_merge( $lahti, [ 'rent' => 151.3 ] ) )['rent'] );

set_transient( 'ketunkoti_euribor_12m', 'none', MINUTE_IN_SECONDS );
check( 'default rate without euribor uses fallback', '3.6', get_calculator_defaults( $lahti )['rate'] );
delete_transient( 'ketunkoti_euribor_12m' );

// get_calculator_home().
$post_id = wp_insert_post(
    [
        'post_type'   => 'home',
        'post_status' => 'draft',
        'post_title'  => 'Yield calculator test',
    ]
);
update_post_meta( $post_id, 'home_rent', 700 );
check( 'home without selling price', null, get_calculator_home( $post_id ) );
update_post_meta( $post_id, 'home_selling_price', 90000 );
check( 'home with rent and selling price', 90000.0, get_calculator_home( $post_id )['selling_price'] ?? null );
wp_delete_post( $post_id, true );

if ( $GLOBALS['ketunkoti_blocks_test_failures'] > 0 ) {
    echo $GLOBALS['ketunkoti_blocks_test_failures'], ' check(s) failed.', PHP_EOL;
    exit( 1 );
}

echo 'All checks passed.', PHP_EOL;
