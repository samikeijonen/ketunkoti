<?php
/**
 * General purpose block styles.
 *
 * @package Ketunkoti
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers general purpose block styles.
 *
 * @return void
 */
function ketunkoti_block_styles() {
	register_block_style(
		'core/list',
		array(
			'name'         => 'checkmark-list',
			'label'        => __( 'Checkmark', 'ketunkoti' ),
			'inline_style' => '
			ul.is-style-checkmark-list {
				list-style-type: "\2713";
			}

			ul.is-style-checkmark-list li {
				padding-inline-start: 1ch;
			}',
		)
	);

	/*
	 * A smaller button. `style_data` makes it a block style variation, so
	 * WordPress generates matching CSS for the front and the editor, and the
	 * style can be adjusted under Styles > Blocks > Button. Colours, hover,
	 * focus and radius are inherited from the default button.
	 */
	register_block_style(
		'core/button',
		array(
			'name'       => 'button-small',
			'label'      => __( 'Small', 'ketunkoti' ),
			'style_data' => array(
				'spacing'    => array(
					'padding' => array(
						'top'    => '0.625em',
						'right'  => '1.5em',
						'bottom' => '0.625em',
						'left'   => '1.5em',
					),
				),
			),
		)
	);
}
add_action( 'init', 'ketunkoti_block_styles' );
