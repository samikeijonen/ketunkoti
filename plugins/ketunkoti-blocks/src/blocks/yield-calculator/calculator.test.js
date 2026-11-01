/**
 * Tests for the yield calculator layer.
 *
 * Mirror `tests/php/test-yield-calculator.php` one to one: the server renders
 * the first paint with the PHP twin and this file updates it live, so the
 * strings must be identical.
 */

import { describe, expect, test } from 'vitest';

import { calculateResults, parseDecimal } from './calculator';

/**
 * Finnish number formatter, the same as the one in `view.js`.
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

const lahti = {
	rent: 780,
	maintenance: 151.3,
	capital: 600.72,
	sellingPrice: 26000,
	debtFreePrice: 138725,
	interestRate: 4.3,
};

const defaults = {
	rent: '780',
	years: '25',
	rate: '3,85',
	ownCapitalShare: '30',
};

const DASH = '—';

describe( 'parseDecimal', () => {
	test( 'decimal comma', () => {
		expect( parseDecimal( '3,85' ) ).toBe( 3.85 );
	} );

	test( 'space as thousands separator', () => {
		expect( parseDecimal( '6 500' ) ).toBe( 6500 );
	} );

	test( 'non-breaking space as thousands separator', () => {
		expect( parseDecimal( '6 500' ) ).toBe( 6500 );
	} );

	test( 'empty', () => {
		expect( parseDecimal( '' ) ).toBeUndefined();
	} );

	test( 'not a number', () => {
		expect( parseDecimal( 'abc' ) ).toBeUndefined();
	} );
} );

describe( 'calculateResults', () => {
	test( 'defaults', () => {
		expect( calculateResults( lahti, defaults, formatNumber ) ).toEqual( {
			ownCapital: '41 618 €',
			rentalYield: '5,4 %',
			cashFlow: '+124 €/kk',
			equityReturn: '9,2 %',
			notice: '',
		} );
	} );

	test( 'no bank loan pays off the company loan', () => {
		expect(
			calculateResults(
				lahti,
				{ ...defaults, ownCapitalShare: '100' },
				formatNumber
			)
		).toEqual( {
			ownCapital: '138 725 €',
			rentalYield: '5,4 %',
			cashFlow: '+629 €/kk',
			equityReturn: '5,4 %',
			notice: '',
		} );
	} );

	test( 'negative equity return uses a real minus sign', () => {
		expect(
			calculateResults(
				lahti,
				{ ...defaults, rent: '100', ownCapitalShare: '100' },
				formatNumber
			).equityReturn
		).toBe( '−0,4 %' );
	} );

	test( 'notice for missing rate', () => {
		const results = calculateResults(
			lahti,
			{ ...defaults, rate: '' },
			formatNumber
		);

		expect( results.cashFlow ).toBe( DASH );
		expect( results.equityReturn ).toBe( DASH );
		expect( results.notice ).toBe( 'Syötä korko ja laina-aika.' );
	} );

	test( 'notice for missing term', () => {
		const results = calculateResults(
			lahti,
			{ ...defaults, years: '0' },
			formatNumber
		);

		expect( results.cashFlow ).toBe( DASH );
		expect( results.equityReturn ).toBe( DASH );
		expect( results.notice ).toBe( 'Syötä korko ja laina-aika.' );
	} );

	test( 'term under a year counts as missing', () => {
		const results = calculateResults(
			lahti,
			{ ...defaults, years: '0.5' },
			formatNumber
		);

		expect( results.cashFlow ).toBe( DASH );
		expect( results.equityReturn ).toBe( DASH );
		expect( results.notice ).toBe( 'Syötä korko ja laina-aika.' );
	} );

	test( 'tiny rate never shows infinity', () => {
		const results = calculateResults(
			lahti,
			{ ...defaults, rate: '0.00000000000001' },
			formatNumber
		);

		expect( results.cashFlow ).not.toMatch( /∞|NaN/ );
		expect( results.equityReturn ).not.toMatch( /∞|NaN/ );
	} );

	test( 'notice for missing own capital', () => {
		const results = calculateResults(
			lahti,
			{ ...defaults, ownCapitalShare: '' },
			formatNumber
		);

		expect( results.ownCapital ).toBe( DASH );
		expect( results.equityReturn ).toBe( DASH );
		expect( results.notice ).toBe( 'Syötä omarahoitusosuus.' );
	} );

	test( 'notice for missing rent', () => {
		const results = calculateResults(
			lahti,
			{ ...defaults, rent: '' },
			formatNumber
		);

		expect( results ).toEqual( {
			ownCapital: '41 618 €',
			rentalYield: DASH,
			cashFlow: DASH,
			equityReturn: DASH,
			notice: 'Syötä vuokra.',
		} );
	} );

	test( 'company loan rate is not needed', () => {
		const results = calculateResults(
			{ ...lahti, interestRate: undefined },
			defaults,
			formatNumber
		);

		expect( results.cashFlow ).toBe( '+124 €/kk' );
		expect( results.equityReturn ).toBe( '9,2 %' );
		expect( results.notice ).toBe( '' );
	} );
} );
