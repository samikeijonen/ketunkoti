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
