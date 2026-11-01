/**
 * Editor view of the investment calculator.
 *
 * Shows the server-rendered calculator with its example values, so the editor
 * sees the same thing as visitors. The calculator needs no post data, so it
 * works on any page.
 */

import { Disabled } from '@wordpress/components';
import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Renders the block in the editor.
 *
 * @param {Object} props      Block props.
 * @param {string} props.name Block name.
 * @return {Element} Editor element.
 */
export default function Edit( { name } ) {
	return (
		<div { ...useBlockProps() }>
			<Disabled>
				<ServerSideRender block={ name } />
			</Disabled>
		</div>
	);
}
