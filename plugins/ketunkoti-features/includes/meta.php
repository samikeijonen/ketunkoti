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
 * Sanitizes an availability date.
 *
 * Dates are stored as plain ISO `YYYY-MM-DD` strings so they sort correctly as
 * text and need no timezone handling. Anything that is not a real calendar date
 * collapses to an empty string, which the bindings callback renders as
 * "Heti vapaa": an empty value means the home is available now rather than
 * meaning the field was left unfilled.
 *
 * The round trip through format() is what rejects impossible dates such as
 * "2026-02-31", which createFromFormat() would otherwise silently roll over
 * into the following month.
 *
 * @param mixed $value Raw meta value.
 * @return string Sanitized ISO date, or an empty string.
 */
function sanitize_date( $value ): string {
    $date = trim( (string) $value );

    if ( '' === $date ) {
        return '';
    }

    $parsed = \DateTimeImmutable::createFromFormat( 'Y-m-d', $date );

    if ( false === $parsed || $parsed->format( 'Y-m-d' ) !== $date ) {
        return '';
    }

    return $date;
}

/**
 * Keeps a water charge basis only when it is one of the known values.
 *
 * Empty means per person per month, the usual Finnish basis and what every
 * value stored before this field existed meant.
 *
 * @param mixed $value Raw value.
 * @return string "person", "month", "consumption" or ''.
 */
function sanitize_water_charge_basis( $value ): string {
    $basis = (string) $value;

    return in_array( $basis, [ 'person', 'month', 'consumption' ], true ) ? $basis : '';
}

/**
 * Keeps a yes/no answer only when it is "yes" or "no".
 *
 * Empty means "not stated", which is different from "no".
 *
 * @param mixed $value Raw value.
 * @return string "yes", "no" or ''.
 */
function sanitize_yes_no( $value ): string {
    $answer = (string) $value;

    return in_array( $answer, [ 'yes', 'no' ], true ) ? $answer : '';
}

/**
 * Post types that share the common home listing fields.
 *
 * The `home_` prefix of the meta keys means "a home listing", not the `home`
 * post type: rentals reuse the same keys, so every binding, formatter and
 * sidebar control works on both.
 */
const SHARED_POST_TYPES = [ 'home', 'rental' ];

/**
 * Registers one meta key with the same arguments on several post types.
 *
 * @param string[] $post_types Post types to register the key on.
 * @param string   $meta_key   Meta key.
 * @param array    $args       Arguments for register_post_meta().
 * @return void
 */
