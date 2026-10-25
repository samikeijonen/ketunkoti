<?php
/**
 * Investment calculations for sijoitusasunnot.
 *
 * Two figures on top of the existing vuokratuotto (see get_rental_yield() in
 * `bindings.php`):
 *
 * - Kassavirta, the monthly cash left to the investor before taxes:
 *   vuokra - hoitovastike - pääomavastike.
 * - Oman pääoman tuotto, the first year's return on the selling price, the
 *   investor's own capital: (nettovuokra * 12 - first year's interest) /
 *   myyntihinta. Only the interest part of pääomavastike is a cost; the
 *   repayment part reduces the flat's debt, so it counts as the investor's
 *   savings.
 *
 * The calculators take plain numbers and read no meta, so they can be tested
 * on their own (`tests/php/test-investment.php`). Keep in sync with the JS
 * twin, `src/investment.js`, which renders the live figures in the editor
 * sidebar. This file stays authoritative for the front end.
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Calculates the interest paid on the taloyhtiö loan share in the first year.
 *
 * Simulated month by month with the actual pääomavastike as the payment, on a
 * nominal monthly rate (annual rate / 12), which is how Finnish bank loans are
 * quoted. This needs no loan term and follows the housing company's real
 * charge rather than a theoretical annuity. If the payment is below the
 * monthly interest, the balance grows; that is real and needs no special case.
 *
 * @param float $loan                Loan share in euros, velaton - myyntihinta.
 * @param float $annual_rate_percent Total nominal annual rate, 4.3 for 4,3 %.
 * @param float $monthly_payment     Pääomavastike in euros per month.
 * @return float Interest paid over the first twelve months.
 */
function calculate_first_year_interest( float $loan, float $annual_rate_percent, float $monthly_payment ): float {
    $monthly_rate = $annual_rate_percent / 100 / 12;
    $balance      = $loan;
    $interest     = 0.0;

    for ( $month = 0; $month < 12; $month++ ) {
        $month_interest = $balance * $monthly_rate;
        $interest      += $month_interest;
        $balance       -= $monthly_payment - $month_interest;
    }

    return $interest;
}

/**
 * Calculates the monthly cash flow, the Finnish "kassavirta".
 *
 * A negative result is real and is returned as it stands.
 *
 * @param float $rent        Vuokra in euros per month.
 * @param float $maintenance Hoitovastike in euros per month, 0 when empty.
 * @param float $capital     Pääomavastike in euros per month, 0 when empty.
 * @return float|null Euros per month, or null without a rent.
 */
function calculate_cash_flow( float $rent, float $maintenance, float $capital ): ?float {
    if ( $rent <= 0 ) {
        return null;
    }

    return $rent - $maintenance - $capital;
}

/**
 * Calculates the first year's return on equity, the Finnish "oman pääoman tuotto".
 *
 *     (nettovuokra * 12 - first year's interest) / myyntihinta * 100
 *
 * The interest rate is only needed when there is a loan share and a
 * pääomavastike to pay it with. Without either there is no interest, and the
 * figure is computed without a rate.
 *
 * @param float      $rent                Vuokra in euros per month.
 * @param float      $maintenance         Hoitovastike in euros per month.
 * @param float      $capital             Pääomavastike in euros per month.
 * @param float      $selling_price       Myyntihinta in euros.
 * @param float      $debt_free_price     Velaton hinta in euros.
 * @param float|null $annual_rate_percent Taloyhtiölainan korko, 4.3 for 4,3 %, or null when not entered.
 * @return float|null Percentage, or null when the inputs are not enough.
 */
function calculate_equity_return( float $rent, float $maintenance, float $capital, float $selling_price, float $debt_free_price, ?float $annual_rate_percent ): ?float {
    if ( $rent <= 0 || $selling_price <= 0 ) {
        return null;
    }

    $loan     = $debt_free_price - $selling_price;
    $interest = 0.0;

    if ( $loan > 0 && $capital > 0 ) {
        // A loan is being paid but its rate is unknown: any figure would be a guess.
        if ( null === $annual_rate_percent ) {
            return null;
        }

        $interest = calculate_first_year_interest( $loan, $annual_rate_percent, $capital );
    }

    return ( ( $rent - $maintenance ) * 12 - $interest ) / $selling_price * 100;
}

