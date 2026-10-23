<?php
/**
 * Builds the launch front page from the theme's launch patterns.
 *
 * Expands the six `ketunkoti/launch-*` patterns into regular, unsynced block
 * markup and saves it as the static front page's content, so the copy lives in
 * the database and stays editable. Run with WP-CLI from the site root:
 *
 *     wp eval-file - < docs/superpowers/plans/assets/build-front-page.php
 *
 * Image URLs are rendered by the current environment, so the same script works
 * locally and on production. The theme must already be deployed and its
 * pattern cache cleared, otherwise the patterns are not registered.
 *
 * @package Ketunkoti
 */

$ketunkoti_slugs = array(
	'ketunkoti/launch-hero',
	'ketunkoti/launch-image',
	'ketunkoti/launch-properties',
	'ketunkoti/launch-reasons',
	'ketunkoti/launch-faq',
	'ketunkoti/launch-cta',
);

$ketunkoti_registry = WP_Block_Patterns_Registry::get_instance();
$ketunkoti_parts    = array();

foreach ( $ketunkoti_slugs as $ketunkoti_slug ) {
	$ketunkoti_pattern = $ketunkoti_registry->get_registered( $ketunkoti_slug );

	if ( ! $ketunkoti_pattern ) {
		WP_CLI::error( "Pattern {$ketunkoti_slug} is not registered. Deploy the theme and clear the pattern cache first." );
	}

	$ketunkoti_parts[] = trim( $ketunkoti_pattern['content'] );
}

$ketunkoti_front_page = (int) get_option( 'page_on_front' );

if ( ! $ketunkoti_front_page ) {
	WP_CLI::error( 'No static front page is set.' );
}

// WP-CLI runs without a user, so KSES would filter the block markup.
kses_remove_filters();

$ketunkoti_result = wp_update_post(
	array(
		'ID'           => $ketunkoti_front_page,
		'post_content' => wp_slash( implode( "\n\n", $ketunkoti_parts ) ),
	),
	true
);

if ( is_wp_error( $ketunkoti_result ) ) {
	WP_CLI::error( $ketunkoti_result->get_error_message() );
}

WP_CLI::success( "Front page {$ketunkoti_front_page} updated from " . count( $ketunkoti_parts ) . ' launch patterns.' );
