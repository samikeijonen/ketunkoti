/**
 * Number helpers shared by the calculator blocks.
 *
 * The JS twin of `includes/numbers.php`: parsing what Finnish visitors type,
 * and formatting results the same way as the PHP copy renders the first paint.
 */

import { formatPercentage } from '../../../ketunkoti-features/src/investment';

/**
 * Shown in place of a result that cannot be calculated, as the listing does.
 */
export const DASH = '—';

/**
 * Parses a number the way Finnish visitors type it.
 *
 * Spaces (including non-breaking ones) are thousands separators and a comma is
 * the decimal point: "6 500" is 6500 and "3,85" is 3.85.
 *
 * @param {string} value Typed value.
 * @return {number|undefined} The number, or undefined when empty or not a number.
 */
export const parseDecimal = ( value ) => {
	const normalised = String( value ?? '' )
		.replace( /[\s\u00a0\u202f]/g, '' )
		.replace( ',', '.' );

	if ( '' === normalised ) {
		return undefined;
	}

	const number = Number( normalised );

	return Number.isFinite( number ) ? number : undefined;
};

/**
 * Drops a value that is not a finite number, so Infinity never reaches the screen.
 *
 * A vanishingly small rate can round the annuity's denominator to zero.
 *
 * @param {number|undefined} value Calculated value.
 * @return {number|undefined} The value, or undefined when not finite.
 */
export const finiteOrUndefined = ( value ) =>
	Number.isFinite( value ) ? value : undefined;

/**
 * Formats a percentage with a real minus sign for negative values.
 *
 * @param {number|undefined}                             value        Percentage, or undefined.
 * @param {(value: number, decimals?: number) => string} formatNumber Locale number formatter.
 * @return {string} Formatted percentage, or a dash.
 */
export const formatPercentageOrDash = ( value, formatNumber ) =>
	undefined === value
		? DASH
		: formatPercentage( value, formatNumber ).replace( '-', '−' );

/**
 * Formats a sum in euros, or a dash when there is none.
 *
 * @param {number|undefined}                             value        Euros.
 * @param {(value: number, decimals?: number) => string} formatNumber Locale number formatter.
 * @param {number}                                       decimals     Decimal places to show.
 * @param {string}                                       suffix       Unit after the euro sign, for example "/kk".
 * @return {string} For example "27 000 €" or "22,69 €/m²/kk".
 */
export const formatEuros = (
	value,
	formatNumber,
	decimals = 0,
	suffix = ''
) =>
	undefined === finiteOrUndefined( value )
		? DASH
		: `${ formatNumber( value, decimals ) }\u00a0€${ suffix }`.replace(
				'-',
				'\u2212'
		  );
