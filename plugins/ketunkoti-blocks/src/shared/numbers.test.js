/**
 * Tests for the shared number helpers.
 *
 * Mirror `tests/php/test-numbers.php` one to one.
 */

import { describe, expect, test } from 'vitest';

import { formatEuros } from './numbers';

/**
 * Finnish number formatter, the same as the ones in the blocks' `view.js`.
 *
 * @param {number} value    Number to format.
 * @param {number} decimals Decimal places to show.
 * @return {string} Formatted number.
 */
const formatNumber = ( value, decimals = 0 ) =>
	new Intl.NumberFormat( 'fi', {
		minimumFractionDigits: decimals,
		maximumFractionDigits: decimals,
	} ).format( value );

describe( 'formatEuros', () => {
	test( 'whole euros', () => {
		expect( formatEuros( 27000, formatNumber ) ).toBe( '27 000 €' );
	} );

	test( 'per square metre and month', () => {
		expect( formatEuros( 22.6923, formatNumber, 2, '/m²/kk' ) ).toBe(
			'22,69 €/m²/kk'
		);
	} );

	test( 'per square metre', () => {
		expect( formatEuros( 3461.54, formatNumber, 0, '/m²' ) ).toBe(
			'3 462 €/m²'
		);
	} );

	test( 'missing', () => {
		expect( formatEuros( undefined, formatNumber ) ).toBe( '—' );
	} );

	test( 'not finite', () => {
		expect( formatEuros( Infinity, formatNumber ) ).toBe( '—' );
	} );
} );
