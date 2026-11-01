<?php
/**
 * Tests for the investment calculator layer.
 *
 * Run inside the wp-env CLI container:
 *
 *     wp eval-file wp-content/plugins/ketunkoti-blocks/tests/php/test-investment-calculator.php
 *
 * Mirrors `src/blocks/investment-calculator/calculator.test.js` one to one: the
 * server's first paint and the live JS updates must produce identical strings.
 * Values hand-checked on 2026-11-01.
 *
 * @package Ketunkoti\Blocks
 */

namespace Ketunkoti\Blocks\Tests;

use function Ketunkoti\Blocks\calculate_investment_results;
use function Ketunkoti\Blocks\get_investment_defaults;
use function Ketunkoti\Blocks\get_investment_fields_from_query;
use function Ketunkoti\Blocks\get_investment_messages;

// Only runnable through WP-CLI, never over HTTP.
if ( ! defined( 'WP_CLI' ) ) {
    exit;
}

// number_format_i18n() must use the Finnish separators.
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

$n        = "\u{00A0}";
$m        = "\u{2212}";
$dash     = '—';
$messages = get_investment_messages();
$defaults = [
    'debtFreePrice'        => '90000',
    'rent'                 => '590',
    'area'                 => '26',
    'maintenance'          => '104',
    'ownCapitalShare'      => '30',
    'rate'                 => '3.8',
    'years'                => '25',
    'method'               => 'annuity',
    'vacantMonths'         => '0',
    'taxRate'              => '30',
    'transferTaxRate'      => '1.5',
    'renovation'           => '0',
];

// wp eval-file runs this file inside a method, so run() reads the defaults
// from $GLOBALS rather than from a file-level variable.
$GLOBALS['ketunkoti_investment_test_defaults'] = $defaults;

/**
 * Runs the calculator with the defaults and some overrides.
 *
 * @param array $overrides Fields to change.
 * @return array Results.
 */
function run( array $overrides = [] ): array {
    return calculate_investment_results( array_merge( $GLOBALS['ketunkoti_investment_test_defaults'], $overrides ) );
}

check(
    'defaults annuity',
    [
        'ownCapital'           => "27{$n}000{$n}€",
        'bankLoan'             => "63{$n}000{$n}€",
        'monthlyPayment'       => "326{$n}€/kk",
        'rentalYield'          => "6,4{$n}%",
        'cashFlowBeforeTax'    => "+160{$n}€/kk",
        'cashFlowAfterTax'     => "+74{$n}€/kk",
        'equityReturn'         => "12,2{$n}%",
        'pricePerSquare'       => "3{$n}462{$n}€/m²",
        'rentPerSquare'        => "22,69{$n}€/m²/kk",
        'maintenancePerSquare' => "4,00{$n}€/m²/kk",
        'rateTable'            => [ "+74{$n}€/kk", "+54{$n}€/kk", "+33{$n}€/kk", "{$m}16{$n}€/kk" ],
        'vacancyTable'         => [ "6,4{$n}%", "5,7{$n}%", "5,1{$n}%", "4,4{$n}%" ],
        'notice'               => '',
    ],
    run()
);

$results = run( [ 'method' => 'fixed' ] );
check( 'fixed shock keeps payment', [ "+74{$n}€/kk", "+90{$n}€/kk", "+106{$n}€/kk", "+138{$n}€/kk" ], $results['rateTable'] );
check( 'fixed notice', $messages['fixedTerm'], $results['notice'] );
check( 'fixed notice explains the rising cash flow', true, str_contains( $messages['fixedTerm'], 'verotuksessa' ) );
check(
    'negative costs count as zero',
    run( [ 'taxRate' => '0', 'transferTaxRate' => '0', 'renovation' => '0', 'maintenance' => '0' ] ),
    run( [ 'taxRate' => '-30', 'transferTaxRate' => '-1.5', 'renovation' => '-91350', 'maintenance' => '-104' ] )
);

$results = run( [ 'method' => 'straight' ] );
check( 'straight payment', "406{$n}€/kk", $results['monthlyPayment'] );
check( 'straight before tax', "+80{$n}€/kk", $results['cashFlowBeforeTax'] );
check( 'straight after tax', "{$m}7{$n}€/kk", $results['cashFlowAfterTax'] );
check( 'straight equity', "12,3{$n}%", $results['equityReturn'] );
check( 'straight rate table', [ "{$m}7{$n}€/kk", "{$m}43{$n}€/kk", "{$m}79{$n}€/kk", "{$m}151{$n}€/kk" ], $results['rateTable'] );

$results = run( [ 'ownCapitalShare' => '100', 'rate' => '' ] );
check( 'share 100 bank loan', "0{$n}€", $results['bankLoan'] );
check( 'share 100 payment', "0{$n}€/kk", $results['monthlyPayment'] );
check( 'share 100 before tax', "+486{$n}€/kk", $results['cashFlowBeforeTax'] );
check( 'share 100 after tax', "+340{$n}€/kk", $results['cashFlowAfterTax'] );
check( 'share 100 equity', "6,4{$n}%", $results['equityReturn'] );
check( 'share 100 notice', '', $results['notice'] );

$results = run( [ 'ownCapitalShare' => '0' ] );
check( 'share zero own capital', "0{$n}€", $results['ownCapital'] );
check( 'share zero bank loan', "90{$n}000{$n}€", $results['bankLoan'] );
check( 'share zero equity', "181,5{$n}%", $results['equityReturn'] );
check( 'share zero notice', '', $results['notice'] );

