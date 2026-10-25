<?php
/**
 * Contact Form 7 asset loading.
 *
 * Contact Form 7 loads its stylesheet and scripts (plus `wp-hooks` and
 * `wp-i18n`) on every page. The form is only used on a few pages, so the
 * assets are loaded only when the current post contains the form.
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Checks whether the current request shows a Contact Form 7 form.
 *
 * Looks for the shortcode and the plugin's block in the queried post. The
 * result is cached for the request because both CF7 filters call this.
 *
 * @return bool
 */
function has_contact_form() {
    static $has_form = null;

    if ( null !== $has_form ) {
        return $has_form;
    }

    $has_form = false;

    if ( is_singular() ) {
        $content  = (string) get_post_field( 'post_content', get_queried_object_id() );
        $has_form = has_shortcode( $content, 'contact-form-7' )
            || has_block( 'contact-form-7/contact-form-selector', $content );
    }

    return $has_form;
}

/**
 * Loads Contact Form 7 scripts and styles only where the form is used.
 *
 * Hooked to the plugin's own `wpcf7_load_js` and `wpcf7_load_css` filters.
 *
 * @param bool $load Whether to load the assets.
 * @return bool
 */
function load_contact_form_assets( $load ) {
    return $load && has_contact_form();
}
add_filter( 'wpcf7_load_js', __NAMESPACE__ . '\load_contact_form_assets' );
add_filter( 'wpcf7_load_css', __NAMESPACE__ . '\load_contact_form_assets' );
