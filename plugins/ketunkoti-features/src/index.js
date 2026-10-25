/**
 * Editor UI for the "home" post type meta fields.
 *
 * Adds a document settings panel for the area and price fields, and registers
 * the client side half of the block bindings source so the fields show up in
 * the editor's bindings UI.
 */

import { __, sprintf } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import {
	PluginDocumentSettingPanel,
	store as editorStore,
} from '@wordpress/editor';
import {
	BaseControl,
	Button,
	DatePicker,
	Dropdown,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEntityProp, store as coreStore } from '@wordpress/core-data';
import { registerBlockBindingsSource } from '@wordpress/blocks';

import './editor.scss';
import './faq-schema';
import {
	calculateCashFlow,
	calculateEquityReturn,
	calculateRentalYield,
	formatCashFlow,
	formatInterestRate,
	formatPercentage,
} from './investment';

/**
 * Post type of homes for sale, investments included.
 */
const SALE_POST_TYPE = 'home';

/**
 * Post type of homes let to tenants.
 */
const RENTAL_POST_TYPE = 'rental';

/**
 * Block bindings source names. Must match the PHP registration.
 *
 * Split into several sources - rather than one - purely so the "connect to a
 * field" picker groups them under separate headings, matching the sidebar's
 * panels below. All four resolve through the same getValues() function, since
 * it already reads any key generically regardless of which source name
 * reached it.
 */
const BINDINGS_SOURCE = 'ketunkoti/home-details';
const PRICING_BINDINGS_SOURCE = 'ketunkoti/home-pricing';
const RENTAL_BINDINGS_SOURCE = 'ketunkoti/home-rental';
const INVESTMENT_BINDINGS_SOURCE = 'ketunkoti/home-investment';

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
	rent: 'home_rent',
	deposit: 'home_deposit',
	waterCharge: 'home_water_charge',
	otherCharges: 'home_other_charges',
	availableFrom: 'home_available_from',
	loanInterest: 'home_loan_interest',
	pets: 'home_pets',
	leaseType: 'home_lease_type',
	waterChargeBasis: 'home_water_charge_basis',
	elevator: 'home_elevator',
	rentalYield: 'home_rental_yield',
	cashFlow: 'home_cash_flow',
	equityReturn: 'home_equity_return',
	city: 'home_city',
	rooms: 'home_rooms',
	purpose: 'home_purpose',
	status: 'home_status',
	plot: 'home_plot',
};

/**
 * Taxonomy holding the listing purpose.
 *
 * Read here only so the purpose can be bound to a block like any other field.
 * The sidebar deliberately does not branch on it: see HomeDetailsPanels below.
 */
const PURPOSE_TAXONOMY = 'home-purpose';

/**
 * Field keys holding free text rather than a number.
 */
const TEXT_FIELDS = [
	FIELDS.floor,
	FIELDS.address,
	FIELDS.roomLayout,
	FIELDS.otherCharges,
	FIELDS.pets,
	FIELDS.leaseType,
];

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
	[ FIELDS.purpose ]: PURPOSE_TAXONOMY,
	[ FIELDS.status ]: 'home-status',
	[ FIELDS.plot ]: 'home-plot',
};

/**
 * Display units for the water charge, keyed by `home_water_charge_basis`.
 *
 * Mirrors format_water_charge() in `includes/bindings.php`. An empty basis
 * means per person, the default.
 */
const WATER_CHARGE_UNITS = {
	person: __( '€/hlö/kk', 'ketunkoti-features' ),
	month: __( '€/kk', 'ketunkoti-features' ),
	consumption: __( '€/kk, kulutuksen mukaan', 'ketunkoti-features' ),
};

/**
 * Display labels for yes/no fields. Mirrors format_yes_no() in PHP.
 */
