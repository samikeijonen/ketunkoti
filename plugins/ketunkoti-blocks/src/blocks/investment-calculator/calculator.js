/**
 * Investment calculator layer: the investor's typed fields in, display strings out.
 *
 * The JS twin of `includes/investment-calculator.php`. The server renders the
 * first paint with the PHP copy and `view.js` updates it live with this one, so
 * both must produce identical strings for the same inputs. The loan maths lives
 * in `ketunkoti-features` (`calculateFirstYearLoan`).
 *
 * The whole velaton hinta is financed with omarahoitus and a bank loan; there is
 * no taloyhtiölaina and no pääomavastike.
 */

import {
	calculateFirstYearLoan,
	formatCashFlow,
} from '../../../../ketunkoti-features/src/investment';
import {
	DASH,
	finiteOrUndefined,
	formatEuros,
	formatPercentageOrDash,
	parseDecimal,
} from '../../shared/numbers';

/**
 * Notices in Finnish. The block passes translated copies from PHP.
 */
export const DEFAULT_MESSAGES = {
	debtFreePrice: 'Syötä velaton hinta.',
	rent: 'Syötä vuokra.',
	area: 'Syötä pinta-ala.',
	ownCapitalShare: 'Syötä omarahoitusosuus.',
	loan: 'Syötä korko ja laina-aika.',
	ownMoney: 'Oman pääoman tuottoa ei voi laskea ilman omaa pääomaa.',
	fixedTerm:
		'Kiinteässä tasaerässä erä pysyy samana, vaikka korko nousee. Kassavirta voi silloin jopa parantua, koska korkoa vähennetään verotuksessa enemmän, mutta laina lyhenee hitaammin ja laina-aika pitenee.',
};

/**
 * Rate rises, in percentage points, shown in the interest rate table.
 */
export const RATE_SHOCKS = [ 0, 1, 2, 4 ];

/**
 * Vacant months per year shown in the vacancy table.
 */
export const VACANCY_ROWS = [ 0, 1, 2, 3 ];

/**
 * Keeps a number within a range.
 *
 * @param {number} value Number.
 * @param {number} min   Lowest allowed value.
 * @param {number} max   Highest allowed value.
 * @return {number} The clamped number.
 */
const clamp = ( value, min, max ) => Math.min( max, Math.max( min, value ) );

/**
 * Calculates every result and a notice from the typed fields.
 *
 * @param {Object}                                       fields       Typed values, see get_investment_defaults() in PHP.
 * @param {(value: number, decimals?: number) => string} formatNumber Locale number formatter.
 * @param {Object}                                       messages     Notices, see DEFAULT_MESSAGES.
 * @return {Object} Display strings: financing, results, ratios, rateTable, vacancyTable and notice.
 */
