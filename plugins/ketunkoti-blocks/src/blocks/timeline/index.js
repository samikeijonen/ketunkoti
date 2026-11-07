/**
 * Registers the timeline block in the editor.
 */

import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';

import metadata from './block.json';
import Edit from './edit';
import './style.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	// render.php draws the wrapper; only the steps are saved.
	save: () => <InnerBlocks.Content />,
} );
