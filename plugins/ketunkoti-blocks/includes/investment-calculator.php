<?php
/**
 * Investment calculator layer: the investor's typed fields in, display strings out.
 *
 * The PHP twin of `src/blocks/investment-calculator/calculator.js`. This copy
 * renders the block's first paint and the JS copy updates it live, so both must
 * produce identical strings (`tests/php/test-investment-calculator.php` and
 * `calculator.test.js` share their cases). The loan maths lives in
 * `ketunkoti-features` (calculate_first_year_loan()).
 *
 * The whole velaton hinta is financed with omarahoitus and a bank loan; there
 * is no taloyhtiölaina and no pääomavastike.
 *
 * @package Ketunkoti\Blocks
 */

namespace Ketunkoti\Blocks;

use function Ketunkoti\calculate_first_year_loan;
use function Ketunkoti\format_cash_flow;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Rate rises, in percentage points, shown in the interest rate table.
 */
const INVESTMENT_RATE_SHOCKS = [ 0, 1, 2, 4 ];

/**
 * Vacant months per year shown in the vacancy table.
 */
const INVESTMENT_VACANCY_ROWS = [ 0, 1, 2, 3 ];

/**
 * Returns the example values the calculator opens with.
 *
 * Strings with dot decimals, as `type="number"` fields need them.
 *
 * @return array<string, string> Field name => value.
 */
function get_investment_defaults(): array {
    return [
        'debtFreePrice'        => '90000',
        'rent'                 => '590',
        'area'                 => '26',
        'maintenance'          => '104',
        'ownCapitalShare'      => '30',
        'rate'                 => format_field_number( get_default_interest_rate() ),
        'years'                => '25',
        'method'               => 'annuity',
        'vacantMonths'         => '0',
        'taxRate'              => '30',
        'transferTaxRate'      => '1.5',
        'renovation'           => '0',
    ];
}

/**
 * Field name => URL parameter. Keep in sync with QUERY_KEYS in `url.js`.
 */
const INVESTMENT_QUERY_KEYS = [
    'debtFreePrice'        => 'hinta',
    'rent'                 => 'vuokra',
    'area'                 => 'ala',
    'maintenance'          => 'vastike',
    'vacantMonths'         => 'tyhjat',
    'ownCapitalShare'      => 'osuus',
    'rate'                 => 'korko',
    'years'                => 'aika',
    'method'               => 'tapa',
    'transferTaxRate'      => 'vsv',
    'renovation'           => 'remontti',
    'taxRate'              => 'vero',
];

/**
 * Finnish URL value => lyhennystapa. Keep in sync with METHOD_QUERY_VALUES in `url.js`.
 */
const INVESTMENT_QUERY_METHODS = [
    'annuiteetti'  => 'annuity',
    'tasaera'      => 'fixed',
    'tasalyhennys' => 'straight',
];

/**
 * Returns the calculator's fields from a shared link's URL parameters.
 *
 * The PHP twin of toQueryParams() in `url.js`. Starts from the defaults and
 * takes every parameter that is valid: a number (comma or dot decimal) or an
 * empty value for the number fields, one of the three Finnish lyhennystapa
 * names for `tapa`. Anything else keeps the default, so a mangled link still
 * shows a working calculator.
 *
 * @param array $query URL parameters, unslashed, for example from $_GET.
 * @return array<string, string> Field name => value, as get_investment_defaults().
 */
function get_investment_fields_from_query( array $query ): array {
    $fields = get_investment_defaults();

    foreach ( INVESTMENT_QUERY_KEYS as $field => $param ) {
        if ( ! isset( $query[ $param ] ) || ! is_string( $query[ $param ] ) ) {
            continue;
        }

        $value = trim( $query[ $param ] );

        if ( 'method' === $field ) {
            $fields['method'] = INVESTMENT_QUERY_METHODS[ $value ] ?? $fields['method'];
            continue;
        }

        if ( '' === $value ) {
            $fields[ $field ] = '';
            continue;
        }

        $number = parse_decimal( $value );

        if ( null !== $number && is_finite( $number ) ) {
            $fields[ $field ] = format_field_number( $number );
        }
    }

    return $fields;
}

