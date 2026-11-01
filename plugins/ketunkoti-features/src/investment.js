/**
 * Investment calculations for the editor sidebar and bindings preview.
 *
 * The JS twin of `includes/investment.php` (and of get_rental_yield() in
 * `includes/bindings.php`). These copies let the editor sidebar show the
 * figures live, before the post is saved, and the calculator block in
 * `ketunkoti-blocks` update them live on the front end. Both copies are used
 * for display and must give the same results. Both are tested against the same cases: `investment.test.js` here,
 * `tests/php/test-investment.php` there.
 *
 * Plain numbers in, plain numbers out: nothing here reads post meta or the
 * DOM, and formatting takes the number formatter as an argument.
 */

/**
 * Non-breaking space, so a number never wraps away from its unit.
 */
const NBSP = ' ';

/**
 * Calculates the net rental yield, the Finnish "vuokratuotto".
 *
 *     ((vuokra - hoitovastike) * 12) / velaton hinta * 100
 *
 * Paaomavastike is deliberately not subtracted: the denominator is the
 * debt-free price, which already covers the flat's share of the taloyhtio debt,
 * so subtracting the charge that services that debt would count it twice.
 * Vesimaksu is not subtracted either, as the tenant pays it on top of the rent.
 *
 * @param {number} rent          Vuokra in euros per month.
 * @param {number} maintenance   Hoitovastike in euros per month, 0 when empty.
 * @param {number} debtFreePrice Velaton hinta in euros.
 * @return {number|undefined} Yield percentage, or undefined when inputs are missing.
 */
export const calculateRentalYield = ( rent, maintenance, debtFreePrice ) => {
	// Without a rent and a price there is no yield, and a zero price would
	// divide to Infinity rather than read as a missing value.
	if ( ! ( rent > 0 ) || ! ( debtFreePrice > 0 ) ) {
		return undefined;
	}

	return ( ( ( rent - maintenance ) * 12 ) / debtFreePrice ) * 100;
};

/**
 * Calculates the interest paid on the taloyhtiö loan share in the first year.
 *
 * Simulated month by month with the actual pääomavastike as the payment, on a
 * nominal monthly rate (annual rate / 12).
 *
 * @param {number} loan              Loan share in euros, velaton - myyntihinta.
 * @param {number} annualRatePercent Total nominal annual rate, 4.3 for 4,3 %.
 * @param {number} monthlyPayment    Pääomavastike in euros per month.
 * @return {number} Interest paid over the first twelve months.
 */
export const calculateFirstYearInterest = (
	loan,
	annualRatePercent,
	monthlyPayment
) => {
	const monthlyRate = annualRatePercent / 100 / 12;
	let balance = loan;
	let interest = 0;

	for ( let month = 0; month < 12; month++ ) {
		const monthInterest = balance * monthlyRate;
		interest += monthInterest;
		balance -= monthlyPayment - monthInterest;
	}

	return interest;
};

/**
 * Calculates the monthly cash flow, the Finnish "kassavirta".
 *
 * @param {number} rent        Vuokra in euros per month.
 * @param {number} maintenance Hoitovastike in euros per month, 0 when empty.
 * @param {number} capital     Pääomavastike in euros per month, 0 when empty.
 * @return {number|undefined} Euros per month, or undefined without a rent.
 */
export const calculateCashFlow = ( rent, maintenance, capital ) => {
	if ( ! ( rent > 0 ) ) {
		return undefined;
	}

	return rent - maintenance - capital;
};

/**
 * Calculates the first year's return on equity, the Finnish "oman pääoman tuotto".
 *
 *     (nettovuokra * 12 - first year's interest) / myyntihinta * 100
 *
 * The interest rate is only needed when there is a loan share and a
 * pääomavastike to pay it with.
 *
 * @param {Object}           inputs                   Calculation inputs.
 * @param {number}           inputs.rent              Vuokra in euros per month.
 * @param {number}           inputs.maintenance       Hoitovastike in euros per month.
 * @param {number}           inputs.capital           Pääomavastike in euros per month.
 * @param {number}           inputs.sellingPrice      Myyntihinta in euros.
 * @param {number}           inputs.debtFreePrice     Velaton hinta in euros.
 * @param {number|undefined} inputs.annualRatePercent Taloyhtiölainan korko, or undefined when not entered.
 * @return {number|undefined} Percentage, or undefined when the inputs are not enough.
 */
