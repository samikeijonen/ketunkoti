/**
 * Shareable URL parameters for the investment calculator.
 *
 * The JS twin of get_investment_fields_from_query() in
 * `includes/investment-calculator.php`: the browser writes the fields into the
 * address bar under short Finnish names, and the server reads them back when
 * the link is opened, so a copied URL shows the same calculation.
 */

/**
 * Field name => URL parameter, in the order the parameters are written.
 */
export const QUERY_KEYS = {
	debtFreePrice: 'hinta',
	rent: 'vuokra',
	area: 'ala',
	maintenance: 'vastike',
	vacantMonths: 'tyhjat',
	ownCapitalShare: 'osuus',
	rate: 'korko',
	years: 'aika',
	method: 'tapa',
	transferTaxRate: 'vsv',
	renovation: 'remontti',
	taxRate: 'vero',
};

/**
 * Lyhennystapa value => its Finnish URL value.
 */
export const METHOD_QUERY_VALUES = {
	annuity: 'annuiteetti',
	fixed: 'tasaera',
	straight: 'tasalyhennys',
};

/**
 * Turns the calculator's fields into URL parameters.
 *
 * Every field is written, an emptied one as an empty value, so the link
 * reproduces exactly what the investor sees, the rate included.
 *
 * @param {Object} fields Typed values, keys as in QUERY_KEYS.
 * @return {Array<[string, string]>} Parameter name and value pairs.
 */
export const toQueryParams = ( fields ) =>
	Object.entries( QUERY_KEYS ).map( ( [ field, param ] ) => [
		param,
		'method' === field
			? METHOD_QUERY_VALUES[ fields.method ] ?? ''
			: String( fields[ field ] ?? '' ),
	] );

/**
 * Builds the shareable link for the current fields.
 *
 * Starts from the page's own address, keeps its other parameters, sets every
 * field and drops the fragment, so the link opens the same calculation.
 *
 * @param {string} href   The page's current address.
 * @param {Object} fields Typed values, keys as in QUERY_KEYS.
 * @return {string} The link.
 */
export const buildShareUrl = ( href, fields ) => {
	const url = new URL( href );

	toQueryParams( fields ).forEach( ( [ key, value ] ) =>
		url.searchParams.set( key, value )
	);
	url.hash = '';

	return url.toString();
};
