<?php
/**
 * Smaller generated images.
 *
 * Strips camera metadata from resized images and lowers the WebP quality a
 * notch. PageSpeed flagged the home gallery images for weak compression;
 * most of the excess was EXIF data copied into every size.
 *
 * Existing images only change when their sizes are regenerated:
 *
 *     wp media regenerate --yes
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WebP quality for generated images, 1–100.
 *
 * Core and the Modern Image Formats plugin use 82. 78 saves roughly another
 * 10 % per image without visible loss at gallery sizes.
 */
const WEBP_QUALITY = 78;

/**
 * Puts the metadata stripping Imagick editor first in the editor list.
 *
 * The core class stays in the list. The Image Placeholders (dominant colour)
 * plugin swaps the core Imagick class for its own subclass while it reads a
 * colour, and asks for methods this editor does not have, so it skips this
 * editor and keeps working as before.
 *
 * The core editor classes are loaded right before this filter runs, so the
 * subclass can be loaded here.
 *
 * @param string[] $editors Image editor class names, in order of preference.
 * @return string[] Editor class names with this plugin's editor first.
 */
function register_image_editor( $editors ) {
	if ( ! is_array( $editors ) || ! class_exists( 'WP_Image_Editor_Imagick', false ) ) {
		return $editors;
	}

	require_once KETUNKOTI_FEATURES_DIR . 'includes/class-image-editor-imagick.php';

	if ( ! in_array( Image_Editor_Imagick::class, $editors, true ) ) {
		array_unshift( $editors, Image_Editor_Imagick::class );
	}

	return $editors;
}
add_filter( 'wp_image_editors', __NAMESPACE__ . '\register_image_editor' );

/**
 * Sets the quality of generated WebP images.
 *
 * Runs after the Modern Image Formats plugin, which forces WebP to 82 at the
 * default priority.
 *
 * @param int    $quality   Quality level between 1 and 100.
 * @param string $mime_type Image MIME type.
 * @return int Quality level.
 */
function set_webp_quality( $quality, $mime_type ) {
	if ( 'image/webp' === $mime_type ) {
		return WEBP_QUALITY;
	}

	return $quality;
}
add_filter( 'wp_editor_set_quality', __NAMESPACE__ . '\set_webp_quality', 20, 2 );