export const calculateEquityReturn = ( {
	rent,
	maintenance,
	capital,
	sellingPrice,
	debtFreePrice,
	annualRatePercent,
} ) => {
	if ( ! ( rent > 0 ) || ! ( sellingPrice > 0 ) ) {
		return undefined;
	}

	const loan = debtFreePrice - sellingPrice;
	let interest = 0;

	if ( loan > 0 && capital > 0 ) {
		// A loan is being paid but its rate is unknown: any figure would be a guess.
		if ( undefined === annualRatePercent ) {
			return undefined;
		}

		interest = calculateFirstYearInterest(
			loan,
			annualRatePercent,
			capital
		);
	}

	return ( ( ( rent - maintenance ) * 12 - interest ) / sellingPrice ) * 100;
};

/**
 * Calculates the monthly payment of an annuity bank loan.
 *
 * Nominal monthly rate (annual rate / 12), as the taloyhtiö loan above.
 *
 * @param {number}           loan              Loan in euros.
 * @param {number|undefined} annualRatePercent Nominal annual rate, or undefined when not entered.
 * @param {number}           years             Loan term in years.
 * @return {number|undefined} Euros per month, 0 without a loan, or undefined when the rate or term is missing.
 */
export const calculateAnnuityPayment = ( loan, annualRatePercent, years ) => {
	if ( ! ( loan > 0 ) ) {
		return 0;
	}

	if ( ! ( years > 0 ) || undefined === annualRatePercent ) {
		return undefined;
	}

	const months = years * 12;
	const monthlyRate = annualRatePercent / 100 / 12;

	const denominator = 1 - Math.pow( 1 + monthlyRate, -months );

	// A zero rate, or one so small that the denominator rounds to zero, is an
	// interest-free loan.
	if ( 0 === denominator ) {
		return loan / months;
	}

	return ( loan * monthlyRate ) / denominator;
};

/**
 * Calculates a bank loan's first year: total payments and total interest.
 *
 * The JS twin of calculate_first_year_loan() in `includes/investment.php`;
 * see there for the three methods.
 *
 * @param {number}           loan              Loan in euros.
 * @param {number|undefined} annualRatePercent Nominal annual rate, or undefined when not entered.
 * @param {number}           years             Loan term in years.
 * @param {string}           method            `annuity`, `fixed` or `straight`.
 * @param {number|undefined} baseRatePercent   Rate that fixes the `fixed` payment, undefined for the current rate.
 * @return {{payments: number, interest: number}|undefined} Year totals, zeros without a loan, or undefined when the inputs are not enough.
 */
export const calculateFirstYearLoan = (
	loan,
	annualRatePercent,
	years,
	method,
	baseRatePercent
) => {
	if ( ! ( loan > 0 ) ) {
		return { payments: 0, interest: 0 };
	}

	if (
		undefined === annualRatePercent ||
		! ( years >= 1 ) ||
		! [ 'annuity', 'fixed', 'straight' ].includes( method )
	) {
		return undefined;
	}

	const monthlyRate = annualRatePercent / 100 / 12;
	const repayment = loan / ( years * 12 );
	const payment = calculateAnnuityPayment(
		loan,
		'fixed' === method
			? baseRatePercent ?? annualRatePercent
			: annualRatePercent,
		years
	);

	let balance = loan;
	let payments = 0;
	let interest = 0;

	for ( let month = 0; month < 12; month++ ) {
		const monthInterest = balance * monthlyRate;
		const monthPayment =
			'straight' === method ? repayment + monthInterest : payment;

		interest += monthInterest;
		payments += monthPayment;
		balance -= monthPayment - monthInterest;
	}

	return { payments, interest };
};

/**
 * Calculates the monthly cash flow with the investor's own bank loan.
 *
 * @param {number} rent        Vuokra in euros per month.
 * @param {number} maintenance Hoitovastike in euros per month.
 * @param {number} capital     Pääomavastike in euros per month.
 * @param {number} bankPayment Bank loan payment in euros per month.
 * @return {number|undefined} Euros per month, or undefined without a rent.
 */
export const calculateLeveragedCashFlow = (
	rent,
	maintenance,
	capital,
	bankPayment
) => {
	const cashFlow = calculateCashFlow( rent, maintenance, capital );

	return undefined === cashFlow ? undefined : cashFlow - bankPayment;
};

