/**
 * Editor view of a timeline step.
 *
 * Same markup as render.php: the number (from a CSS counter) above the step
 * content, which starts as a single paragraph.
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

/**
 * Renders the block in the editor.
 *
 * @return {Element} Editor element.
 */
export default function Edit() {
	const blockProps = useBlockProps( { className: 'timeline-item' } );
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'timeline-item__content' },
		{
			template: [
				[
					'core/paragraph',
					{
						placeholder: __(
							'Kerro, mitä tässä vaiheessa tapahtuu…',
							'ketunkoti-blocks'
						),
					},
				],
			],
		}
	);

	return (
		<li { ...blockProps }>
			<span className="timeline-item__number" aria-hidden="true" />
			<div { ...innerBlocksProps } />
		</li>
	);
}
