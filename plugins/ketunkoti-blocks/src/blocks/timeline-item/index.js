/**
 * Registers the timeline step block in the editor.
 *
 * Styles live in the timeline block, the only place a step can be used.
 */

import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';

import metadata from './block.json';
import Edit from './edit';

registerBlockType( metadata.name, {
	edit: Edit,
	// render.php draws the list item; only the step content is saved.
	save: () => <InnerBlocks.Content />,
} );
