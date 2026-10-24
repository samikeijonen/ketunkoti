<?php
/**
 * "Video text" Cover block style and its pause/play button.
 *
 * @package Ketunkoti
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the "Video text" block style for the Cover block.
 *
 * @return void
 */
function ketunkoti_videotext_block_style() {
	/*
	 * Video text: the Cover background (video, image and overlay) only shows
	 * through the "KETUN KOTI" letterforms. The mask is applied to the
	 * background layers rather than the Cover itself, so the pause button
	 * added by `ketunkoti_videotext_toggle()` stays visible. The aspect ratio
	 * matches the mask SVG's viewBox, so the letters fill the full width;
	 * `content-box` sizing keeps that ratio for the letters while the bottom
	 * padding reserves room for the button below them.
	 */
	$mask_url = esc_url( get_theme_file_uri( 'assets/images/brand/ketunkoti-wordmark-mask.svg' ) );

	register_block_style(
		'core/cover',
		array(
			'name'         => 'videotext',
			'label'        => __( 'Video text', 'ketunkoti' ),
			'inline_style' => '
			.wp-block-cover.is-style-videotext {
				min-height: 0;
				box-sizing: content-box;
				aspect-ratio: 5981 / 733;
				padding: 0 0 3.5rem;
			}

			.wp-block-cover.is-style-videotext > :is(.wp-block-cover__video-background, .wp-block-cover__image-background, .wp-block-cover__background) {
				mask: url("' . $mask_url . '") top center / 100% auto no-repeat;
			}

			.ketunkoti-videotext__toggle {
				position: absolute;
				inset-block-end: 0;
				inset-inline-end: 0;
				z-index: 2;
				display: grid;
				place-items: center;
				width: 2.75rem;
				height: 2.75rem;
				padding: 0;
				border: 1px solid currentcolor;
				border-radius: 50%;
				background-color: var(--wp--preset--color--base);
				color: var(--wp--preset--color--contrast);
				cursor: pointer;
			}

			.ketunkoti-videotext__toggle:focus-visible {
				outline: 2px solid var(--wp--preset--color--contrast);
				outline-offset: 2px;
			}

			.ketunkoti-videotext__toggle svg {
				width: 1rem;
				height: 1rem;
				fill: currentcolor;
			}

			.ketunkoti-videotext__toggle [data-icon="play"],
			.ketunkoti-videotext__toggle.is-paused [data-icon="pause"] {
				display: none;
			}

			.ketunkoti-videotext__toggle.is-paused [data-icon="play"] {
				display: block;
			}',
		)
	);
}
add_action( 'init', 'ketunkoti_videotext_block_style' );

/**
 * Adds a pause/play button to Cover blocks using the "Video text" style.
 *
 * An autoplaying, looping video needs a way to stop it (WCAG 2.2.2 Pause,
 * Stop, Hide). The button is placed right after the video so it sits inside
 * the Cover but outside the masked layers. The masked wordmark itself is
 * decorative, so the video is hidden from assistive technology. Only Covers
 * with a video background get the button and the toggle script.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block.
 * @return string Filtered block HTML.
 */
function ketunkoti_videotext_toggle( $block_content, $block ) {
	$class_name = $block['attrs']['className'] ?? '';

	if ( ! str_contains( $class_name, 'is-style-videotext' ) || ! str_contains( $block_content, '</video>' ) ) {
		return $block_content;
	}

	$processor = new WP_HTML_Tag_Processor( $block_content );
	if ( $processor->next_tag( 'video' ) ) {
		$processor->set_attribute( 'aria-hidden', 'true' );
		$block_content = $processor->get_updated_html();
	}

	$button = sprintf(
		'<button type="button" class="ketunkoti-videotext__toggle js-videotext-toggle" aria-label="%1$s" data-label-pause="%1$s" data-label-play="%2$s">' .
			'<svg data-icon="pause" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M3 2h4v12H3zM9 2h4v12H9z"/></svg>' .
			'<svg data-icon="play" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M4 2l10 6-10 6z"/></svg>' .
		'</button>',
		esc_attr__( 'Pause video', 'ketunkoti' ),
		esc_attr__( 'Play video', 'ketunkoti' )
	);

	wp_enqueue_script(
		'ketunkoti-videotext',
		get_parent_theme_file_uri( 'assets/js/videotext.js' ),
		array(),
		wp_get_theme()->get( 'Version' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	return preg_replace( '#</video>#', '</video>' . $button, $block_content, 1 );
}
add_filter( 'render_block_core/cover', 'ketunkoti_videotext_toggle', 10, 2 );
