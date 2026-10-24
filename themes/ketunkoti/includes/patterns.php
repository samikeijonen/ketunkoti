<?php
/**
 * Block pattern categories.
 *
 * @package Ketunkoti
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers pattern categories.
 *
 * @return void
 */
function ketunkoti_pattern_categories() {

	register_block_pattern_category(
		'ketunkoti_page',
		array(
			'label'       => __( 'Pages', 'ketunkoti' ),
			'description' => __( 'A collection of full page layouts.', 'ketunkoti' ),
		)
	);
}
add_action( 'init', 'ketunkoti_pattern_categories' );
