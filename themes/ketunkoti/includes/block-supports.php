<?php
/**
 * Extra block supports for core blocks.
 *
 * @package Ketunkoti
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enables separate row and column gap support for the Group block.
 *
 * Core Group only declares `blockGap: true`, so its Grid, Row and Stack
 * variations get a single gap value for both axes. Declaring both axes keeps
 * the server side block definition in sync with the editor filter in
 * `assets/js/editor.js`.
 *
 * @param array  $args       Block type registration arguments.
 * @param string $block_type Block type name.
 * @return array Filtered block type registration arguments.
 */
function ketunkoti_group_block_gap_sides( $args, $block_type ) {
	if ( 'core/group' !== $block_type ) {
		return $args;
	}

	$args['supports']['spacing']['blockGap'] = array( 'horizontal', 'vertical' );

	return $args;
}
add_filter( 'register_block_type_args', 'ketunkoti_group_block_gap_sides', 10, 2 );

/**
 * Enables margin support for the Navigation block.
 *
 * Core Navigation declares no margin support, so it has no margin control and
 * saved margins would not be output. Keeps the server side block definition
 * in sync with the editor filter in `assets/js/editor.js`, which the frontend
 * margin output relies on.
 *
 * @param array  $args       Block type registration arguments.
 * @param string $block_type Block type name.
 * @return array Filtered block type registration arguments.
 */
function ketunkoti_navigation_margin_support( $args, $block_type ) {
	if ( 'core/navigation' !== $block_type ) {
		return $args;
	}

	$args['supports']['spacing']['margin'] = true;

	return $args;
}
add_filter( 'register_block_type_args', 'ketunkoti_navigation_margin_support', 10, 2 );
