<?php
/**
 * Yield calculator layer: the visitor's typed fields in, display strings out.
 *
 * The PHP twin of `src/blocks/yield-calculator/calculator.js`. This copy
 * renders the block's first paint and the JS copy updates it live, so both
 * must produce identical strings (`tests/php/test-yield-calculator.php` and
 * `calculator.test.js` share their cases). The formulas live in
 * `ketunkoti-features` (`includes/investment.php`) and are shared with the
 * listing's own figures.
 *
 * @package Ketunkoti\Blocks
 */

namespace Ketunkoti\Blocks;

use function Ketunkoti\calculate_annuity_payment;
use function Ketunkoti\calculate_leveraged_cash_flow;
use function Ketunkoti\calculate_leveraged_equity_return;
use function Ketunkoti\calculate_rental_yield;
use function Ketunkoti\format_cash_flow;
use function Ketunkoti\get_investment_inputs;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Default omarahoitusosuus, percent of the myyntihinta.
 */
const DEFAULT_OWN_CAPITAL_SHARE = 30;

/**
 * Default bank loan term in years.
 */
const DEFAULT_LOAN_YEARS = 25;

/**
 * Returns the translated notices shown under the results.
 *
 * Passed to the JS twin too, so both show the same translated text.
 *
 * @return array{rent: string, ownCapitalShare: string, loan: string}
 */
function get_calculator_messages(): array {
    return [
        'rent'            => __( 'Syötä vuokra.', 'ketunkoti-blocks' ),
        'ownCapitalShare' => __( 'Syötä omarahoitusosuus.', 'ketunkoti-blocks' ),
        'loan'            => __( 'Syötä korko ja laina-aika.', 'ketunkoti-blocks' ),
    ];
}

/**
 * Returns a home's figures for the calculator.
 *
 * @param int $post_id Home post ID.
 * @return array{rent: float, maintenance: float, capital: float, selling_price: float, debt_free_price: float, interest_rate: float|null}|null
 *         The figures, or null when the home has no rent or no selling price.
 */
function get_calculator_home( int $post_id ): ?array {
    $home = get_investment_inputs( $post_id );

    return $home['rent'] > 0 && $home['selling_price'] > 0 ? $home : null;
}

/**
 * Returns the prefilled values of the visitor's fields.
 *
 * @param array $home Figures from get_calculator_home().
 * @return array{rent: string, years: string, rate: string, ownCapitalShare: string}
 */
function get_calculator_defaults( array $home ): array {
    return [
        'rent'            => format_field_number( (float) $home['rent'] ),
        'years'           => (string) DEFAULT_LOAN_YEARS,
        'rate'            => format_field_number( get_default_interest_rate() ),
        'ownCapitalShare' => (string) DEFAULT_OWN_CAPITAL_SHARE,
    ];
}

/**
 * Calculates the three results and a notice from the home and the typed fields.
 *
 * @param array $home   Figures from get_calculator_home().
 * @param array $fields Typed values: rent, years, rate, ownCapitalShare (strings, share in % of myyntihinta).
 * @return array{ownCapital: string, rentalYield: string, cashFlow: string, equityReturn: string, notice: string}
 */
function calculate_calculator_results( array $home, array $fields ): array {
    $rent        = parse_decimal( (string) ( $fields['rent'] ?? '' ) ) ?? 0.0;
    $years       = parse_decimal( (string) ( $fields['years'] ?? '' ) ) ?? 0.0;
    // The first year is simulated month by month, so a shorter term is not supported.
    $years = $years >= 1 ? $years : 0.0;
    $rate        = parse_decimal( (string) ( $fields['rate'] ?? '' ) );
    $maintenance = (float) $home['maintenance'];
    $debt_free   = (float) $home['debt_free_price'];

    /*
     * The buyer finances the whole debt-free price with omarahoitus and a bank
     * loan, which pays off the flat's taloyhtiölaina share: no pääomavastike
     * and no taloyhtiö interest remain. Without a debt-free price, the selling
     * price is the whole price.
     */
    $purchase_price = $debt_free > 0 ? $debt_free : (float) $home['selling_price'];
    $own_capital    = $purchase_price * ( parse_decimal( (string) ( $fields['ownCapitalShare'] ?? '' ) ) ?? 0.0 ) / 100;

    $bank_loan = max( 0.0, $purchase_price - $own_capital );
    $payment   = finite_or_null( calculate_annuity_payment( $bank_loan, $rate, $years ) );

    $rental_yield = calculate_rental_yield( $rent, $maintenance, $debt_free );

    $cash_flow = null === $payment ? null : calculate_leveraged_cash_flow( $rent, $maintenance, 0.0, $payment );

    $equity_return = calculate_leveraged_equity_return(
        [
            'rent'            => $rent,
            'maintenance'     => $maintenance,
            'capital'         => 0.0,
            'selling_price'   => $purchase_price,
            'debt_free_price' => $purchase_price,
            'interest_rate'   => null,
            'own_capital'     => $own_capital,
            'bank_rate'       => $rate,
            'years'           => $years,
        ]
    );

    $messages = get_calculator_messages();
    $notice   = '';

    if ( $rent <= 0 ) {
        $notice = $messages['rent'];
    } elseif ( $own_capital <= 0 ) {
        $notice = $messages['ownCapitalShare'];
    } elseif ( null === $payment ) {
        $notice = $messages['loan'];
    }

    return [
        'ownCapital'   => $own_capital > 0 ? normalise_formatted_number( number_format_i18n( $own_capital ) ) . "\u{00A0}€" : CALCULATOR_DASH,
        'rentalYield'  => format_percentage_or_dash( $rental_yield ),
        'cashFlow'     => null === finite_or_null( $cash_flow ) ? CALCULATOR_DASH : normalise_formatted_number( format_cash_flow( $cash_flow ) ),
        'equityReturn' => format_percentage_or_dash( finite_or_null( $equity_return ) ),
        'notice'       => $notice,
    ];
}
