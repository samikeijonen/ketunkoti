/**
 * Tests for the investment calculations.
 *
 * Mirror `tests/php/test-investment.php` one to one, so the editor preview and
 * the front end can never drift apart unnoticed.
 */

import {
	calculateCashFlow,
	calculateEquityReturn,
	calculateFirstYearInterest,
	formatCashFlow,
	formatInterestRate,
} from './investment';

/**
 * Finnish number formatter, the same shape as `formatNumber()` in `index.js`.
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

describe( 'Lahti, worked by hand on 25.10.2026', () => {
	test( 'first year interest', () => {
		expect( calculateFirstYearInterest( 112725, 4.3, 600.72 ) ).toBeCloseTo(
			4800.07,
			2
		);
	} );

	test( 'cash flow', () => {
		expect( calculateCashFlow( 780, 151.3, 600.72 ) ).toBeCloseTo(
			27.98,
			2
		);
	} );

	test( 'equity return', () => {
		expect(
			calculateEquityReturn( {
				rent: 780,
				maintenance: 151.3,
				capital: 600.72,
				sellingPrice: 26000,
				debtFreePrice: 138725,
				annualRatePercent: 4.3,
			} )
		).toBeCloseTo( 10.56, 2 );
	} );

	test( 'cash flow format', () => {
		expect( formatCashFlow( 27.98, formatNumber ) ).toBe( '+28 €/kk' );
	} );
} );

describe( 'Finnoo kulmayksiö, post 358', () => {
	test( 'cash flow', () => {
		expect( calculateCashFlow( 675, 106.4, 872.9 ) ).toBeCloseTo(
			-304.3,
			2
		);
	} );

	test( 'cash flow format', () => {
		expect( formatCashFlow( -304.3, formatNumber ) ).toBe( '−304 €/kk' );
	} );

	test( 'equity return is undefined without korko', () => {
		expect(
			calculateEquityReturn( {
				rent: 675,
				maintenance: 106.4,
				capital: 872.9,
				sellingPrice: 22000,
				debtFreePrice: 166000,
				annualRatePercent: undefined,
			} )
		).toBeUndefined();
	} );
} );

describe( 'no loan', () => {
	const inputs = {
		rent: 800,
		maintenance: 200,
		capital: 0,
		sellingPrice: 100000,
		debtFreePrice: 100000,
	};

	test( 'equity return without korko', () => {
		expect(
			calculateEquityReturn( { ...inputs, annualRatePercent: undefined } )
		).toBeCloseTo( 7.2, 2 );
	} );

	test( 'equity return ignores korko', () => {
		expect(
			calculateEquityReturn( { ...inputs, annualRatePercent: 4.3 } )
		).toBeCloseTo( 7.2, 2 );
	} );

	test( 'cash flow', () => {
		expect( calculateCashFlow( 800, 200, 0 ) ).toBeCloseTo( 600, 2 );
	} );
} );

describe( 'missing inputs', () => {
	test( 'no rent: cash flow is undefined', () => {
		expect( calculateCashFlow( 0, 150, 600 ) ).toBeUndefined();
	} );

	test( 'no rent: equity return is undefined', () => {
		expect(
			calculateEquityReturn( {
				rent: 0,
				maintenance: 150,
				capital: 600,
				sellingPrice: 26000,
				debtFreePrice: 138725,
				annualRatePercent: 4.3,
			} )
		).toBeUndefined();
	} );

	test( 'no selling price: equity return is undefined', () => {
		expect(
			calculateEquityReturn( {
				rent: 780,
				maintenance: 151.3,
				capital: 600.72,
				sellingPrice: 0,
				debtFreePrice: 138725,
				annualRatePercent: 4.3,
			} )
		).toBeUndefined();
	} );
} );

describe( 'formats', () => {
	test( 'cash flow rounding to zero has no sign', () => {
		expect( formatCashFlow( 0.4, formatNumber ) ).toBe( '0 €/kk' );
	} );

	test( 'cash flow rounds half away from zero', () => {
		expect( formatCashFlow( -0.5, formatNumber ) ).toBe( '−1 €/kk' );
	} );

	test.each( [
		[ 4.3, '4,3 %' ],
		[ 4.25, '4,25 %' ],
		[ 4, '4 %' ],
	] )( 'interest rate %s', ( rate, expected ) => {
		expect( formatInterestRate( rate, formatNumber ) ).toBe( expected );
	} );
} );