/**
 * Returns the translated notices shown under the results, and the copy link
 * feedback.
 *
 * Passed to the JS twin too, so both show the same translated text.
 *
 * @return array<string, string> Notice key => text.
 */
function get_investment_messages(): array {
    return [
        'debtFreePrice'   => __( 'Syötä velaton hinta.', 'ketunkoti-blocks' ),
        'rent'            => __( 'Syötä vuokra.', 'ketunkoti-blocks' ),
        'area'            => __( 'Syötä pinta-ala.', 'ketunkoti-blocks' ),
        'ownCapitalShare' => __( 'Syötä omarahoitusosuus.', 'ketunkoti-blocks' ),
        'loan'            => __( 'Syötä korko ja laina-aika.', 'ketunkoti-blocks' ),
        'ownMoney'        => __( 'Oman pääoman tuottoa ei voi laskea ilman omaa pääomaa.', 'ketunkoti-blocks' ),
        'fixedTerm'       => __( 'Kiinteässä tasaerässä erä pysyy samana, vaikka korko nousee. Kassavirta voi silloin jopa parantua, koska korkoa vähennetään verotuksessa enemmän, mutta laina lyhenee hitaammin ja laina-aika pitenee.', 'ketunkoti-blocks' ),
        'linkCopied'      => __( 'Linkki kopioitu.', 'ketunkoti-blocks' ),
        'linkCopyFailed'  => __( 'Kopiointi ei onnistunut. Kopioi linkki osoiteriviltä.', 'ketunkoti-blocks' ),
    ];
}

/**
 * Formats a monthly cash flow, or a dash.
 *
 * @param float|null $value Euros per month.
 * @return string Formatted cash flow.
 */
function format_cash_flow_or_dash( ?float $value ): string {
    $value = finite_or_null( $value );

    return null === $value ? CALCULATOR_DASH : normalise_formatted_number( format_cash_flow( $value ) );
}

/**
 * Calculates every result and a notice from the typed fields.
 *
 * @param array<string, string> $fields Typed values, keys as in get_investment_defaults().
 * @return array<string, string|string[]> Display strings: financing, results, ratios, rateTable, vacancyTable and notice.
 */