export const calculateInvestmentResults = (
	fields,
	formatNumber,
	messages = DEFAULT_MESSAGES
) => {
	const debtFreePrice = parseDecimal( fields.debtFreePrice );
	const rent = parseDecimal( fields.rent );
	const area = parseDecimal( fields.area );
	const typedShare = parseDecimal( fields.ownCapitalShare );

	const missing = [
		[ 'debtFreePrice', debtFreePrice > 0 ],
		[ 'rent', rent > 0 ],
		[ 'area', area > 0 ],
		[ 'ownCapitalShare', undefined !== typedShare ],
	].find( ( [ , valid ] ) => ! valid );

	if ( missing ) {
		return {
			ownCapital: DASH,
			bankLoan: DASH,
			monthlyPayment: DASH,
			rentalYield: DASH,
			cashFlowBeforeTax: DASH,
			cashFlowAfterTax: DASH,
			equityReturn: DASH,
			pricePerSquare: DASH,
			rentPerSquare: DASH,
			maintenancePerSquare: DASH,
			rateTable: RATE_SHOCKS.map( () => DASH ),
			vacancyTable: VACANCY_ROWS.map( () => DASH ),
			notice: messages[ missing[ 0 ] ],
		};
	}

	const share = clamp( typedShare, 0, 100 );
	const maintenance = Math.max( 0, parseDecimal( fields.maintenance ) ?? 0 );
	const vacantMonths = clamp(
		parseDecimal( fields.vacantMonths ) ?? 0,
		0,
		12
	);
	const taxRate = clamp( parseDecimal( fields.taxRate ) ?? 0, 0, 100 );
	const transferTaxRate = clamp(
		parseDecimal( fields.transferTaxRate ) ?? 0,
		0,
		100
	);
	const renovation = Math.max( 0, parseDecimal( fields.renovation ) ?? 0 );
	const rate = parseDecimal( fields.rate );
	const years = parseDecimal( fields.years ) ?? 0;

	const transferTax = ( debtFreePrice * transferTaxRate ) / 100;
	const ownCapital = ( debtFreePrice * share ) / 100;
	const bankLoan = Math.max( 0, debtFreePrice - ownCapital );
	const ownMoney = ownCapital + transferTax + renovation;
	const totalCost = debtFreePrice + transferTax + renovation;

	/**
	 * Yearly rent after vacancy, minus hoitovastike.
	 *
	 * @param {number} months Vacant months.
	 * @return {number} Euros per year.
	 */
	const netIncome = ( months ) => rent * ( 12 - months ) - maintenance * 12;

	/**
	 * Monthly cash flow after tax with a given rate rise.
	 *
	 * @param {number} shock Rate rise in percentage points.
	 * @return {number|undefined} Euros per month, or undefined without a loan rate.
	 */
	const cashFlowAfterTaxAt = ( shock ) => {
		const year = calculateFirstYearLoan(
			bankLoan,
			undefined === rate ? undefined : rate + shock,
			years,
			fields.method,
			rate
		);

		if ( undefined === year ) {
			return undefined;
		}

		const income = netIncome( vacantMonths );
		const tax = ( Math.max( 0, income - year.interest ) * taxRate ) / 100;

		return finiteOrUndefined( ( income - year.payments - tax ) / 12 );
	};

	const year = calculateFirstYearLoan(
		bankLoan,
		rate,
		years,
		fields.method,
		rate
	);
	const income = netIncome( vacantMonths );
	const cashFlowBeforeTax =
		undefined === year ? undefined : ( income - year.payments ) / 12;
	const cashFlowAfterTax = cashFlowAfterTaxAt( 0 );
	const equityReturn =
		undefined === year || ! ( ownMoney > 0 )
			? undefined
			: ( ( income - year.interest ) / ownMoney ) * 100;

	/**
	 * Formats a monthly cash flow, or a dash.
	 *
	 * @param {number|undefined} value Euros per month.
	 * @return {string} Formatted cash flow.
	 */
	const cashFlowOrDash = ( value ) =>
		undefined === finiteOrUndefined( value )
			? DASH
			: formatCashFlow( value, formatNumber );

	let notice = '';

	if ( undefined === year ) {
		notice = messages.loan;
	} else if ( ! ( ownMoney > 0 ) ) {
		notice = messages.ownMoney;
	} else if ( 'fixed' === fields.method ) {
		notice = messages.fixedTerm;
	}

	return {
		ownCapital: formatEuros( ownCapital, formatNumber ),
		bankLoan: formatEuros( bankLoan, formatNumber ),
		monthlyPayment:
			undefined === year
				? DASH
				: formatEuros( year.payments / 12, formatNumber, 0, '/kk' ),
		rentalYield: formatPercentageOrDash(
			finiteOrUndefined( ( income / totalCost ) * 100 ),
			formatNumber
		),
		cashFlowBeforeTax: cashFlowOrDash( cashFlowBeforeTax ),
		cashFlowAfterTax: cashFlowOrDash( cashFlowAfterTax ),
		equityReturn: formatPercentageOrDash(
			finiteOrUndefined( equityReturn ),
			formatNumber
		),
		pricePerSquare: formatEuros(
			debtFreePrice / area,
			formatNumber,
			0,
			'/m²'
		),
		rentPerSquare: formatEuros( rent / area, formatNumber, 2, '/m²/kk' ),
		maintenancePerSquare: formatEuros(
			maintenance / area,
			formatNumber,
			2,
			'/m²/kk'
		),
		rateTable: RATE_SHOCKS.map( ( shock ) =>
			cashFlowOrDash( cashFlowAfterTaxAt( shock ) )
		),
		vacancyTable: VACANCY_ROWS.map( ( months ) =>
			formatPercentageOrDash(
				finiteOrUndefined( ( netIncome( months ) / totalCost ) * 100 ),
				formatNumber
			)
		),
		notice,
	};
};
