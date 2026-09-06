<?php
/**
 * Post meta registration.
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Authorization callback shared by every meta field in this plugin.
 *
 * Anyone allowed to edit the post is allowed to read and write its meta through
 * the REST API. Without this, `show_in_rest` alone would leave the fields
 * read-only for the block editor.
 *
 * @param bool   $allowed  Whether the user can add the meta. Unused.
 * @param string $meta_key The meta key being checked. Unused.
 * @param int    $post_id  The post ID being edited.
 * @return bool True when the current user may edit the given post.
 */
function meta_auth_callback( $allowed, $meta_key, $post_id ): bool {
    return current_user_can( 'edit_post', $post_id );
}

/**
 * Sanitizes a decimal measurement or amount.
 *
 * Areas are square metres and the monthly charges are euros; both may carry
 * decimals. Negative values make no sense, so they collapse to zero, which the
 * bindings callback treats as "not set".
 *
 * @param mixed $value Raw meta value.
 * @return float Sanitized number, never negative.
 */
function sanitize_positive_number( $value ): float {
    if ( is_string( $value ) ) {
        /*
         * Finnish notation uses a comma as the decimal separator and spaces as
         * the thousands separator, so "1 285,50" must not cast to 1. Strip the
         * separators and normalise the comma before casting.
         */
        $value = str_replace( [ ' ', "\xc2\xa0", "\xe2\x80\xaf" ], '', $value );
        $value = str_replace( ',', '.', $value );
    }

    return max( 0, (float) $value );
}

/**
 * Sanitizes a construction year.
 *
 * Years are whole numbers in a plausible range. Anything outside it is a typo
 * rather than a year, so it collapses to zero, which the bindings callback
 * treats as "not set". New builds may be dated slightly into the future.
 *
 * @param mixed $value Raw meta value.
 * @return int Sanitized year, or 0 when the value is not plausible.
 */
function sanitize_year( $value ): int {
    $year = (int) $value;

    if ( $year < 1000 || $year > (int) gmdate( 'Y' ) + 5 ) {
        return 0;
    }

    return $year;
}

/**
 * Registers the meta fields for the "home" post type.
 *
 * Runs late on `init` so the post type it attaches to is already registered.
 *
 * Prices are stored as whole euros in integer fields, which keeps money out of
 * floating point arithmetic. A value of 0 is treated as "not set" by the block
 * bindings callback, since no default is registered here.
 *
 * @return void
 */
function register_meta_fields() {
    // Living area in square metres.
    register_post_meta(
        'home',
        'home_area',
        [
            'type'              => 'number',
            'label'             => __( 'Asuinpinta-ala', 'ketunkoti-features' ),
            'description'       => __( 'Kodin asuinpinta-ala neliömetreinä.', 'ketunkoti-features' ),
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => __NAMESPACE__ . '\sanitize_positive_number',
            'auth_callback'     => __NAMESPACE__ . '\meta_auth_callback',
        ]
    );

    // Debt-free price in whole euros.
    register_post_meta(
        'home',
        'home_debt_free_price',
        [
            'type'              => 'integer',
            'label'             => __( 'Velaton hinta', 'ketunkoti-features' ),
            'description'       => __( 'Kodin velaton hinta euroina.', 'ketunkoti-features' ),
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'absint',
            'auth_callback'     => __NAMESPACE__ . '\meta_auth_callback',
        ]
    );

    // Selling price in whole euros.
    register_post_meta(
        'home',
        'home_selling_price',
        [
            'type'              => 'integer',
            'label'             => __( 'Myyntihinta', 'ketunkoti-features' ),
            'description'       => __( 'Kodin myyntihinta euroina.', 'ketunkoti-features' ),
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'absint',
            'auth_callback'     => __NAMESPACE__ . '\meta_auth_callback',
        ]
    );

    // Maintenance charge in euros per month.
    register_post_meta(
        'home',
        'home_maintenance_charge',
        [
            'type'              => 'number',
            'label'             => __( 'Hoitovastike', 'ketunkoti-features' ),
            'description'       => __( 'Kodin hoitovastike euroina kuukaudessa.', 'ketunkoti-features' ),
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => __NAMESPACE__ . '\sanitize_positive_number',
            'auth_callback'     => __NAMESPACE__ . '\meta_auth_callback',
        ]
    );

    // Capital charge in euros per month.
    register_post_meta(
        'home',
        'home_capital_charge',
        [
            'type'              => 'number',
            'label'             => __( 'Pääomavastike', 'ketunkoti-features' ),
            'description'       => __( 'Kodin pääomavastike euroina kuukaudessa.', 'ketunkoti-features' ),
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => __NAMESPACE__ . '\sanitize_positive_number',
            'auth_callback'     => __NAMESPACE__ . '\meta_auth_callback',
        ]
    );

    // Floor. Free text so that "3/5" and "kellarikerros" are both valid.
    register_post_meta(
        'home',
        'home_floor',
        [
            'type'              => 'string',
            'label'             => __( 'Kerros', 'ketunkoti-features' ),
            'description'       => __( 'Kerros, esimerkiksi "3/5".', 'ketunkoti-features' ),
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => __NAMESPACE__ . '\meta_auth_callback',
        ]
    );

    // Street address.
    register_post_meta(
        'home',
        'home_address',
        [
            'type'              => 'string',
            'label'             => __( 'Osoite', 'ketunkoti-features' ),
            'description'       => __( 'Kodin katuosoite.', 'ketunkoti-features' ),
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => __NAMESPACE__ . '\meta_auth_callback',
        ]
    );

    // Construction year.
    register_post_meta(
        'home',
        'home_year_built',
        [
            'type'              => 'integer',
            'label'             => __( 'Rakennusvuosi', 'ketunkoti-features' ),
            'description'       => __( 'Vuosi jolloin kohde on rakennettu.', 'ketunkoti-features' ),
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => __NAMESPACE__ . '\sanitize_year',
            'auth_callback'     => __NAMESPACE__ . '\meta_auth_callback',
        ]
    );

    // Room layout, the Finnish "huoneistoselitelmä". Free text, because the
    // notation is conventional rather than structured. The room count itself
    // is the `home-rooms` taxonomy.
    register_post_meta(
        'home',
        'home_room_layout',
        [
            'type'              => 'string',
            'label'             => __( 'Huoneistoselitelmä', 'ketunkoti-features' ),
            'description'       => __( 'Huoneistoselitelmä, esimerkiksi "3h + k + s + p".', 'ketunkoti-features' ),
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => __NAMESPACE__ . '\meta_auth_callback',
        ]
    );
}
add_action( 'init', __NAMESPACE__ . '\register_meta_fields', 20 );
