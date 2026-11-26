<?php
/**
 * Tests for the metadata stripping image editor and the WebP quality.
 *
 * Needs Imagick with JPEG and WebP support. The wp-env CLI container's
 * Imagick has no image formats, so run it in the WordPress container:
 *
 *     docker exec <wp-env>-wordpress-1 php -r 'require "/var/www/html/wp-load.php"; require "/var/www/html/wp-content/plugins/ketunkoti-features/tests/php/test-images.php";'
 *
 * Prints one PASS/FAIL line per check and exits with status 1 if any failed.
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti\Tests;

use Ketunkoti\Image_Editor_Imagick;

// Only runnable from the command line, never over HTTP.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$GLOBALS['ketunkoti_test_failures'] = 0;

/**
 * Compares an expected and an actual value strictly and prints the result.
 *
 * @param string $name     Check name.
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @return void
 */
function check( string $name, $expected, $actual ): void {
	if ( $expected === $actual ) {
		echo 'PASS ', $name, PHP_EOL;
		return;
	}

	++$GLOBALS['ketunkoti_test_failures'];
	echo 'FAIL ', $name, ': expected ', var_export( $expected, true ), ', got ', var_export( $actual, true ), PHP_EOL;
}

/**
 * Builds a minimal EXIF profile with the given orientation and some padding.
 *
 * The padding stands in for the camera data and preview image a phone adds.
 *
 * @param int $orientation EXIF orientation, 1–8.
 * @return string EXIF profile blob.
 */
function make_exif( int $orientation ): string {
	$ifd = pack( 'v', 1 ) . pack( 'vvVvv', 0x0112, 3, 1, $orientation, 0 ) . pack( 'V', 0 );

	return "Exif\0\0II*\0" . pack( 'V', 8 ) . $ifd . str_repeat( "\0", 20000 );
}

/**
 * Writes a JPEG with EXIF and XMP profiles to a temporary file.
 *
 * @param int $orientation EXIF orientation, 1–8.
 * @return string File path.
 */
function make_jpeg( int $orientation ): string {
	$image = new \Imagick();
	$image->newImage( 800, 600, new \ImagickPixel( '#c96' ) );
	$image->setImageFormat( 'jpeg' );
	$image->setImageProfile( 'exif', make_exif( $orientation ) );
	$image->setImageProfile( 'xmp', '<x:xmpmeta xmlns:x="adobe:ns:meta/"></x:xmpmeta>' );

	// ImageMagick rewrites the EXIF orientation from this value on save.
	$image->setImageOrientation( $orientation );

	$file = tempnam( get_temp_dir(), 'ketunkoti-image-test' );
	$image->writeImage( 'jpeg:' . $file );

	return $file;
}

/**
 * Resizes an image through the WordPress editor and saves it.
 *
 * Calls resize() directly, which unlike the upload flow does not rotate the
 * pixels to match the EXIF orientation first.
 *
 * @param string $file      Source file path.
 * @param string $mime_type Output MIME type.
 * @return string[] Profile names left in the saved file, or `[ 'error' ]`.
 */
function resized_profiles( string $file, string $mime_type ): array {
	$editor = wp_get_image_editor( $file );

	if ( is_wp_error( $editor ) || is_wp_error( $editor->resize( 300, 300 ) ) ) {
		return [ 'error' ];
	}

	$saved = $editor->save( $file . '-resized', $mime_type );

	if ( is_wp_error( $saved ) ) {
		return [ 'error' ];
	}

	$profiles = array_keys( ( new \Imagick( $saved['path'] ) )->getImageProfiles( '*', true ) );
	wp_delete_file( $saved['path'] );
	sort( $profiles );

	return $profiles;
}

if ( ! class_exists( 'Imagick' ) || ! \Imagick::queryFormats( 'JPEG' ) || ! \Imagick::queryFormats( 'WEBP' ) ) {
	echo 'SKIP Imagick with JPEG and WebP support is not available.', PHP_EOL;
	exit( 1 );
}

// Quality: WebP lowered, other formats untouched.
check( 'webp quality', 78, apply_filters( 'wp_editor_set_quality', 82, 'image/webp' ) );
check( 'jpeg quality', 82, apply_filters( 'wp_editor_set_quality', 82, 'image/jpeg' ) );

// Editor list: ours first, the core class still there for other plugins.
// Core loads its editor classes right before the filter, so do the same.
require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';
$editors = apply_filters( 'wp_image_editors', [ 'WP_Image_Editor_Imagick', 'WP_Image_Editor_GD' ] );
check( 'editor first', Image_Editor_Imagick::class, $editors[0] );
check( 'core editor kept', true, in_array( 'WP_Image_Editor_Imagick', $editors, true ) );
check( 'editor added once', 1, count( array_keys( apply_filters( 'wp_image_editors', $editors ), Image_Editor_Imagick::class, true ) ) );

$upright = make_jpeg( 1 );
check( 'source has exif and xmp', [ 'exif', 'xmp' ], array_values( array_intersect( [ 'exif', 'xmp' ], array_keys( ( new \Imagick( $upright ) )->getImageProfiles( '*', true ) ) ) ) );
check( 'editor chosen', true, wp_get_image_editor( $upright ) instanceof Image_Editor_Imagick );
check( 'upright webp stripped', [], resized_profiles( $upright, 'image/webp' ) );
check( 'upright jpeg stripped', [], resized_profiles( $upright, 'image/jpeg' ) );

// Not yet rotated: EXIF must stay or the image would show sideways.
$rotated = make_jpeg( 6 );
check( 'unrotated keeps exif', [ 'exif' ], resized_profiles( $rotated, 'image/jpeg' ) );

wp_delete_file( $upright );
wp_delete_file( $rotated );

if ( $GLOBALS['ketunkoti_test_failures'] > 0 ) {
	echo $GLOBALS['ketunkoti_test_failures'], ' check(s) failed.', PHP_EOL;
	exit( 1 );
}

echo 'All checks passed.', PHP_EOL;