/**
 * Formats a monthly cash flow for display.
 *
 * Whole euros, rounded half away from zero, always signed: "+28 €/kk",
 * "−304 €/kk" with a real minus sign, and "0 €/kk" for zero.
 *
 * @param float $cash_flow Euros per month.
 * @return string Formatted cash flow.
 */
function format_cash_flow( float $cash_flow ): string {
    $rounded = round( $cash_flow );
    $sign    = '';

    if ( $rounded > 0 ) {
        $sign = '+';
    } elseif ( $rounded < 0 ) {
        $sign = "\u{2212}";
    }

    // Non-breaking space so the number never wraps away from its unit.
    return $sign . number_format_i18n( abs( $rounded ), 0 ) . "\u{00A0}" . __( '€/kk', 'ketunkoti-features' );
}

/**
 * Formats a percentage with one decimal, as vuokratuotto is shown.
 *
 * @param float $value Percentage.
 * @return string Formatted percentage, for example "10,6 %".
 */
function format_percentage( float $value ): string {
    // Non-breaking space so the number never wraps away from its percent sign.
    return number_format_i18n( $value, 1 ) . "\u{00A0}%";
}

/**
 * Formats an interest rate with as few decimals as it needs, at most two.
 *
 * "4 %", "4,3 %", "4,25 %".
 *
 * @param float $rate Annual rate as a percentage.
 * @return string Formatted rate.
 */
function format_interest_rate( float $rate ): string {
    $decimals = 2;

    if ( abs( $rate - round( $rate ) ) < 0.000001 ) {
        $decimals = 0;
    } elseif ( abs( $rate * 10 - round( $rate * 10 ) ) < 0.000001 ) {
        $decimals = 1;
    }

    return number_format_i18n( $rate, $decimals ) . "\u{00A0}%";
}

/**
 * Turns a stored interest rate into a rate, or null when none was entered.
 *
 * The editor stores 0 for an empty number field, and saving any field writes
 * every registered key, so 0 is the normal "not entered" value rather than a
 * real 0 % loan. Reading it as 0 % would publish an inflated oman pääoman
 * tuotto with no stated assumption. Matches the JS sidebar and the
 * `home_loan_interest` binding, which both treat 0 as empty.
 *
 * @param mixed $raw Raw meta value.
 * @return float|null The rate, or null when empty or not above 0.
 */
function normalise_interest_rate( $raw ): ?float {
    $rate = (float) $raw;

    return $rate > 0 ? $rate : null;
}

/**
 * Reads the inputs for the investment calculations from a post's meta.
 *
 * Empty numeric fields read as 0, matching how get_rental_yield() treats an
 * empty hoitovastike. The interest rate is the exception: empty means "not
 * entered", which calculate_equity_return() needs to tell apart from a rate.
 *
 * @param int $post_id Post to read.
 * @return array{rent: float, maintenance: float, capital: float, selling_price: float, debt_free_price: float, interest_rate: float|null}
 */
function get_investment_inputs( int $post_id ): array {
    return [
        'rent'            => (float) get_post_meta( $post_id, 'home_rent', true ),
        'maintenance'     => (float) get_post_meta( $post_id, 'home_maintenance_charge', true ),
        'capital'         => (float) get_post_meta( $post_id, 'home_capital_charge', true ),
        'selling_price'   => (float) get_post_meta( $post_id, 'home_selling_price', true ),
        'debt_free_price' => (float) get_post_meta( $post_id, 'home_debt_free_price', true ),
        'interest_rate'   => normalise_interest_rate( get_post_meta( $post_id, 'home_loan_interest', true ) ),
    ];
}
