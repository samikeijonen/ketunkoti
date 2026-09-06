/**
 * Editor UI for the "home" post type meta fields.
 *
 * Adds a document settings panel for the area and price fields, and registers
 * the client side half of the block bindings source so the fields show up in
 * the editor's bindings UI.
 */

import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import {
	PluginDocumentSettingPanel,
	store as editorStore,
} from '@wordpress/editor';
import { TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEntityProp, store as coreStore } from '@wordpress/core-data';
import { registerBlockBindingsSource } from '@wordpress/blocks';

import './editor.scss';

/**
 * Post type these fields belong to.
 */
const POST_TYPE = 'home';

/**
 * Block bindings source name. Must match the PHP registration.
 */
const BINDINGS_SOURCE = 'ketunkoti/home-details';

/**
 * Field keys exposed through the bindings source.
 *
 * `city` is backed by the `home-city` taxonomy rather than post meta, so it is
 * read only here and edited through the normal taxonomy UI.
 */
const FIELDS = {
	area: 'home_area',
	debtFreePrice: 'home_debt_free_price',
	sellingPrice: 'home_selling_price',
	maintenanceCharge: 'home_maintenance_charge',
	capitalCharge: 'home_capital_charge',
	floor: 'home_floor',
	address: 'home_address',
	yearBuilt: 'home_year_built',
	roomLayout: 'home_room_layout',
	city: 'home_city',
	rooms: 'home_rooms',
};

/**
 * Field keys holding free text rather than a number.
 */
const TEXT_FIELDS = [ FIELDS.floor, FIELDS.address, FIELDS.roomLayout ];

/**
 * Field keys holding a monthly charge in euros.
 */
const CHARGE_FIELDS = [ FIELDS.maintenanceCharge, FIELDS.capitalCharge ];

/**
 * Field keys that read a taxonomy rather than post meta, valued by taxonomy.
 */
const TAXONOMY_FIELDS = {
	[ FIELDS.city ]: 'home-city',
	[ FIELDS.rooms ]: 'home-rooms',
};

/**
 * Formats a number for display in the editor preview.
 *
 * Mirrors what `number_format_i18n()` produces server side. The PHP binding
 * callback stays authoritative for the front end; this only keeps the editor
 * preview looking like the rendered card.
 *
 * @param {number} value    Number to format.
 * @param {number} decimals Decimal places to show.
 * @return {string} Locale formatted number.
 */
const formatNumber = ( value, decimals = 0 ) => {
	const locale = document.documentElement?.lang?.replace( '_', '-' ) || 'fi';

	return new Intl.NumberFormat( locale, {
		minimumFractionDigits: decimals,
		maximumFractionDigits: decimals,
	} ).format( value );
};

/**
 * Resolves a single bound field to its display value.
 *
 * Returns undefined when there is nothing to show, which leaves the block's
 * own placeholder content in place - the same behaviour as the PHP callback
 * returning null.
 *
 * @param {Function} select Data registry select function.
 * @param {Object}   record Edited entity record for the post.
 * @param {string}   key    Field key from the binding args.
 * @return {string|undefined} Display value, or undefined when unset.
 */
const resolveField = ( select, record, key ) => {
	if ( ! record || ! key ) {
		return undefined;
	}

	const taxonomy = TAXONOMY_FIELDS[ key ];

	if ( taxonomy ) {
		const termIds = record[ taxonomy ];

		if ( ! termIds?.length ) {
			return undefined;
		}

		const terms = select( coreStore ).getEntityRecords(
			'taxonomy',
			taxonomy,
			{ include: termIds, per_page: -1 }
		);

		// Still resolving, or no matching terms.
		if ( ! terms?.length ) {
			return undefined;
		}

		return terms.map( ( term ) => term.name ).join( ', ' );
	}

	if ( TEXT_FIELDS.includes( key ) ) {
		return record.meta?.[ key ] || undefined;
	}

	const value = Number( record.meta?.[ key ] );

	// Zero stands in for "not set", matching the PHP callback.
	if ( ! Number.isFinite( value ) || value <= 0 ) {
		return undefined;
	}

	if ( FIELDS.area === key ) {
		return `${ formatNumber( value, 1 ) } m²`;
	}

	if ( CHARGE_FIELDS.includes( key ) ) {
		return `${ formatNumber( value, 2 ) } €/kk`;
	}

	// A year takes no thousands separator, so it skips formatNumber().
	if ( FIELDS.yearBuilt === key ) {
		return String( value );
	}

	return `${ formatNumber( value ) } €`;
};

