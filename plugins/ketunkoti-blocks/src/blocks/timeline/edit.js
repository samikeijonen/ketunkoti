/**
 * Editor view of the timeline.
 *
 * Uses the same ordered list markup and classes as render.php, so the editor
 * shows the line, dots and numbers as on the site. The scroll animation runs
 * on the site only (see style.scss).
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

/**
 * Three empty steps for a freshly inserted timeline.
 *
 * @type {Array}
 */
const TEMPLATE = [
	[ 'ketunkoti/timeline-item' ],
	[ 'ketunkoti/timeline-item' ],
	[ 'ketunkoti/timeline-item' ],
];

/**
 * Renders the block in the editor.
 *
 * @return {Element} Editor element.
 */
export default function Edit() {
	const blockProps = useBlockProps( { className: 'timeline' } );
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		template: TEMPLATE,
	} );

	return <ol { ...innerBlocksProps } />;
}
