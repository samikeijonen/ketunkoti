<?php
/**
 * "Stacked cards" Gallery block style.
 *
 * @package Ketunkoti
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the "Stacked cards" block style for the Gallery block.
 *
 * @return void
 */
function ketunkoti_stacked_gallery_block_style() {
	/*
	 * Stacked: gallery images become full-width cards that pin to the top of
	 * the viewport while the next card slides over them. Each card sticks a
	 * step lower than the previous one, so the edges of the earlier cards stay
	 * visible. Where scroll-driven animations are supported, a covered card
	 * also shrinks and darkens slightly.
	 *
	 * Card position and count come from `nth-of-type` rules instead of a render
	 * filter, so the editor gets the same stacking as the front. CSS has no
	 * widely supported `sibling-index()` yet, hence the generated rules.
	 */
	$stacked_max_cards = 20;
	$stacked_index_css = '';

	for ( $i = 1; $i <= $stacked_max_cards; $i++ ) {
		$stacked_index_css .= sprintf(
			'
			.wp-block-gallery.is-style-stacked > figure.wp-block-image:nth-of-type(%1$d) { --stack-index: %2$d; }
			.wp-block-gallery.is-style-stacked:has(> figure.wp-block-image:nth-of-type(%1$d):last-of-type) { --stack-count: %1$d; }',
			$i,
			$i - 1
		);
	}

	register_block_style(
		'core/gallery',
		array(
			'name'         => 'stacked',
			'label'        => __( 'Stacked cards', 'ketunkoti' ),
			'inline_style' => '
			.wp-block-gallery.has-nested-images.is-style-stacked {
				--stack-top: calc(var(--wp-admin--admin-bar--height, 0px) + var(--wp--preset--spacing--50));
				--stack-step: var(--wp--preset--spacing--40);
				flex-direction: column;
				flex-wrap: nowrap;
				view-timeline-name: --stacked-gallery;
			}

			.wp-block-gallery.has-nested-images.is-style-stacked figure.wp-block-image:not(#individual-image) {
				position: sticky;
				top: calc(var(--stack-top) + var(--stack-index, 0) * var(--stack-step));
				width: 100%;
				margin: 0;
			}

			.wp-block-gallery.has-nested-images.is-style-stacked figure.wp-block-image:not(#individual-image) img {
				width: 100%;
				height: auto;
				max-height: 80svh;
				aspect-ratio: 3 / 2;
				object-fit: cover;
			}

			.wp-block-gallery.has-nested-images.is-style-stacked figure.wp-block-image figcaption {
				padding: var(--wp--preset--spacing--50);
				font-size: var(--wp--preset--font-size--medium);
				text-align: start;
			}
			' . $stacked_index_css . '

			@keyframes ketunkoti-stack-recede {
				to {
					transform: scale(0.95);
					filter: brightness(0.75);
				}
			}

			/*
			 * Card N recedes while card N + 1 scrolls over it, which is the
			 * N-th slice of the time the gallery spends crossing the top of
			 * the viewport.
			 */
			@media (prefers-reduced-motion: no-preference) {
				@supports (animation-timeline: view()) {
					.wp-block-gallery.has-nested-images.is-style-stacked figure.wp-block-image:not(#individual-image):not(:last-of-type) {
						transform-origin: 50% 0;
						animation: ketunkoti-stack-recede linear both;
						animation-timeline: --stacked-gallery;
						animation-range:
							exit-crossing calc(var(--stack-index) / var(--stack-count) * 100%)
							exit-crossing calc((var(--stack-index) + 1) / var(--stack-count) * 100%);
					}
				}
			}',
		)
	);
}
add_action( 'init', 'ketunkoti_stacked_gallery_block_style' );
