/**
 * Yield calculator layer: the visitor's typed fields in, display strings out.
 *
 * The JS twin of `includes/yield-calculator.php`. The server renders the first
 * paint with the PHP copy and `view.js` updates it live with this one, so both
 * must produce identical strings for the same inputs. The formulas themselves
 * live in `ketunkoti-features` and are shared with the listing's figures.
 */

import {
	calculateAnnuityPayment,
	calculateLeveragedCashFlow,
	calculateLeveragedEquityReturn,
	calculateRentalYield,
	formatCashFlow,
} from '../../../../ketunkoti-features/src/investment';
import {
	DASH,
	finiteOrUndefined,
	formatPercentageOrDash,
	parseDecimal,
} from '../../shared/numbers';

// Re-exported so existing imports from this module keep working.
export { DASH, parseDecimal };

/**
 * Notices in Finnish. The block passes translated copies from PHP.
 */
export const DEFAULT_MESSAGES = {
	rent: 'Syötä vuokra.',
	ownCapitalShare: 'Syötä omarahoitusosuus.',
	loan: 'Syötä korko ja laina-aika.',
};

/**
 * Calculates the three results and a notice from the home and the typed fields.
 *
 * @param {Object}                                       home               The home's locked figures.
 * @param {number}                                       home.rent          Listed vuokra (unused: the typed rent wins).
 * @param {number}                                       home.maintenance   Hoitovastike in euros per month.
 * @param {number}                                       home.capital       Pääomavastike (unused: the bank loan pays off the taloyhtiölaina).
 * @param {number}                                       home.sellingPrice  Myyntihinta in euros.
 * @param {number}                                       home.debtFreePrice Velaton hinta in euros.
 * @param {Object}                                       fields             Typed values: rent, years, rate, ownCapitalShare (% of myyntihinta).
 * @param {(value: number, decimals?: number) => string} formatNumber       Locale number formatter.
 * @param {Object}                                       messages           Notices, see DEFAULT_MESSAGES.
 * @return {{ownCapital: string, rentalYield: string, cashFlow: string, equityReturn: string, notice: string}} Display strings.
 */
export const calculateResults = (
	home,
	fields,
	formatNumber,
	messages = DEFAULT_MESSAGES
) => {
	const rent = parseDecimal( fields.rent ) ?? 0;
	const ownCapitalShare = parseDecimal( fields.ownCapitalShare ) ?? 0;
	// The buyer finances the whole debt-free price with omarahoitus and a bank
	// loan, which pays off the flat's taloyhtiölaina share: no pääomavastike and
	// no taloyhtiö interest remain. Without a debt-free price, the selling price
	// is the whole price.
	const purchasePrice =
		home.debtFreePrice > 0 ? home.debtFreePrice : home.sellingPrice;
	const ownCapital = ( purchasePrice * ownCapitalShare ) / 100;
	// The first year is simulated month by month, so a shorter term is not supported.
	const typedYears = parseDecimal( fields.years ) ?? 0;
	const years = typedYears >= 1 ? typedYears : 0;
	const rate = parseDecimal( fields.rate );

	const bankLoan = Math.max( 0, purchasePrice - ownCapital );
	const payment = finiteOrUndefined(
		calculateAnnuityPayment( bankLoan, rate, years )
	);

	const rentalYield = calculateRentalYield(
		rent,
		home.maintenance,
		home.debtFreePrice
	);
	const cashFlow =
		undefined === payment
			? undefined
			: calculateLeveragedCashFlow( rent, home.maintenance, 0, payment );
	const equityReturn = calculateLeveragedEquityReturn( {
		rent,
		maintenance: home.maintenance,
		capital: 0,
		sellingPrice: purchasePrice,
		debtFreePrice: purchasePrice,
		annualRatePercent: undefined,
		ownCapital,
		bankRate: rate,
		years,
	} );

	let notice = '';

	if ( ! ( rent > 0 ) ) {
		notice = messages.rent;
	} else if ( ! ( ownCapital > 0 ) ) {
		notice = messages.ownCapitalShare;
	} else if ( undefined === payment ) {
		notice = messages.loan;
	}

	return {
		ownCapital:
			ownCapital > 0 ? `${ formatNumber( ownCapital ) }\u00a0€` : DASH,
		rentalYield: formatPercentageOrDash( rentalYield, formatNumber ),
		cashFlow:
			undefined === finiteOrUndefined( cashFlow )
				? DASH
				: formatCashFlow( cashFlow, formatNumber ),
		equityReturn: formatPercentageOrDash(
			finiteOrUndefined( equityReturn ),
			formatNumber
		),
		notice,
	};
};
