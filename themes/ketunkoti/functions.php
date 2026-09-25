<?php
/**
 * Functions and definitions.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 */

/**
 * Loads the main stylesheet in the block editor too.
 *
 * The editor used to load a small, hand-curated `assets/css/editor-style.css`
 * instead - every rule that needed editor/frontend parity (the status badge's
 * positioning being the latest) had to be copied there by hand and kept in
 * sync manually, which is exactly the kind of thing that quietly drifts out of
 * date. Loading the real compiled stylesheet removes that second copy
 * entirely: there is only one file to edit, and the editor canvas now matches
 * the frontend for anything CSS can express in the first place. Mirrors the
 * same `SCRIPT_DEBUG` suffix logic as `ketunkoti_enqueue_styles()` below, so
 * both always load the same file.
 *
 * @return void
 */
function ketunkoti_editor_style() {
	$suffix = SCRIPT_DEBUG ? '' : '.min';

	add_editor_style( 'style' . $suffix . '.css' );
}
add_action( 'after_setup_theme', 'ketunkoti_editor_style' );

/**
 * Enqueues the theme stylesheet on the front.
 *
 * @return void
 */
function ketunkoti_enqueue_styles() {
	$suffix = SCRIPT_DEBUG ? '' : '.min';
	$src    = 'style' . $suffix . '.css';

	wp_enqueue_style(
		'ketunkoti-style',
		get_parent_theme_file_uri( $src ),
		array(),
		wp_get_theme()->get( 'Version' )
	);
	wp_style_add_data(
		'ketunkoti-style',
		'path',
		get_parent_theme_file_path( $src )
	);
}
add_action( 'wp_enqueue_scripts', 'ketunkoti_enqueue_styles' );

/**
 * Enqueues block editor tweaks.
 *
 * @return void
 */
function ketunkoti_enqueue_editor_scripts() {
	wp_enqueue_script(
		'ketunkoti-editor',
		get_parent_theme_file_uri( 'assets/js/editor.js' ),
		array( 'wp-hooks' ),
		wp_get_theme()->get( 'Version' ),
		array( 'in_footer' => false )
	);
}
add_action( 'enqueue_block_editor_assets', 'ketunkoti_enqueue_editor_scripts' );

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

/**
 * Registers custom block styles.
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
}
add_action( 'init', 'ketunkoti_block_styles' );

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