const YES_NO_LABELS = {
	yes: __( 'Kyllä', 'ketunkoti-features' ),
	no: __( 'Ei', 'ketunkoti-features' ),
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
 * Reads a numeric meta value, treating anything unset as 0.
 *
 * @param {Object} meta Post meta object.
 * @param {string} key  Meta key.
 * @return {number} The value, or 0.
 */
const metaNumber = ( meta, key ) => Number( meta?.[ key ] ) || 0;

/**
 * Calculates every investment figure from a post's meta.
 *
 * The arithmetic lives in `investment.js`, the twin of `includes/investment.php`.
 * The interest rate stays undefined when not entered, because "no rate" and
 * "0 %" mean different things to the oman pääoman tuotto calculation.
 *
 * @param {Object} meta Post meta object.
 * @return {Object} rentalYield, cashFlow, equityReturn and loanInterest, each a number or undefined.
 */
const getInvestmentFigures = ( meta ) => {
	const rent = metaNumber( meta, FIELDS.rent );
	const maintenance = metaNumber( meta, FIELDS.maintenanceCharge );
	const capital = metaNumber( meta, FIELDS.capitalCharge );
	const loanInterest = metaNumber( meta, FIELDS.loanInterest ) || undefined;

	return {
		rentalYield: calculateRentalYield(
			rent,
			maintenance,
			metaNumber( meta, FIELDS.debtFreePrice )
		),
		cashFlow: calculateCashFlow( rent, maintenance, capital ),
		equityReturn: calculateEquityReturn( {
			rent,
			maintenance,
			capital,
			sellingPrice: metaNumber( meta, FIELDS.sellingPrice ),
			debtFreePrice: metaNumber( meta, FIELDS.debtFreePrice ),
			annualRatePercent: loanInterest,
		} ),
		loanInterest,
	};
};

/**
 * Formats one investment figure for display, or returns undefined when unset.
 *
 * @param {string} key  Field key, one of the computed keys or the loan interest.
 * @param {Object} meta Post meta object.
 * @return {string|undefined} Formatted figure.
 */
const formatInvestmentFigure = ( key, meta ) => {
	const figures = getInvestmentFigures( meta );
	const formatters = {
		[ FIELDS.rentalYield ]: [ figures.rentalYield, formatPercentage ],
		[ FIELDS.cashFlow ]: [ figures.cashFlow, formatCashFlow ],
		[ FIELDS.equityReturn ]: [ figures.equityReturn, formatPercentage ],
		[ FIELDS.loanInterest ]: [ figures.loanInterest, formatInterestRate ],
	};
	const [ value, format ] = formatters[ key ];

	return undefined === value ? undefined : format( value, formatNumber );
};

/**
 * Field keys rendered through formatInvestmentFigure().
 */
const INVESTMENT_FIELDS = [
	FIELDS.rentalYield,
	FIELDS.cashFlow,
	FIELDS.equityReturn,
	FIELDS.loanInterest,
];

/**
 * Normalises a DatePicker value to the stored ISO `YYYY-MM-DD` form.
 *
 * DatePicker emits a full datetime such as "2026-06-01T00:00:00", but the meta
 * field holds a plain date. Taking the leading date part keeps the stored value
 * free of a time of day that would only invite timezone bugs.
 *
 * @param {string|Date} value Value from the picker.
 * @return {string} ISO date, or an empty string when unparseable.
 */
const toIsoDate = ( value ) => {
	const match = /^(\d{4}-\d{2}-\d{2})/.exec( String( value || '' ) );

	return match ? match[ 1 ] : '';
};

/**
 * Formats the availability date for display.
 *
 * An empty value means the home is available now, which is information rather
 * than absence, so it renders as a label instead of as nothing.
 *
 * @param {string} value Stored ISO `YYYY-MM-DD` date.
 * @return {string} Finnish formatted date, or the "available now" label.
 */
const formatAvailability = ( value ) => {
	const date = ( value || '' ).trim();
	const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec( date );

	if ( ! match ) {
		return __( 'Heti vapaa', 'ketunkoti-features' );
	}

	const [ , year, month, day ] = match;

	// Built from the matched parts rather than through Date, which would apply
	// the browser timezone to a value that has no time of day.
	return `${ Number( day ) }.${ Number( month ) }.${ year }`;
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

	// Derived and "empty means something" fields resolve before the numeric
	// handling below, which would read them as unset.
	if ( INVESTMENT_FIELDS.includes( key ) ) {
		return formatInvestmentFigure( key, record.meta );
	}

	if ( FIELDS.availableFrom === key ) {
		return formatAvailability( record.meta?.[ key ] );
	}

	if ( FIELDS.elevator === key ) {
		return YES_NO_LABELS[ record.meta?.[ key ] ];
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

	if ( FIELDS.waterCharge === key ) {
		const basis = record.meta?.[ FIELDS.waterChargeBasis ];
		const unit = WATER_CHARGE_UNITS[ basis ] || WATER_CHARGE_UNITS.person;

		return `${ formatNumber( value, 2 ) } ${ unit }`;
	}

	// Rents are quoted in round euros, unlike the vastike charges.
	if ( FIELDS.rent === key ) {
		return `${ formatNumber( value ) } €/kk`;
	}

	// A year takes no thousands separator, so it skips formatNumber().
	if ( FIELDS.yearBuilt === key ) {
		return String( value );
	}

	return `${ formatNumber( value ) } €`;
};

/**
 * Resolves bound values for the editor preview, shared by every source below.
 *
 * Required even though the front end is rendered by the PHP callback: the
 * bindings UI calls this without checking that it exists, so a source
 * without it throws when the fields list is opened. All four sources share
 * this one implementation, since it already resolves any key generically
 * regardless of which source name reached it.
 *
 * @param {Object}   options          Source options.
 * @param {Function} options.select   Data registry select function.
 * @param {Object}   options.context  Block context, carries postId/postType.
 * @param {Object}   options.bindings Bindings keyed by block attribute.
 * @return {Object} Values keyed by block attribute.
 */
const getValues = ( { select, context, bindings } ) => {
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

	for ( const [ attribute, binding ] of Object.entries( bindings || {} ) ) {
		values[ attribute ] = resolveField(
			select,
			record,
			binding?.args?.key
		);
	}

	return values;
};

/**
 * Registers the bindings sources in the editor so the fields are selectable
 * in the bindings UI. Values are resolved server side by the PHP callback.
 *
 * Split into four sources - rather than one - purely so the "connect to a
 * field" picker groups them under separate headings, matching the sidebar's
 * "Kodin tiedot" / "Hinnat ja vastikkeet" / "Vuokratiedot" / "Sijoituslaskelma"
 * panels above: WordPress's bindings UI only ever groups by registered source
 * name, with no sub-category within a single source.
 */
registerBlockBindingsSource( {
	name: BINDINGS_SOURCE,
	label: __( 'Kodin tiedot', 'ketunkoti-features' ),
	getValues,
	getFieldsList() {
		return [
			{
				label: __( 'Asuinpinta-ala', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.area },
			},
			{
				label: __( 'Osoite', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.address },
			},
			{
				label: __( 'Kerros', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.floor },
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
				label: __( 'Sijainti', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.city },
			},
			{
				label: __( 'Huoneiden lukumäärä', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.rooms },
			},
			{
				label: __( 'Käyttötarkoitus', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.purpose },
			},
			{
				label: __( 'Status', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.status },
			},
			{
				label: __( 'Tontti', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.plot },
			},
		];
	},
} );

registerBlockBindingsSource( {
	name: PRICING_BINDINGS_SOURCE,
	label: __( 'Hinnat ja vastikkeet', 'ketunkoti-features' ),
	getValues,
	getFieldsList() {
		return [
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
		];
	},
} );

registerBlockBindingsSource( {
	name: RENTAL_BINDINGS_SOURCE,
	label: __( 'Vuokratiedot', 'ketunkoti-features' ),
	getValues,
	getFieldsList() {
		return [
			{
				label: __( 'Vuokra', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.rent },
			},
			{
				label: __( 'Vakuus', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.deposit },
			},
			{
				label: __( 'Vesimaksu', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.waterCharge },
			},
			{
				label: __( 'Muut kulut', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.otherCharges },
			},
			{
				label: __( 'Vapautuu', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.availableFrom },
			},
			{
				label: __( 'Lemmikit', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.pets },
			},
			{
				label: __( 'Vuokrasopimus', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.leaseType },
			},
			{
				label: __( 'Hissi', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.elevator },
			},
		];
	},
} );

registerBlockBindingsSource( {
	name: INVESTMENT_BINDINGS_SOURCE,
	label: __( 'Sijoituslaskelma', 'ketunkoti-features' ),
	getValues,
	getFieldsList() {
		return [
			{
				label: __( 'Vuokratuotto', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.rentalYield },
			},
			{
				label: __( 'Kassavirta', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.cashFlow },
			},
			{
				label: __( 'Oman pääoman tuotto', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.equityReturn },
			},
			{
				label: __( 'Taloyhtiölainan korko', 'ketunkoti-features' ),
				type: 'string',
				args: { key: FIELDS.loanInterest },
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

	// Accept a Finnish decimal comma ("4,3") as well as a dot.
	const normalised = String( value ).replace( ',', '.' );
	const parsed = isFloat
		? parseFloat( normalised )
		: parseInt( normalised, 10 );

	return Number.isFinite( parsed ) && parsed > 0 ? parsed : 0;
};

/**
 * Document settings panels holding the home listing meta fields.
 *
 * The fields are grouped into panels by subject. Which panels render depends
 * on the post type only: rentals get "Kodin tiedot" and "Vuokratiedot", homes
 * for sale get all four. The post type is known synchronously and a rental
 * never has the sale fields registered, so neither problem below applies.
 *
 * Panels never depend on a term. Showing or hiding them based on the selected purpose was tried and
 * rejected: the terms arrive asynchronously, so panels would appear and then
 * vanish a moment after load, and a hidden field still holds its value, which
 * makes "removed from the editor" look like "removed from the site" when it is
 * not. Editors who want less on screen can collapse a panel, and WordPress
 * remembers that per user.
 *
 * @return {JSX.Element|null} The panels, or null on other post types.
 */
const HomeDetailsPanels = () => {
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

	// Only relevant for the home listing post types.
	if ( SALE_POST_TYPE !== postType && RENTAL_POST_TYPE !== postType ) {
		return null;
	}

	const isRental = RENTAL_POST_TYPE === postType;

	/**
	 * Writes a single meta key, preserving the rest of the meta object.
	 *
	 * @param {string}        key   Meta key to update.
	 * @param {number|string} value New value.
	 */
	const updateMeta = ( key, value ) => {
		setMeta( { ...meta, [ key ]: value } );
	};

	/**
	 * Builds a numeric control bound to a meta key.
	 *
	 * @param {string} metaKey Meta key to edit.
	 * @param {string} label   Visible label, already translated.
	 * @param {Object} options Control options: step, min, isFloat, help.
	 * @return {JSX.Element} The control.
	 */
	const numberField = ( metaKey, label, options = {} ) => {
		const { step = '1', min = '0', isFloat = false, help } = options;

		return (
			<TextControl
				label={ label }
				help={ help }
				type="number"
				min={ min }
				step={ step }
				value={ meta?.[ metaKey ] || '' }
				onChange={ ( value ) =>
					updateMeta( metaKey, toNumber( value, isFloat ) )
				}
			/>
		);
	};

	/**
	 * Builds a text control bound to a meta key.
	 *
	 * @param {string} metaKey Meta key to edit.
	 * @param {string} label   Visible label, already translated.
	 * @param {Object} options Control options: help, type.
	 * @return {JSX.Element} The control.
	 */
	const textField = ( metaKey, label, options = {} ) => (
		<TextControl
			label={ label }
			help={ options.help }
			type={ options.type }
			value={ meta?.[ metaKey ] || '' }
			onChange={ ( value ) => updateMeta( metaKey, value ) }
		/>
	);

	/**
	 * Builds a select control bound to a string meta key.
	 *
	 * @param {string}   metaKey Meta key to edit.
	 * @param {string}   label   Visible label, already translated.
	 * @param {Object[]} options Options as { label, value }.
	 * @return {JSX.Element} The control.
	 */
	const selectField = ( metaKey, label, options ) => (
		<SelectControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ label }
			value={ meta?.[ metaKey ] || '' }
			options={ options }
			onChange={ ( value ) => updateMeta( metaKey, value ) }
		/>
	);

	/**
	 * Builds the availability control.
	 *
	 * A core DatePicker in a dropdown rather than an `<input type="date">`,
	 * which renders the browser's own widget and looks nothing like the
	 * surrounding controls.
	 *
	 * The clear button is not optional: an empty value is what stores "heti
	 * vapaa", and a calendar has no way to express "no date".
	 *
	 * @return {JSX.Element} The control.
	 */
	const availabilityField = () => {
		const stored = meta?.[ FIELDS.availableFrom ] || '';
		const label = formatAvailability( stored );

		return (
			<BaseControl
				id="ketunkoti-home-available-from"
				label={ __( 'Vapautuu', 'ketunkoti-features' ) }
				help={
					isRental
						? __(
								'Tyhjä = heti vapaa. Kun koti on vuokrattu, lisää päivä tai poista rivi sivulta.',
								'ketunkoti-features'
						  )
						: __(
								'Tyhjä tarkoittaa, että koti on heti vapaa.',
								'ketunkoti-features'
						  )
				}
			>
				<Dropdown
					className="ketunkoti-home-details__date"
					popoverProps={ { placement: 'bottom-start' } }
					renderToggle={ ( { isOpen, onToggle } ) => (
						<Button
							id="ketunkoti-home-available-from"
							variant="tertiary"
							onClick={ onToggle }
							aria-expanded={ isOpen }
							aria-label={ sprintf(
								/* translators: %s: Availability date, or the "available now" label. */
								__( 'Vapautuu: %s', 'ketunkoti-features' ),
								label
							) }
						>
							{ label }
						</Button>
					) }
					renderContent={ () => (
						<div className="ketunkoti-home-details__date-popover">
							<DatePicker
								currentDate={ stored || null }
								onChange={ ( date ) =>
									updateMeta(
										FIELDS.availableFrom,
										toIsoDate( date )
									)
								}
							/>

							<Button
								variant="tertiary"
								disabled={ '' === stored }
								accessibleWhenDisabled
								onClick={ () =>
									updateMeta( FIELDS.availableFrom, '' )
								}
							>
								{ __(
									'Tyhjennä (heti vapaa)',
									'ketunkoti-features'
								) }
							</Button>
						</div>
					) }
				/>
			</BaseControl>
		);
	};

	/**
	 * Renders one read-only investment figure row.
	 *
	 * @param {string} key   Field key passed to formatInvestmentFigure().
	 * @param {string} label Visible label, already translated.
	 * @return {JSX.Element} The row.
	 */
	const figureRow = ( key, label ) => (
		<p className="ketunkoti-home-details__yield">
			<span className="ketunkoti-home-details__yield-label">
				{ label }
			</span>
			<strong className="ketunkoti-home-details__yield-value">
				{ formatInvestmentFigure( key, meta ) ?? '—' }
			</strong>
		</p>
	);

	return (
		<>
			<PluginDocumentSettingPanel
				name="ketunkoti-home-details"
				title={ __( 'Kodin tiedot', 'ketunkoti-features' ) }
			>
				<div className="ketunkoti-home-details">
					{ numberField(
						FIELDS.area,
						__( 'Asuinpinta-ala (m²)', 'ketunkoti-features' ),
						{ step: '0.1', isFloat: true }
					) }
					{ textField(
						FIELDS.address,
						__( 'Osoite', 'ketunkoti-features' )
					) }
					{ textField(
						FIELDS.floor,
						__( 'Kerros', 'ketunkoti-features' ),
						{
							help: __(
								'Esimerkiksi "3/5".',
								'ketunkoti-features'
							),
						}
					) }
					{ textField(
						FIELDS.roomLayout,
						__( 'Huoneistoselitelmä', 'ketunkoti-features' ),
						{
							help: __(
								'Esimerkiksi "3h + k + s + p".',
								'ketunkoti-features'
							),
						}
					) }
					{ numberField(
						FIELDS.yearBuilt,
						__( 'Rakennusvuosi', 'ketunkoti-features' ),
						{ min: '1000' }
					) }
				</div>
			</PluginDocumentSettingPanel>

			{ ! isRental && (
				<PluginDocumentSettingPanel
					name="ketunkoti-home-sale"
					title={ __( 'Hinnat ja vastikkeet', 'ketunkoti-features' ) }
				>
					<div className="ketunkoti-home-details">
						{ numberField(
							FIELDS.debtFreePrice,
							__( 'Velaton hinta (€)', 'ketunkoti-features' )
						) }
						{ numberField(
							FIELDS.sellingPrice,
							__( 'Myyntihinta (€)', 'ketunkoti-features' )
						) }
						{ numberField(
							FIELDS.maintenanceCharge,
							__( 'Hoitovastike (€/kk)', 'ketunkoti-features' ),
							{ step: '0.01', isFloat: true }
						) }
						{ numberField(
							FIELDS.capitalCharge,
							__( 'Pääomavastike (€/kk)', 'ketunkoti-features' ),
							{ step: '0.01', isFloat: true }
						) }
						{ numberField(
							FIELDS.loanInterest,
							__(
								'Taloyhtiölainan korko (%)',
								'ketunkoti-features'
							),
							{
								step: '0.01',
								isFloat: true,
								help: __(
									'Prosentteina, esim. 4,3.',
									'ketunkoti-features'
								),
							}
						) }
					</div>
				</PluginDocumentSettingPanel>
			) }

			<PluginDocumentSettingPanel
				name="ketunkoti-home-rental"
				title={ __( 'Vuokratiedot', 'ketunkoti-features' ) }
			>
				<div className="ketunkoti-home-details">
					{ numberField(
						FIELDS.rent,
						__( 'Vuokra (€/kk)', 'ketunkoti-features' )
					) }
					{ numberField(
						FIELDS.deposit,
						__( 'Vakuus (€)', 'ketunkoti-features' )
					) }
					{ numberField(
						FIELDS.waterCharge,
						__( 'Vesimaksu (€)', 'ketunkoti-features' ),
						{ step: '0.01', isFloat: true }
					) }
					{ selectField(
						FIELDS.waterChargeBasis,
						__( 'Vesimaksun peruste', 'ketunkoti-features' ),
						[
							{
								label: __(
									'Henkilöä kohden / kk',
									'ketunkoti-features'
								),
								value: '',
							},
							{
								label: __(
									'Asuntoa kohden / kk',
									'ketunkoti-features'
								),
								value: 'month',
							},
							{
								label: __(
									'Kulutuksen mukaan (ennakko / kk)',
									'ketunkoti-features'
								),
								value: 'consumption',
							},
						]
					) }
					{ textField(
						FIELDS.otherCharges,
						__( 'Muut kulut', 'ketunkoti-features' ),
						{
							help: __(
								'Esimerkiksi "autopaikka 25 €/kk".',
								'ketunkoti-features'
							),
						}
					) }
					{ availabilityField() }
					{ textField(
						FIELDS.leaseType,
						__( 'Vuokrasopimus', 'ketunkoti-features' ),
						{
							help: __(
								'Esimerkiksi "Toistaiseksi voimassa" tai "Määräaikainen 1 v".',
								'ketunkoti-features'
							),
						}
					) }
					{ selectField(
						FIELDS.elevator,
						__( 'Hissi', 'ketunkoti-features' ),
						[
							{ label: '—', value: '' },
							{
								label: __( 'Kyllä', 'ketunkoti-features' ),
								value: 'yes',
							},
							{
								label: __( 'Ei', 'ketunkoti-features' ),
								value: 'no',
							},
						]
					) }
					{ isRental &&
						textField(
							FIELDS.pets,
							__( 'Lemmikit', 'ketunkoti-features' ),
							{
								help: __(
									'Esimerkiksi "Sovittavissa" tai "Ei lemmikkejä".',
									'ketunkoti-features'
								),
							}
						) }
				</div>
			</PluginDocumentSettingPanel>

			{ ! isRental && (
				<PluginDocumentSettingPanel
					name="ketunkoti-home-investment"
					title={ __( 'Sijoituslaskelma', 'ketunkoti-features' ) }
				>
					<div className="ketunkoti-home-details">
						{ figureRow(
							FIELDS.rentalYield,
							__( 'Vuokratuotto', 'ketunkoti-features' )
						) }
						{ figureRow(
							FIELDS.cashFlow,
							__( 'Kassavirta', 'ketunkoti-features' )
						) }
						{ figureRow(
							FIELDS.equityReturn,
							__( 'Oman pääoman tuotto', 'ketunkoti-features' )
						) }
						{ figureRow(
							FIELDS.loanInterest,
							__( 'Taloyhtiölainan korko', 'ketunkoti-features' )
						) }

						<p className="ketunkoti-home-details__yield-help">
							{ __(
								'Lasketaan vuokrasta, hoito- ja pääomavastikkeesta, myynti- ja velattomasta hinnasta sekä korosta. Ennen veroja.',
								'ketunkoti-features'
							) }
						</p>
					</div>
				</PluginDocumentSettingPanel>
			) }
		</>
	);
};

registerPlugin( 'ketunkoti-home-details', {
	render: HomeDetailsPanels,
} );
