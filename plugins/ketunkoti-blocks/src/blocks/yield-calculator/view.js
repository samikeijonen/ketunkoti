/**
 * Front-end behaviour of the yield calculator.
 *
 * The server renders the first paint (`render.php`); this store recalculates
 * the results whenever the visitor types, with the same calculator layer.
 */

import { getContext, store, withSyncEvent } from '@wordpress/interactivity';

import { calculateResults } from './calculator';

/**
 * Formats a number the Finnish way, as number_format_i18n() does on the server.
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

const { state } = store( 'ketunkoti/yield-calculator', {
	actions: {
		/**
		 * Keeps Enter in a field from submitting the form and reloading the page.
		 *
		 * Wrapped in withSyncEvent() because preventDefault() needs synchronous
		 * access to the event.
		 *
		 * @param {SubmitEvent} event Submit event.
		 */
		preventSubmit: withSyncEvent( ( event ) => {
			event.preventDefault();
		} ),

		/**
		 * Stores the typed value and recalculates the results.
		 *
		 * @param {Event} event Input event from one of the fields.
		 */
		update( event ) {
			const context = getContext();
			const { name, value } = event.target;

			context.fields[ name ] = value;
			context.results = calculateResults(
				context.home,
				context.fields,
				formatNumber,
				state.messages
			);
		},
	},
} );