/**
 * Registers the bindings source in the editor so the fields are selectable in
 * the bindings UI. Values are resolved server side by the PHP callback.
 */
registerBlockBindingsSource( {
	name: BINDINGS_SOURCE,
	label: __( 'Kodin tiedot', 'ketunkoti-features' ),
	/**
	 * Resolves bound values for the editor preview.
	 *
	 * Required even though the front end is rendered by the PHP callback: the
	 * bindings UI calls this without checking that it exists, so a source
	 * without it throws when the fields list is opened.
	 *
	 * @param {Object}   options          Source options.
	 * @param {Function} options.select   Data registry select function.
	 * @param {Object}   options.context  Block context, carries postId/postType.
	 * @param {Object}   options.bindings Bindings keyed by block attribute.
	 * @return {Object} Values keyed by block attribute.
	 */
	getValues( { select, context, bindings } ) {
		const { postType, postId } = context || {};

		const record =
			postType && postId
				? select( coreStore ).getEditedEntityRecord(
						'postType',
						postType,
						postId
				  )
				: undefined;

		const values = {};

		for ( const [ attribute, binding ] of Object.entries(
			bindings || {}
		) ) {
			values[ attribute ] = resolveField(
				select,
				record,
				binding?.args?.key
			);
		}

		return values;
	},
	getFieldsList() {
		return [
			{
				label: __( 'Asuinpinta-ala', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.area },
			},
			{
				label: __( 'Velaton hinta', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.debtFreePrice },
			},
			{
				label: __( 'Myyntihinta', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.sellingPrice },
			},
			{
				label: __( 'Hoitovastike', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.maintenanceCharge },
			},
			{
				label: __( 'Pääomavastike', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.capitalCharge },
			},
			{
				label: __( 'Kerros', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.floor },
			},
			{
				label: __( 'Osoite', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.address },
			},
			{
				label: __( 'Rakennusvuosi', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.yearBuilt },
			},
			{
				label: __( 'Huoneistoselitelmä', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.roomLayout },
			},
			{
				label: __( 'Huoneiden lukumäärä', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.rooms },
			},
			{
				label: __( 'Sijainti', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.city },
			},
		];
	},
} );

/**
 * Converts a control value to the numeric type the REST schema expects.
 *
 * An empty control must not send an empty string: the integer schema rejects
 * it. Zero is used as the "not set" value instead, which the bindings callback
 * renders as nothing.
 *
 * @param {string}  value   Raw value from the control.
 * @param {boolean} isFloat Whether decimals are allowed.
 * @return {number} Numeric value safe to store in meta.
 */
const toNumber = ( value, isFloat = false ) => {
	if ( '' === value || undefined === value || null === value ) {
		return 0;
	}

	const parsed = isFloat ? parseFloat( value ) : parseInt( value, 10 );

	return Number.isFinite( parsed ) && parsed > 0 ? parsed : 0;
};

/**
 * Document settings panel holding the "home" meta fields.
 *
 * @return {JSX.Element|null} The panel, or null on other post types.
 */