$results = run( [ 'ownCapitalShare' => '0', 'transferTaxRate' => '0', 'renovation' => '0' ] );
check( 'no own money equity', $dash, $results['equityReturn'] );
check( 'no own money cash flow still shown', true, $dash !== $results['cashFlowAfterTax'] );
check( 'no own money notice', $messages['ownMoney'], $results['notice'] );

$results = run( [ 'vacantMonths' => '12' ] );
check( 'vacancy twelve before tax', "{$m}430{$n}€/kk", $results['cashFlowBeforeTax'] );
check( 'vacancy twelve after tax', "{$m}430{$n}€/kk", $results['cashFlowAfterTax'] );
check( 'vacancy clamped', $results, run( [ 'vacantMonths' => '15' ] ) );

$results = run( [ 'debtFreePrice' => '' ] );
check( 'missing price own capital', $dash, $results['ownCapital'] );
check( 'missing price cash flow', $dash, $results['cashFlowAfterTax'] );
check( 'missing price rate table', [ $dash, $dash, $dash, $dash ], $results['rateTable'] );
check( 'missing price vacancy table', [ $dash, $dash, $dash, $dash ], $results['vacancyTable'] );
check( 'missing price notice', $messages['debtFreePrice'], $results['notice'] );

check( 'missing rent', $messages['rent'], run( [ 'rent' => '' ] )['notice'] );

check( 'maintenance per square follows area', "3,90{$n}€/m²/kk", run( [ 'area' => '31.5', 'maintenance' => '122.85' ] )['maintenancePerSquare'] );

$results = run( [ 'area' => '0' ] );
check( 'zero area price per square', $dash, $results['pricePerSquare'] );
check( 'zero area notice', $messages['area'], $results['notice'] );

check( 'missing share', $messages['ownCapitalShare'], run( [ 'ownCapitalShare' => '' ] )['notice'] );

$results = run( [ 'rate' => '' ] );
check( 'missing rate own capital', "27{$n}000{$n}€", $results['ownCapital'] );
check( 'missing rate payment', $dash, $results['monthlyPayment'] );
check( 'missing rate before tax', $dash, $results['cashFlowBeforeTax'] );
check( 'missing rate after tax', $dash, $results['cashFlowAfterTax'] );
check( 'missing rate equity', $dash, $results['equityReturn'] );
check( 'missing rate table', [ $dash, $dash, $dash, $dash ], $results['rateTable'] );
check( 'missing rate vacancy', "6,4{$n}%", $results['vacancyTable'][0] );
check( 'missing rate notice', $messages['loan'], $results['notice'] );

check( 'short term', $messages['loan'], run( [ 'years' => '0.5' ] )['notice'] );

// Defaults. Euribor is pinned through the transient.
set_transient( 'ketunkoti_euribor_12m', 3.247, MINUTE_IN_SECONDS );
// Copy link feedback.
check( 'link copied message', 'Linkki kopioitu.', $messages['linkCopied'] ?? null );
check( 'link copy failed message', 'Kopiointi ei onnistunut. Kopioi linkki osoiteriviltä.', $messages['linkCopyFailed'] ?? null );

// Shareable URL parameters, the same names as `url.js` writes.
$from_query = get_investment_fields_from_query(
    [
        'hinta'    => '120000',
        'vuokra'   => '700',
        'ala'      => '31.5',
        'vastike'  => '122,85',
        'tyhjat'   => '1',
        'osuus'    => '0',
        'korko'    => '4.2',
        'aika'     => '20',
        'tapa'     => 'tasalyhennys',
        'vsv'      => '1.5',
        'remontti' => '2000',
        'vero'     => '34',
    ]
);
check( 'query price', '120000', $from_query['debtFreePrice'] );
check( 'query decimal area', '31.5', $from_query['area'] );
check( 'query comma decimal', '122.85', $from_query['maintenance'] );
check( 'query zero share kept', '0', $from_query['ownCapitalShare'] );
check( 'query rate', '4.2', $from_query['rate'] );
check( 'query method', 'straight', $from_query['method'] );
check( 'query fixed method', 'fixed', get_investment_fields_from_query( [ 'tapa' => 'tasaera' ] )['method'] );
check( 'query unknown method uses default', 'annuity', get_investment_fields_from_query( [ 'tapa' => 'bullet' ] )['method'] );
check( 'query emptied field stays empty', '', get_investment_fields_from_query( [ 'vuokra' => '' ] )['rent'] );
check( 'query invalid number uses default', '590', get_investment_fields_from_query( [ 'vuokra' => '<script>' ] )['rent'] );
check( 'query array value uses default', '590', get_investment_fields_from_query( [ 'vuokra' => [ '1' ] ] )['rent'] );
check( 'query missing uses default', '26', get_investment_fields_from_query( [ 'hinta' => '100000' ] )['area'] );
check( 'no query gives defaults', get_investment_defaults(), get_investment_fields_from_query( [] ) );

check( 'default rate', '3.8', get_investment_defaults()['rate'] );
check( 'default method', 'annuity', get_investment_defaults()['method'] );
check( 'defaults give the hand-checked results', run(), calculate_investment_results( get_investment_defaults() ) );
set_transient( 'ketunkoti_euribor_12m', 'none', MINUTE_IN_SECONDS );
check( 'default rate without euribor uses fallback', '3.6', get_investment_defaults()['rate'] );
delete_transient( 'ketunkoti_euribor_12m' );

if ( $GLOBALS['ketunkoti_blocks_test_failures'] > 0 ) {
    echo $GLOBALS['ketunkoti_blocks_test_failures'], ' check(s) failed.', PHP_EOL;
    exit( 1 );
}

echo 'All checks passed.', PHP_EOL;
