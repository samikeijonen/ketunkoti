/**
 * Editor view of the yield calculator.
 *
 * Shows the server-rendered calculator for the home being edited, so the
 * editor sees the same figures as visitors. Outside a home there is nothing to
 * calculate, so a placeholder explains where the block works.
 */

import { Disabled, Placeholder } from '@wordpress/components';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Renders the block in the editor.
 *
 * @param {Object} props                  Block props.
 * @param {string} props.name             Block name.
 * @param {Object} props.context          Block context.
 * @param {number} props.context.postId   ID of the post being edited.
 * @param {string} props.context.postType Post type of the post being edited.
 * @return {Element} Editor element.
 */
export default function Edit( { name, context: { postId, postType } } ) {
	const blockProps = useBlockProps();

	if ( 'home' !== postType ) {
		return (
			<div { ...blockProps }>
				<Placeholder
					icon="calculator"
					label={ __( 'Vuokratuottolaskuri', 'ketunkoti-blocks' ) }
					instructions={ __(
						'Laskuri näkyy kodin sivulla.',
						'ketunkoti-blocks'
					) }
				/>
			</div>
		);
	}

	return (
		<div { ...blockProps }>
			<Disabled>
				<ServerSideRender
					block={ name }
					urlQueryArgs={ { post_id: postId } }
				/>
			</Disabled>
		</div>
	);
}