/**
 * Calculates the first year's return on the investor's own capital with a bank loan.
 *
 *     (nettovuokra * 12 - taloyhtiö interest - bank interest) / omarahoitus * 100
 *
 * @param {Object}           inputs                   Calculation inputs.
 * @param {number}           inputs.rent              Vuokra in euros per month.
 * @param {number}           inputs.maintenance       Hoitovastike in euros per month.
 * @param {number}           inputs.capital           Pääomavastike in euros per month.
 * @param {number}           inputs.sellingPrice      Myyntihinta in euros.
 * @param {number}           inputs.debtFreePrice     Velaton hinta in euros.
 * @param {number|undefined} inputs.annualRatePercent Taloyhtiölainan korko, or undefined when not entered.
 * @param {number}           inputs.ownCapital        Omarahoitus in euros.
 * @param {number|undefined} inputs.bankRate          Bank loan rate, or undefined when not entered.
 * @param {number}           inputs.years             Bank loan term in years.
 * @return {number|undefined} Percentage, or undefined when the inputs are not enough.
 */
export const calculateLeveragedEquityReturn = ( {
	rent,
	maintenance,
	capital,
	sellingPrice,
	debtFreePrice,
	annualRatePercent,
	ownCapital,
	bankRate,
	years,
} ) => {
	if ( ! ( rent > 0 ) || ! ( sellingPrice > 0 ) || ! ( ownCapital > 0 ) ) {
		return undefined;
	}

	const loanShare = debtFreePrice - sellingPrice;
	let companyInterest = 0;

	if ( loanShare > 0 && capital > 0 ) {
		// A loan is being paid but its rate is unknown: any figure would be a guess.
		if ( undefined === annualRatePercent ) {
			return undefined;
		}

		companyInterest = calculateFirstYearInterest(
			loanShare,
			annualRatePercent,
			capital
		);
	}

	const bankLoan = Math.max( 0, sellingPrice - ownCapital );
	let bankInterest = 0;

	if ( bankLoan > 0 ) {
		const payment = calculateAnnuityPayment( bankLoan, bankRate, years );

		if ( undefined === payment ) {
			return undefined;
		}

		bankInterest = calculateFirstYearInterest(
			bankLoan,
			bankRate,
			payment
		);
	}

	return (
		( ( ( rent - maintenance ) * 12 - companyInterest - bankInterest ) /
			ownCapital ) *
		100
	);
};

/**
 * Rounds half away from zero, as PHP's round() does.
 *
 * @param {number} value Number to round.
 * @return {number} Rounded number.
 */
const roundHalfAwayFromZero = ( value ) =>
	Math.sign( value ) * Math.round( Math.abs( value ) );

/**
 * Formats a monthly cash flow: "+28 €/kk", "−304 €/kk", "0 €/kk".
 *
 * @param {number}   value        Euros per month.
 * @param {Function} formatNumber Locale number formatter, ( value, decimals ) => string.
 * @return {string} Formatted cash flow.
 */
export const formatCashFlow = ( value, formatNumber ) => {
	const rounded = roundHalfAwayFromZero( value );
	let sign = '';

	if ( rounded > 0 ) {
		sign = '+';
	} else if ( rounded < 0 ) {
		sign = '−';
	}

	return `${ sign }${ formatNumber( Math.abs( rounded ) ) }${ NBSP }€/kk`;
};

/**
 * Formats a percentage with one decimal, as vuokratuotto is shown.
 *
 * @param {number}   value        Percentage.
 * @param {Function} formatNumber Locale number formatter.
 * @return {string} Formatted percentage, for example "10,6 %".
 */
export const formatPercentage = ( value, formatNumber ) =>
	`${ formatNumber( value, 1 ) }${ NBSP }%`;

/**
 * Formats an interest rate with as few decimals as it needs, at most two.
 *
 * @param {number}   rate         Annual rate as a percentage.
 * @param {Function} formatNumber Locale number formatter.
 * @return {string} Formatted rate: "4 %", "4,3 %", "4,25 %".
 */
export const formatInterestRate = ( rate, formatNumber ) => {
	let decimals = 2;

	if ( Math.abs( rate - Math.round( rate ) ) < 0.000001 ) {
		decimals = 0;
	} else if ( Math.abs( rate * 10 - Math.round( rate * 10 ) ) < 0.000001 ) {
		decimals = 1;
	}

	return `${ formatNumber( rate, decimals ) }${ NBSP }%`;
};