function calculate_investment_results( array $fields ): array {
    $field = static fn( string $key ): ?float => parse_decimal( (string) ( $fields[ $key ] ?? '' ) );

    $debt_free_price = $field( 'debtFreePrice' );
    $rent            = $field( 'rent' );
    $area            = $field( 'area' );
    $typed_share     = $field( 'ownCapitalShare' );
    $messages        = get_investment_messages();

    $missing = null;

    if ( ! ( $debt_free_price > 0 ) ) {
        $missing = 'debtFreePrice';
    } elseif ( ! ( $rent > 0 ) ) {
        $missing = 'rent';
    } elseif ( ! ( $area > 0 ) ) {
        $missing = 'area';
    } elseif ( null === $typed_share ) {
        $missing = 'ownCapitalShare';
    }

    if ( null !== $missing ) {
        return [
            'ownCapital'           => CALCULATOR_DASH,
            'bankLoan'             => CALCULATOR_DASH,
            'monthlyPayment'       => CALCULATOR_DASH,
            'rentalYield'          => CALCULATOR_DASH,
            'cashFlowBeforeTax'    => CALCULATOR_DASH,
            'cashFlowAfterTax'     => CALCULATOR_DASH,
            'equityReturn'         => CALCULATOR_DASH,
            'pricePerSquare'       => CALCULATOR_DASH,
            'rentPerSquare'        => CALCULATOR_DASH,
            'maintenancePerSquare' => CALCULATOR_DASH,
            'rateTable'            => array_fill( 0, count( INVESTMENT_RATE_SHOCKS ), CALCULATOR_DASH ),
            'vacancyTable'         => array_fill( 0, count( INVESTMENT_VACANCY_ROWS ), CALCULATOR_DASH ),
            'notice'               => $messages[ $missing ],
        ];
    }

    $share             = min( 100.0, max( 0.0, $typed_share ) );
    $maintenance       = max( 0.0, $field( 'maintenance' ) ?? 0.0 );
    $vacant_months     = min( 12.0, max( 0.0, $field( 'vacantMonths' ) ?? 0.0 ) );
    $tax_rate          = min( 100.0, max( 0.0, $field( 'taxRate' ) ?? 0.0 ) );
    $transfer_tax_rate = min( 100.0, max( 0.0, $field( 'transferTaxRate' ) ?? 0.0 ) );
    $renovation        = max( 0.0, $field( 'renovation' ) ?? 0.0 );
    $rate              = $field( 'rate' );
    $years             = $field( 'years' ) ?? 0.0;
    $method            = (string) ( $fields['method'] ?? '' );

    $transfer_tax = $debt_free_price * $transfer_tax_rate / 100;
    $own_capital  = $debt_free_price * $share / 100;
    $bank_loan    = max( 0.0, $debt_free_price - $own_capital );
    $own_money    = $own_capital + $transfer_tax + $renovation;
    $total_cost   = $debt_free_price + $transfer_tax + $renovation;

    // Yearly rent after vacancy, minus hoitovastike.
    $net_income = static fn( float $months ): float => $rent * ( 12 - $months ) - $maintenance * 12;
    $income     = $net_income( $vacant_months );

    // Monthly cash flow after tax with a given rate rise, or null without a loan rate.
    $cash_flow_after_tax_at = static function ( float $shock ) use ( $bank_loan, $rate, $years, $method, $income, $tax_rate ): ?float {
        $year = calculate_first_year_loan( $bank_loan, null === $rate ? null : $rate + $shock, $years, $method, $rate );

        if ( null === $year ) {
            return null;
        }

        $tax = max( 0.0, $income - $year['interest'] ) * $tax_rate / 100;

        return finite_or_null( ( $income - $year['payments'] - $tax ) / 12 );
    };

    $year                 = calculate_first_year_loan( $bank_loan, $rate, $years, $method, $rate );
    $cash_flow_before_tax = null === $year ? null : ( $income - $year['payments'] ) / 12;
    $equity_return        = null === $year || ! ( $own_money > 0 ) ? null : ( $income - $year['interest'] ) / $own_money * 100;

    $notice = '';

    if ( null === $year ) {
        $notice = $messages['loan'];
    } elseif ( ! ( $own_money > 0 ) ) {
        $notice = $messages['ownMoney'];
    } elseif ( 'fixed' === $method ) {
        $notice = $messages['fixedTerm'];
    }

    return [
        'ownCapital'           => format_euros( $own_capital ),
        'bankLoan'             => format_euros( $bank_loan ),
        'monthlyPayment'       => null === $year ? CALCULATOR_DASH : format_euros( $year['payments'] / 12, 0, '/kk' ),
        'rentalYield'          => format_percentage_or_dash( finite_or_null( $income / $total_cost * 100 ) ),
        'cashFlowBeforeTax'    => format_cash_flow_or_dash( $cash_flow_before_tax ),
        'cashFlowAfterTax'     => format_cash_flow_or_dash( $cash_flow_after_tax_at( 0.0 ) ),
        'equityReturn'         => format_percentage_or_dash( finite_or_null( $equity_return ) ),
        'pricePerSquare'       => format_euros( $debt_free_price / $area, 0, '/m²' ),
        'rentPerSquare'        => format_euros( $rent / $area, 2, '/m²/kk' ),
        'maintenancePerSquare' => format_euros( $maintenance / $area, 2, '/m²/kk' ),
        'rateTable'            => array_map(
            static fn( int $shock ): string => format_cash_flow_or_dash( $cash_flow_after_tax_at( (float) $shock ) ),
            INVESTMENT_RATE_SHOCKS
        ),
        'vacancyTable'         => array_map(
            static fn( int $months ): string => format_percentage_or_dash( finite_or_null( $net_income( (float) $months ) / $total_cost * 100 ) ),
            INVESTMENT_VACANCY_ROWS
        ),
        'notice'               => $notice,
    ];
}
