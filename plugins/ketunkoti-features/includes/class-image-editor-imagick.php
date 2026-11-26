<?php
/**
 * Imagick image editor that strips camera metadata from generated images.
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extends the core Imagick editor so resized images keep only their colour
 * profile.
 *
 * Core strips metadata from resized images but deliberately keeps the EXIF,
 * IPTC and XMP profiles. Photos taken with a phone carry an EXIF block of up
 * to ~50 KB (camera details plus an embedded preview image), and that block
 * was copied into every image size: a 150 × 150 thumbnail weighed 57 KB, of
 * which 49 KB was EXIF. Nothing on the site reads that data, so it goes.
 *
 * The ICC/ICM colour profile is kept so colours render as shot. EXIF is also
 * kept whenever the pixels have not been rotated to match the EXIF
 * orientation yet, because dropping it then would show the image sideways.
 * Core rotates on upload and resets the orientation to top-left, so in
 * practice EXIF is removed from every generated size.
 */
class Image_Editor_Imagick extends \WP_Image_Editor_Imagick {

	/**
	 * Image profiles that are always kept.
	 *
	 * @var string[]
	 */
	private const KEPT_PROFILES = [ 'icc', 'icm' ];

	/**
	 * Strips all image metadata except the colour profile.
	 *
	 * Runs the core implementation first, which also checks that the
	 * required Imagick methods exist, then removes the profiles core
	 * protects.
	 *
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	protected function strip_meta() {
		$result = parent::strip_meta();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$kept_profiles = self::KEPT_PROFILES;

		if ( ! $this->is_upright() ) {
			$kept_profiles[] = 'exif';
		}

		try {
			foreach ( array_keys( $this->image->getImageProfiles( '*', true ) ) as $profile ) {
				if ( ! in_array( $profile, $kept_profiles, true ) ) {
					$this->image->removeImageProfile( $profile );
				}
			}
		} catch ( \Exception $e ) {
			return new \WP_Error( 'image_strip_meta_error', $e->getMessage() );
		}

		return true;
	}

	/**
	 * Tells whether the pixels are already stored the right way up.
	 *
	 * When the orientation cannot be read the image is treated as rotated,
	 * so EXIF is kept rather than risking a sideways image.
	 *
	 * @return bool True when the orientation is top-left or undefined.
	 */
	private function is_upright(): bool {
		if ( ! is_callable( [ $this->image, 'getImageOrientation' ] ) ) {
			return false;
		}

		try {
			$orientation = $this->image->getImageOrientation();
		} catch ( \Exception $e ) {
			return false;
		}

		return in_array(
			$orientation,
			[ \Imagick::ORIENTATION_UNDEFINED, \Imagick::ORIENTATION_TOPLEFT ],
			true
		);
	}
}
