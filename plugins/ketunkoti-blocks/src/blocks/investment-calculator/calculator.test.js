/**
 * Tests for the investment calculator layer.
 *
 * Mirror `tests/php/test-investment-calculator.php` one to one: the server
 * renders the first paint with the PHP twin and this file updates it live, so
 * the strings must be identical. Values hand-checked on 2026-11-01.
 */

import { describe, expect, test } from 'vitest';

import { calculateInvestmentResults, DEFAULT_MESSAGES } from './calculator';

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

const N = ' ';
const M = '−';
const DASH = '—';

const defaults = {
	debtFreePrice: '90000',
	rent: '590',
	area: '26',
	maintenance: '104',
	ownCapitalShare: '30',
	rate: '3.8',
	years: '25',
	method: 'annuity',
	vacantMonths: '0',
	taxRate: '30',
	transferTaxRate: '1.5',
	renovation: '0',
};

/**
 * Runs the calculator with the defaults and some overrides.
 *
 * @param {Object} overrides Fields to change.
 * @return {Object} Results.
 */
const run = ( overrides = {} ) =>
	calculateInvestmentResults( { ...defaults, ...overrides }, formatNumber );

describe( 'calculateInvestmentResults', () => {
	test( 'defaults annuity', () => {
		expect( run() ).toEqual( {
			ownCapital: `27${ N }000${ N }€`,
			bankLoan: `63${ N }000${ N }€`,
			monthlyPayment: `326${ N }€/kk`,
			rentalYield: `6,4${ N }%`,
			cashFlowBeforeTax: `+160${ N }€/kk`,
			cashFlowAfterTax: `+74${ N }€/kk`,
			equityReturn: `12,2${ N }%`,
			pricePerSquare: `3${ N }462${ N }€/m²`,
			rentPerSquare: `22,69${ N }€/m²/kk`,
			maintenancePerSquare: `4,00${ N }€/m²/kk`,
			rateTable: [
				`+74${ N }€/kk`,
				`+54${ N }€/kk`,
				`+33${ N }€/kk`,
				`${ M }16${ N }€/kk`,
			],
			vacancyTable: [
				`6,4${ N }%`,
				`5,7${ N }%`,
				`5,1${ N }%`,
				`4,4${ N }%`,
			],
			notice: '',
		} );
	} );

	test( 'fixed shock keeps payment', () => {
		const results = run( { method: 'fixed' } );

		expect( results.rateTable ).toEqual( [
			`+74${ N }€/kk`,
			`+90${ N }€/kk`,
			`+106${ N }€/kk`,
			`+138${ N }€/kk`,
		] );
		expect( results.notice ).toBe( DEFAULT_MESSAGES.fixedTerm );
	} );

	test( 'fixed notice explains the rising cash flow', () => {
		expect( DEFAULT_MESSAGES.fixedTerm ).toContain( 'verotuksessa' );
	} );

	test( 'negative costs count as zero', () => {
		expect(
			run( {
				taxRate: '-30',
				transferTaxRate: '-1.5',
				renovation: '-91350',
				maintenance: '-104',
			} )
		).toEqual(
			run( {
				taxRate: '0',
				transferTaxRate: '0',
				renovation: '0',
				maintenance: '0',
			} )
		);
	} );

	test( 'straight', () => {
		const results = run( { method: 'straight' } );

		expect( results.monthlyPayment ).toBe( `406${ N }€/kk` );
		expect( results.cashFlowBeforeTax ).toBe( `+80${ N }€/kk` );
		expect( results.cashFlowAfterTax ).toBe( `${ M }7${ N }€/kk` );
		expect( results.equityReturn ).toBe( `12,3${ N }%` );
		expect( results.rateTable ).toEqual( [
			`${ M }7${ N }€/kk`,
			`${ M }43${ N }€/kk`,
			`${ M }79${ N }€/kk`,
			`${ M }151${ N }€/kk`,
		] );
	} );

	test( 'share 100 no loan', () => {
		const results = run( { ownCapitalShare: '100', rate: '' } );

		expect( results.bankLoan ).toBe( `0${ N }€` );
		expect( results.monthlyPayment ).toBe( `0${ N }€/kk` );
		expect( results.cashFlowBeforeTax ).toBe( `+486${ N }€/kk` );
		expect( results.cashFlowAfterTax ).toBe( `+340${ N }€/kk` );
		expect( results.equityReturn ).toBe( `6,4${ N }%` );
		expect( results.notice ).toBe( '' );
	} );

	test( 'share zero', () => {
		const results = run( { ownCapitalShare: '0' } );

		expect( results.ownCapital ).toBe( `0${ N }€` );
		expect( results.bankLoan ).toBe( `90${ N }000${ N }€` );
		expect( results.equityReturn ).toBe( `181,5${ N }%` );
		expect( results.notice ).toBe( '' );
	} );

	test( 'no own money at all', () => {
		const results = run( {
			ownCapitalShare: '0',
			transferTaxRate: '0',
			renovation: '0',
		} );

		expect( results.equityReturn ).toBe( DASH );
		expect( results.cashFlowAfterTax ).not.toBe( DASH );
		expect( results.notice ).toBe( DEFAULT_MESSAGES.ownMoney );
	} );

	test( 'vacancy twelve is a loss', () => {
		const results = run( { vacantMonths: '12' } );

		expect( results.cashFlowBeforeTax ).toBe( `${ M }430${ N }€/kk` );
		expect( results.cashFlowAfterTax ).toBe( `${ M }430${ N }€/kk` );
		expect( run( { vacantMonths: '15' } ) ).toEqual( results );
	} );

	test( 'missing debt-free price', () => {
		const results = run( { debtFreePrice: '' } );

		expect( results.ownCapital ).toBe( DASH );
		expect( results.cashFlowAfterTax ).toBe( DASH );
		expect( results.rateTable ).toEqual( [ DASH, DASH, DASH, DASH ] );
		expect( results.vacancyTable ).toEqual( [ DASH, DASH, DASH, DASH ] );
		expect( results.notice ).toBe( DEFAULT_MESSAGES.debtFreePrice );
	} );

	test( 'missing rent', () => {
		expect( run( { rent: '' } ).notice ).toBe( DEFAULT_MESSAGES.rent );
	} );

	test( 'maintenance per square follows area', () => {
		expect(
			run( { area: '31.5', maintenance: '122.85' } ).maintenancePerSquare
		).toBe( `3,90${ N }€/m²/kk` );
	} );

	test( 'zero area', () => {
		const results = run( { area: '0' } );

		expect( results.pricePerSquare ).toBe( DASH );
		expect( results.notice ).toBe( DEFAULT_MESSAGES.area );
	} );

	test( 'missing share', () => {
		expect( run( { ownCapitalShare: '' } ).notice ).toBe(
			DEFAULT_MESSAGES.ownCapitalShare
		);
	} );

	test( 'missing rate', () => {
		const results = run( { rate: '' } );

		expect( results.ownCapital ).toBe( `27${ N }000${ N }€` );
		expect( results.monthlyPayment ).toBe( DASH );
		expect( results.cashFlowBeforeTax ).toBe( DASH );
		expect( results.cashFlowAfterTax ).toBe( DASH );
		expect( results.equityReturn ).toBe( DASH );
		expect( results.rateTable ).toEqual( [ DASH, DASH, DASH, DASH ] );
		expect( results.vacancyTable[ 0 ] ).toBe( `6,4${ N }%` );
		expect( results.notice ).toBe( DEFAULT_MESSAGES.loan );
	} );

	test( 'short term', () => {
		expect( run( { years: '0.5' } ).notice ).toBe( DEFAULT_MESSAGES.loan );
	} );
} );