function register_listing_meta( array $post_types, string $meta_key, array $args ): void {
    foreach ( $post_types as $post_type ) {
        register_post_meta( $post_type, $meta_key, $args );
    }
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
    register_listing_meta(
        SHARED_POST_TYPES,
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
    register_listing_meta(
        SHARED_POST_TYPES,
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
    register_listing_meta(
        SHARED_POST_TYPES,
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
    register_listing_meta(
        SHARED_POST_TYPES,
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
    register_listing_meta(
        SHARED_POST_TYPES,
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

    // Monthly rent in euros. Read by rentals as the asking rent and by
    // investments as the income side of the vuokratuotto calculation.
    register_listing_meta(
        SHARED_POST_TYPES,
        'home_rent',
        [
            'type'              => 'number',
            'label'             => __( 'Vuokra', 'ketunkoti-features' ),
            'description'       => __( 'Kodin vuokra euroina kuukaudessa.', 'ketunkoti-features' ),
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => __NAMESPACE__ . '\sanitize_positive_number',
            'auth_callback'     => __NAMESPACE__ . '\meta_auth_callback',
        ]
    );

    // Rental deposit in euros, commonly one to three months' rent.
    register_listing_meta(
        SHARED_POST_TYPES,
        'home_deposit',
        [
            'type'              => 'number',
            'label'             => __( 'Vakuus', 'ketunkoti-features' ),
            'description'       => __( 'Vuokravakuus euroina.', 'ketunkoti-features' ),
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => __NAMESPACE__ . '\sanitize_positive_number',
            'auth_callback'     => __NAMESPACE__ . '\meta_auth_callback',
        ]
    );

    // Water charge in euros per person per month, the usual Finnish billing
    // basis. Being per person, it cannot be added to a flat monthly total.
    register_listing_meta(
        SHARED_POST_TYPES,
        'home_water_charge',
        [
            'type'              => 'number',
            'label'             => __( 'Vesimaksu', 'ketunkoti-features' ),
            'description'       => __( 'Vesimaksu euroina henkilöltä kuukaudessa.', 'ketunkoti-features' ),
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => __NAMESPACE__ . '\sanitize_positive_number',
            'auth_callback'     => __NAMESPACE__ . '\meta_auth_callback',
        ]
    );

    // Other recurring costs. Free text rather than a number so the charge can
    // name itself, for example "autopaikka 25 €/kk".
    register_listing_meta(
        SHARED_POST_TYPES,
        'home_other_charges',
        [
            'type'              => 'string',
            'label'             => __( 'Muut kulut', 'ketunkoti-features' ),
            'description'       => __( 'Muut kuukausikulut, esimerkiksi "autopaikka 25 €/kk".', 'ketunkoti-features' ),
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => __NAMESPACE__ . '\meta_auth_callback',
        ]
    );

    // Date the home becomes available, as an ISO `YYYY-MM-DD` string. Empty
    // means available immediately, not missing.
    register_listing_meta(
        SHARED_POST_TYPES,
        'home_available_from',
        [
            'type'              => 'string',
            'label'             => __( 'Vapautuu', 'ketunkoti-features' ),
            'description'       => __( 'Päivämäärä jolloin koti vapautuu.', 'ketunkoti-features' ),
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => __NAMESPACE__ . '\sanitize_date',
            'auth_callback'     => __NAMESPACE__ . '\meta_auth_callback',
        ]
    );
    // Taloyhtiö loan interest rate as a percentage, 4.3 for 4,3 %. Investment
    // homes only: it feeds the oman pääoman tuotto calculation.
    register_post_meta(
        'home',
        'home_loan_interest',
        [
            'type'              => 'number',
            'label'             => __( 'Taloyhtiölainan korko', 'ketunkoti-features' ),
            'description'       => __( 'Taloyhtiölainan korko prosentteina, esimerkiksi 4,3.', 'ketunkoti-features' ),
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => __NAMESPACE__ . '\sanitize_positive_number',
            'auth_callback'     => __NAMESPACE__ . '\meta_auth_callback',
        ]
    );

    // How the water charge is billed: per person (empty, the default), per flat
    // per month, or an advance billed by consumption. Shared, like the charge.
    register_listing_meta(
        SHARED_POST_TYPES,
        'home_water_charge_basis',
        [
            'type'              => 'string',
            'label'             => __( 'Vesimaksun peruste', 'ketunkoti-features' ),
            'description'       => __( 'Henkilöä kohden, asuntoa kohden tai kulutuksen mukaan.', 'ketunkoti-features' ),
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => __NAMESPACE__ . '\sanitize_water_charge_basis',
            'auth_callback'     => __NAMESPACE__ . '\meta_auth_callback',
        ]
    );

    // Lease type: the offered lease for rentals, the current tenant's lease for
    // investment homes. Free text, for example "Toistaiseksi voimassa" or
    // "Määräaikainen 1 v".
    register_listing_meta(
        SHARED_POST_TYPES,
        'home_lease_type',
        [
            'type'              => 'string',
            'label'             => __( 'Vuokrasopimus', 'ketunkoti-features' ),
            'description'       => __( 'Esimerkiksi "Toistaiseksi voimassa" tai "Määräaikainen 1 v".', 'ketunkoti-features' ),
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => __NAMESPACE__ . '\meta_auth_callback',
        ]
    );

    // Whether the building has an elevator: "yes", "no", or empty when not stated.
    register_listing_meta(
        SHARED_POST_TYPES,
        'home_elevator',
        [
            'type'              => 'string',
            'label'             => __( 'Hissi', 'ketunkoti-features' ),
            'description'       => __( 'Onko talossa hissi.', 'ketunkoti-features' ),
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => __NAMESPACE__ . '\sanitize_yes_no',
            'auth_callback'     => __NAMESPACE__ . '\meta_auth_callback',
        ]
    );

    // Pets policy for rentals. Free text, because the real answers are
    // nuanced, for example "Sovittavissa" or "Pienet lemmikit sallittu".
    register_post_meta(
        'rental',
        'home_pets',
        [
            'type'              => 'string',
            'label'             => __( 'Lemmikit', 'ketunkoti-features' ),
            'description'       => __( 'Esimerkiksi "Sovittavissa" tai "Ei lemmikkejä".', 'ketunkoti-features' ),
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => __NAMESPACE__ . '\meta_auth_callback',
        ]
    );
}
add_action( 'init', __NAMESPACE__ . '\register_meta_fields', 20 );
