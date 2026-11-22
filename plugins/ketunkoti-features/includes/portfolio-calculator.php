<?php
/**
 * Calculations for Salkku, the private apartment portfolio in wp-admin.
 *
 * The calculators take plain field arrays (as stored in post meta) and call no
 * WordPress functions, so they can be tested on their own
 * (`tests/php/test-portfolio-calculator.php`).
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collateral rate in percent used when an apartment has none of its own.
 *
 * @var float
 */
const PORTFOLIO_DEFAULT_COLLATERAL_RATE = 75.0;

/**
 * Calculates the debts, net value and collateral figures of one apartment.
 *
 * - debts: company loan + bank loan.
 * - net_value: estimated value - debts.
 * - collateral_value: estimated value * collateral rate, 75 % when the rate
 *   is empty or not above 0.
 * - free_collateral: collateral value - debts - used collateral, never below
 *   0 (the owner's decision: over-used collateral shows as 0, not negative).
 *
 * Missing numbers count as 0 and string values from meta are cast to float.
 *
 * @param array<string, mixed> $fields Apartment fields keyed by `portfolio_*` meta keys.
 * @return array{debts: float, net_value: float, collateral_value: float, free_collateral: float}
 */
function calculate_portfolio_apartment( array $fields ): array {
	$value           = (float) ( $fields['portfolio_estimated_value'] ?? 0 );
	$company_loan    = (float) ( $fields['portfolio_company_loan'] ?? 0 );
	$bank_loan       = (float) ( $fields['portfolio_bank_loan'] ?? 0 );
	$used_collateral = (float) ( $fields['portfolio_used_collateral'] ?? 0 );
	$rate            = (float) ( $fields['portfolio_collateral_rate'] ?? 0 );

	if ( $rate <= 0 ) {
		$rate = PORTFOLIO_DEFAULT_COLLATERAL_RATE;
	}

	$debts            = $company_loan + $bank_loan;
	$collateral_value = $value * $rate / 100;

	return [
		'debts'            => $debts,
		'net_value'        => $value - $debts,
		'collateral_value' => $collateral_value,
		'free_collateral'  => max( 0.0, $collateral_value - $debts - $used_collateral ),
	];
}

/**
 * Sums the figures of a list of apartments.
 *
 * Leverage is debts / value, or null when the total value is 0.
 *
 * @param array<int, array<string, mixed>> $apartments List of apartment field arrays.
 * @return array{count: int, free_collateral: float, debts: float, net_worth: float, value: float, rent: float, charges: float, debt_free_price: float, leverage: float|null}
 */
function calculate_portfolio_totals( array $apartments ): array {
	$totals = [
		'count'           => count( $apartments ),
		'free_collateral' => 0.0,
		'debts'           => 0.0,
		'net_worth'       => 0.0,
		'value'           => 0.0,
		'rent'            => 0.0,
		'charges'         => 0.0,
		'debt_free_price' => 0.0,
		'leverage'        => null,
	];

	foreach ( $apartments as $fields ) {
		$apartment = calculate_portfolio_apartment( $fields );

		$totals['free_collateral'] += $apartment['free_collateral'];
		$totals['debts']           += $apartment['debts'];
		$totals['net_worth']       += $apartment['net_value'];
		$totals['value']           += (float) ( $fields['portfolio_estimated_value'] ?? 0 );
		$totals['rent']            += (float) ( $fields['portfolio_rent'] ?? 0 );
		$totals['charges']         += (float) ( $fields['portfolio_charges'] ?? 0 );
		$totals['debt_free_price'] += (float) ( $fields['portfolio_debt_free_price'] ?? 0 );
	}

	if ( $totals['value'] > 0 ) {
		$totals['leverage'] = $totals['debts'] / $totals['value'];
	}

	return $totals;
}

/**
 * Formats an amount as whole euros.
 *
 * Non-breaking spaces as the thousands separator and before the euro sign, and
 * a real minus sign (U+2212) for negatives: "1 234 567 €", "−25 €".
 *
 * @param float $amount Amount in euros.
 * @return string Formatted amount.
 */
function format_portfolio_euros( float $amount ): string {
	$rounded = round( $amount );
	$sign    = $rounded < 0 ? "\u{2212}" : '';

	return $sign . number_format( abs( $rounded ), 0, ',', "\u{00A0}" ) . "\u{00A0}€";
}

/**
 * Formats a ratio as a percentage with one decimal.
 *
 * @param float|null $ratio Ratio, 0.65 for 65 %, or null when unknown.
 * @return string Formatted percentage such as "65,3 %", or an en dash for null.
 */
function format_portfolio_percent( ?float $ratio ): string {
	if ( null === $ratio ) {
		return '–';
	}

	return number_format( $ratio * 100, 1, ',', '' ) . "\u{00A0}%";
}
