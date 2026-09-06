<?php
/**
 * Functions and definitions.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 */

/**
 * Enqueues editor-style.css in the editors.
 *
 * @return void
 */
function ketunkoti_editor_style() {
	add_editor_style( 'assets/css/editor-style.css' );
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
