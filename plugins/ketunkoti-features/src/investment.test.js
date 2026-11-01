/**
 * Tests for the investment calculations.
 *
 * Mirror `tests/php/test-investment.php` one to one, so the editor preview and
 * the front end can never drift apart unnoticed.
 */

import {
	calculateAnnuityPayment,
	calculateCashFlow,
	calculateEquityReturn,
	calculateFirstYearInterest,
	calculateFirstYearLoan,
	calculateLeveragedCashFlow,
	calculateLeveragedEquityReturn,
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

describe( 'Bank loan, 2026-11-01', () => {
	const lahti = {
		rent: 780,
		maintenance: 151.3,
		capital: 600.72,
		sellingPrice: 26000,
		debtFreePrice: 138725,
		annualRatePercent: 4.3,
		ownCapital: 6500,
		bankRate: 3.85,
		years: 25,
	};

	test( 'annuity 100k', () => {
		expect( calculateAnnuityPayment( 100000, 3.85, 25 ) ).toBeCloseTo(
			519.59,
			2
		);
	} );

	test( 'annuity zero rate', () => {
		expect( calculateAnnuityPayment( 120000, 0, 25 ) ).toBeCloseTo(
			400,
			2
		);
	} );

	test( 'annuity vanishing rate', () => {
		expect(
			calculateAnnuityPayment( 120000, 0.00000000000001, 25 )
		).toBeCloseTo( 400, 2 );
	} );

	test( 'annuity no loan', () => {
		expect( calculateAnnuityPayment( 0, 3.85, 25 ) ).toBe( 0 );
	} );

	test( 'annuity zero years', () => {
		expect( calculateAnnuityPayment( 19500, 3.85, 0 ) ).toBeUndefined();
	} );

	test( 'annuity no rate', () => {
		expect(
			calculateAnnuityPayment( 19500, undefined, 25 )
		).toBeUndefined();
	} );

	test( 'lahti bank payment', () => {
		expect( calculateAnnuityPayment( 19500, 3.85, 25 ) ).toBeCloseTo(
			101.32,
			2
		);
	} );

	test( 'lahti leveraged cash flow', () => {
		expect(
			calculateLeveragedCashFlow( 780, 151.3, 600.72, 101.32 )
		).toBeCloseTo( -73.34, 2 );
	} );

	test( 'leveraged cash flow no rent', () => {
		expect(
			calculateLeveragedCashFlow( 0, 151.3, 600.72, 101.32 )
		).toBeUndefined();
	} );

	test( 'lahti leveraged equity', () => {
		expect( calculateLeveragedEquityReturn( lahti ) ).toBeCloseTo(
			30.8,
			2
		);
	} );

	test( 'no bank loan equals listing', () => {
		expect(
			calculateLeveragedEquityReturn( {
				...lahti,
				ownCapital: 26000,
				bankRate: undefined,
			} )
		).toBeCloseTo( 10.56, 2 );
	} );

	test( 'overfunded', () => {
		const interest = calculateFirstYearInterest( 112725, 4.3, 600.72 );
		expect(
			calculateLeveragedEquityReturn( { ...lahti, ownCapital: 52000 } )
		).toBeCloseTo(
			( ( ( 780 - 151.3 ) * 12 - interest ) / 52000 ) * 100,
			2
		);
	} );

	test( 'leveraged equity no own capital', () => {
		expect(
			calculateLeveragedEquityReturn( { ...lahti, ownCapital: 0 } )
		).toBeUndefined();
	} );

	test( 'leveraged equity no bank rate', () => {
		expect(
			calculateLeveragedEquityReturn( { ...lahti, bankRate: undefined } )
		).toBeUndefined();
	} );

	test( 'leveraged equity no company rate', () => {
		expect(
			calculateLeveragedEquityReturn( {
				...lahti,
				annualRatePercent: undefined,
			} )
		).toBeUndefined();
	} );
} );

describe( 'First-year loan per lyhennystapa, 2026-11-01', () => {
	test( 'loan annuity', () => {
		const year = calculateFirstYearLoan( 63000, 3.8, 25, 'annuity' );
		expect( year.payments / 12 ).toBeCloseTo( 325.62, 2 );
		expect( year.interest ).toBeCloseTo( 2367.36, 2 );
	} );

	test( 'loan fixed equals annuity at base', () => {
		expect(
			calculateFirstYearLoan( 63000, 3.8, 25, 'fixed', 3.8 ).interest
		).toBeCloseTo( 2367.36, 2 );
	} );

	test( 'loan fixed without base uses rate', () => {
		expect(
			calculateFirstYearLoan( 63000, 3.8, 25, 'fixed' ).payments / 12
		).toBeCloseTo( 325.62, 2 );
	} );

	test( 'loan straight', () => {
		const year = calculateFirstYearLoan( 63000, 3.8, 25, 'straight' );
		expect( year.payments ).toBeCloseTo( 4870.11, 2 );
		expect( year.interest ).toBeCloseTo( 2350.11, 2 );
	} );

	test( 'fixed payment below interest grows balance', () => {
		const year = calculateFirstYearLoan( 63000, 7.8, 25, 'fixed', 3.8 );
		expect( year.payments / 12 ).toBeCloseTo( 325.62, 2 );
		expect( Number.isFinite( year.interest ) ).toBe( true );
		expect( year.interest ).toBeGreaterThan( 63000 * 0.078 );
	} );

	test( 'loan none', () => {
		expect( calculateFirstYearLoan( 0, 3.8, 25, 'annuity' ) ).toEqual( {
			payments: 0,
			interest: 0,
		} );
	} );

	test( 'loan short term', () => {
		expect(
			calculateFirstYearLoan( 63000, 3.8, 0.5, 'annuity' )
		).toBeUndefined();
	} );

	test( 'loan no rate', () => {
		expect(
			calculateFirstYearLoan( 63000, undefined, 25, 'annuity' )
		).toBeUndefined();
	} );

	test( 'loan unknown method', () => {
		expect(
			calculateFirstYearLoan( 63000, 3.8, 25, 'bullet' )
		).toBeUndefined();
	} );

	test( 'loan zero rate straight', () => {
		expect(
			calculateFirstYearLoan( 63000, 0, 25, 'straight' ).payments
		).toBeCloseTo( 2520, 2 );
	} );
} );
