/**
 * FAQ schema toggle for the core Accordion block.
 *
 * Adds the `ketunkotiFaqSchema` attribute and a sidebar toggle for it. The
 * schema itself is collected and printed server side by
 * `includes/faq-schema.php`.
 */

import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';

/**
 * Name of the attribute that opts an accordion into FAQ schema.
 *
 * Must match `FAQ_SCHEMA_ATTRIBUTE` in `includes/faq-schema.php`.
 */
const FAQ_SCHEMA_ATTRIBUTE = 'ketunkotiFaqSchema';

/**
 * Registers the FAQ schema attribute on the Accordion block.
 *
 * @param {Object} settings Block type settings.
 * @param {string} name     Block type name.
 * @return {Object} Filtered block type settings.
 */
const addFaqSchemaAttribute = ( settings, name ) => {
	if ( 'core/accordion' !== name ) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			[ FAQ_SCHEMA_ATTRIBUTE ]: {
				type: 'boolean',
				default: false,
			},
		},
	};
};

/**
 * Adds the FAQ schema toggle to the Accordion block's settings sidebar.
 */
const withFaqSchemaControl = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		if ( 'core/accordion' !== props.name ) {
			return <BlockEdit { ...props } />;
		}

		const { attributes, setAttributes } = props;

		return (
			<>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody title={ __( 'Hakukoneet', 'ketunkoti-features' ) }>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'Usein kysytyt kysymykset (FAQ)',
								'ketunkoti-features'
							) }
							help={ __(
								'Lisää kysymykset ja vastaukset sivulle FAQ-rakenteisena datana (JSON-LD). Otsikko on kysymys ja paneelin teksti vastaus.',
								'ketunkoti-features'
							) }
							checked={ !! attributes[ FAQ_SCHEMA_ATTRIBUTE ] }
							onChange={ ( value ) =>
								setAttributes( {
									[ FAQ_SCHEMA_ATTRIBUTE ]: value,
								} )
							}
						/>
					</PanelBody>
				</InspectorControls>
			</>
		);
	},
	'withFaqSchemaControl'
);

addFilter(
	'blocks.registerBlockType',
	'ketunkoti/faq-schema-attribute',
	addFaqSchemaAttribute
);

addFilter(
	'editor.BlockEdit',
	'ketunkoti/faq-schema-control',
	withFaqSchemaControl
);
