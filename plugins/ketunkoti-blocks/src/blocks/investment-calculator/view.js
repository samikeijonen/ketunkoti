/**
 * Front-end behaviour of the investment calculator.
 *
 * The server renders the first paint (`render.php`); this store recalculates
 * the results whenever the investor types or picks a lyhennystapa, with the
 * same calculator layer.
 */

import { getContext, store, withSyncEvent } from '@wordpress/interactivity';

import { calculateInvestmentResults } from './calculator';
import { buildShareUrl } from './url';

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

/**
 * Writes the fields into the address bar, so the URL can be copied and shared.
 *
 * Replaces the current history entry instead of adding one per keystroke, and
 * keeps any other parameters the page has.
 *
 * @param {Object} fields Typed values.
 */
const updateUrl = ( fields ) => {
	window.history.replaceState(
		window.history.state,
		'',
		buildShareUrl( window.location.href, fields )
	);
};

const { state } = store( 'ketunkoti/investment-calculator', {
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
		 * Copies the shareable link of the current calculation.
		 *
		 * The link is built from the fields, so it carries every value even
		 * before the investor has typed anything. The address bar is updated
		 * too, so a blocked clipboard still leaves a link to copy by hand.
		 * A generator, as the Interactivity API wants for async actions.
		 */
		*copyLink() {
			const context = getContext();
			const link = buildShareUrl( window.location.href, context.fields );

			updateUrl( context.fields );

			try {
				yield window.navigator.clipboard.writeText( link );
				context.copyStatus = state.messages.linkCopied;
			} catch {
				context.copyStatus = state.messages.linkCopyFailed;
			}
		},

		/**
		 * Stores the typed or selected value, recalculates the results and
		 * updates the shareable URL.
		 *
		 * @param {Event} event Input event from one of the fields.
		 */
		update( event ) {
			const context = getContext();
			const { name, value } = event.target;

			context.fields[ name ] = value;
			context.copyStatus = '';
			context.results = calculateInvestmentResults(
				context.fields,
				formatNumber,
				state.messages
			);
			updateUrl( context.fields );
		},
	},
} );
