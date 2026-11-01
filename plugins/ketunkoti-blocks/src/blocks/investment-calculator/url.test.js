/**
 * Tests for the shareable URL parameters.
 *
 * Mirror the query checks in `tests/php/test-investment-calculator.php`:
 * what the browser writes, the server must read back to the same fields.
 */

import { describe, expect, test } from 'vitest';

import { buildShareUrl, toQueryParams } from './url';

const fields = {
	debtFreePrice: '90000',
	rent: '590',
	area: '26',
	maintenance: '104',
	vacantMonths: '0',
	ownCapitalShare: '30',
	rate: '3.8',
	years: '25',
	method: 'annuity',
	transferTaxRate: '1.5',
	renovation: '0',
	taxRate: '30',
};

describe( 'toQueryParams', () => {
	test( 'every field under its Finnish name', () => {
		expect( toQueryParams( fields ) ).toEqual( [
			[ 'hinta', '90000' ],
			[ 'vuokra', '590' ],
			[ 'ala', '26' ],
			[ 'vastike', '104' ],
			[ 'tyhjat', '0' ],
			[ 'osuus', '30' ],
			[ 'korko', '3.8' ],
			[ 'aika', '25' ],
			[ 'tapa', 'annuiteetti' ],
			[ 'vsv', '1.5' ],
			[ 'remontti', '0' ],
			[ 'vero', '30' ],
		] );
	} );

	test( 'lyhennystapa in Finnish', () => {
		const tapa = ( method ) =>
			toQueryParams( { ...fields, method } ).find(
				( [ key ] ) => 'tapa' === key
			)[ 1 ];

		expect( tapa( 'fixed' ) ).toBe( 'tasaera' );
		expect( tapa( 'straight' ) ).toBe( 'tasalyhennys' );
	} );

	test( 'an emptied field stays empty', () => {
		expect( toQueryParams( { ...fields, rent: '' } )[ 1 ] ).toEqual( [
			'vuokra',
			'',
		] );
	} );
} );

describe( 'buildShareUrl', () => {
	test( 'writes every field and keeps other parameters', () => {
		const url = new URL(
			buildShareUrl(
				'http://localhost:8888/vuokratuottolaskuri/?utm_source=x&hinta=1',
				fields
			)
		);

		expect( url.pathname ).toBe( '/vuokratuottolaskuri/' );
		expect( url.searchParams.get( 'utm_source' ) ).toBe( 'x' );
		expect( url.searchParams.get( 'hinta' ) ).toBe( '90000' );
		expect( url.searchParams.get( 'korko' ) ).toBe( '3.8' );
		expect( url.searchParams.get( 'tapa' ) ).toBe( 'annuiteetti' );
		expect( url.searchParams.getAll( 'hinta' ) ).toHaveLength( 1 );
	} );

	test( 'drops the fragment', () => {
		expect(
			buildShareUrl( 'http://localhost:8888/laskuri/#tulokset', fields )
		).not.toContain( '#' );
	} );
} );