const HomeDetailsPanel = () => {
	const { postType, postId } = useSelect( ( select ) => {
		return {
			postType: select( editorStore ).getCurrentPostType(),
			postId: select( editorStore ).getCurrentPostId(),
		};
	}, [] );

	const [ meta, setMeta ] = useEntityProp(
		'postType',
		postType,
		'meta',
		postId
	);

	// Only relevant for the "home" post type.
	if ( POST_TYPE !== postType ) {
		return null;
	}

	/**
	 * Writes a single meta key, preserving the rest of the meta object.
	 *
	 * @param {string} key   Meta key to update.
	 * @param {number} value New value.
	 */
	const updateMeta = ( key, value ) => {
		setMeta( { ...meta, [ key ]: value } );
	};

	return (
		<PluginDocumentSettingPanel
			name="ketunkoti-home-details"
			title={ __( 'Kodin tiedot', 'ketunkoti-features' ) }
		>
			<div className="ketunkoti-home-details">
				<TextControl
					label={ __( 'Asuinpinta-ala (m²)', 'ketunkoti-features' ) }
					type="number"
					min="0"
					step="0.1"
					value={ meta?.[ FIELDS.area ] || '' }
					onChange={ ( value ) =>
						updateMeta( FIELDS.area, toNumber( value, true ) )
					}
				/>

				<TextControl
					label={ __( 'Velaton hinta (€)', 'ketunkoti-features' ) }
					type="number"
					min="0"
					step="1"
					value={ meta?.[ FIELDS.debtFreePrice ] || '' }
					onChange={ ( value ) =>
						updateMeta( FIELDS.debtFreePrice, toNumber( value ) )
					}
				/>

				<TextControl
					label={ __( 'Myyntihinta (€)', 'ketunkoti-features' ) }
					type="number"
					min="0"
					step="1"
					value={ meta?.[ FIELDS.sellingPrice ] || '' }
					onChange={ ( value ) =>
						updateMeta( FIELDS.sellingPrice, toNumber( value ) )
					}
				/>

				<TextControl
					label={ __( 'Hoitovastike (€/kk)', 'ketunkoti-features' ) }
					type="number"
					min="0"
					step="0.01"
					value={ meta?.[ FIELDS.maintenanceCharge ] || '' }
					onChange={ ( value ) =>
						updateMeta(
							FIELDS.maintenanceCharge,
							toNumber( value, true )
						)
					}
				/>

				<TextControl
					label={ __( 'Pääomavastike (€/kk)', 'ketunkoti-features' ) }
					type="number"
					min="0"
					step="0.01"
					value={ meta?.[ FIELDS.capitalCharge ] || '' }
					onChange={ ( value ) =>
						updateMeta(
							FIELDS.capitalCharge,
							toNumber( value, true )
						)
					}
				/>

				<TextControl
					label={ __( 'Kerros', 'ketunkoti-features' ) }
					help={ __( 'Esimerkiksi "3/5".', 'ketunkoti-features' ) }
					value={ meta?.[ FIELDS.floor ] || '' }
					onChange={ ( value ) => updateMeta( FIELDS.floor, value ) }
				/>

				<TextControl
					label={ __( 'Osoite', 'ketunkoti-features' ) }
					value={ meta?.[ FIELDS.address ] || '' }
					onChange={ ( value ) =>
						updateMeta( FIELDS.address, value )
					}
				/>

				<TextControl
					label={ __( 'Huoneistoselitelmä', 'ketunkoti-features' ) }
					help={ __(
						'Esimerkiksi "3h + k + s + p".',
						'ketunkoti-features'
					) }
					value={ meta?.[ FIELDS.roomLayout ] || '' }
					onChange={ ( value ) =>
						updateMeta( FIELDS.roomLayout, value )
					}
				/>

				<TextControl
					label={ __( 'Rakennusvuosi', 'ketunkoti-features' ) }
					type="number"
					min="1000"
					step="1"
					value={ meta?.[ FIELDS.yearBuilt ] || '' }
					onChange={ ( value ) =>
						updateMeta( FIELDS.yearBuilt, toNumber( value ) )
					}
				/>
			</div>
		</PluginDocumentSettingPanel>
	);
};

registerPlugin( 'ketunkoti-home-details', {
	render: HomeDetailsPanel,
} );
