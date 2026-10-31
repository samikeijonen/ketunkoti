<?php
/**
 * Listing schema for homes and rentals.
 *
 * Turns The SEO Framework's `WebPage` entity on single home and rental pages
 * into a `RealEstateListing` with an `Offer` for the apartment. Homes for sale
 * carry the selling price as the offer price and list the selling price, debt
 * free price and monthly charges as named price specifications. Rentals carry
 * the monthly rent.
 *
 * Every value comes from the listing's own meta fields and taxonomies, empty
 * fields are left out.
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti;

use WP_Post;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Schema.org availability for each `home-status` term slug.
 */
const HOME_STATUS_AVAILABILITY = [
    'myynnissa' => 'https://schema.org/InStock',
    'vapaa'     => 'https://schema.org/InStock',
    'varattu'   => 'https://schema.org/Reserved',
    'myyty'     => 'https://schema.org/SoldOut',
    'vuokrattu' => 'https://schema.org/SoldOut',
];

/**
 * Returns a positive numeric meta value, or null when it is empty.
 *
 * @param int    $post_id  Post ID.
 * @param string $meta_key Meta key.
 * @return float|null The value, or null.
 */
function get_home_number( int $post_id, string $meta_key ): ?float {
    $value = get_post_meta( $post_id, $meta_key, true );

    return is_numeric( $value ) && (float) $value > 0 ? (float) $value : null;
}

/**
 * Returns a non-empty string meta value, or null.
 *
 * @param int    $post_id  Post ID.
 * @param string $meta_key Meta key.
 * @return string|null The trimmed value, or null.
 */
function get_home_text( int $post_id, string $meta_key ): ?string {
    $value = trim( (string) get_post_meta( $post_id, $meta_key, true ) );

    return '' !== $value ? $value : null;
}

/**
 * Returns the slug or name of the first term of a taxonomy, or null.
 *
 * @param int    $post_id  Post ID.
 * @param string $taxonomy Taxonomy name.
 * @param string $field    Term field, `slug` or `name`.
 * @return string|null The field value, or null.
 */
function get_home_term( int $post_id, string $taxonomy, string $field = 'name' ): ?string {
    $terms = get_the_terms( $post_id, $taxonomy );

    return is_array( $terms ) && $terms ? (string) $terms[0]->$field : null;
}

/**
 * Builds a named price specification in euros.
 *
 * @param string     $name    Price name shown to readers, for example "Velaton hinta".
 * @param float|null $price   Price in euros, or null to skip.
 * @param bool       $monthly Whether the price is per month.
 * @return array|null The specification, or null when there is no price.
 */
function home_price_specification( string $name, ?float $price, bool $monthly = false ): ?array {
    if ( null === $price ) {
        return null;
    }

    $specification = [
        '@type'         => $monthly ? 'UnitPriceSpecification' : 'PriceSpecification',
        'name'          => $name,
        'price'         => $price,
        'priceCurrency' => 'EUR',
    ];

    if ( $monthly ) {
        $specification['unitCode'] = 'MON';
    }

    return $specification;
}

/**
 * Builds the `Apartment` entity of a listing.
 *
 * @param WP_Post $post Home or rental post.
 * @return array Apartment entity.
 */
function home_apartment_schema( WP_Post $post ): array {
    $rooms  = get_home_term( $post->ID, 'home-rooms' );
    $area   = get_home_number( $post->ID, 'home_area' );
    $layout = get_home_text( $post->ID, 'home_room_layout' );
    $image  = get_the_post_thumbnail_url( $post, 'large' );

    $address = array_filter(
        [
            '@type'           => 'PostalAddress',
            'streetAddress'   => get_home_text( $post->ID, 'home_address' ),
            'addressLocality' => get_home_term( $post->ID, 'home-city' ),
            'addressCountry'  => 'FI',
        ]
    );

    $year   = get_home_number( $post->ID, 'home_year_built' );

    $apartment = [
        '@type'              => 'Apartment',
        'name'               => get_the_title( $post ),
        'url'                => get_permalink( $post ),
        'image'              => $image ? $image : null,
        'address'            => $address,
        'floorSize'          => $area ? [
            '@type'    => 'QuantitativeValue',
            'value'    => $area,
            'unitCode' => 'MTK',
        ] : null,
        // Term names look like "2h", so the leading number is the room count.
        'numberOfRooms'      => $rooms && (int) $rooms > 0 ? (int) $rooms : null,
        'floorLevel'         => get_home_text( $post->ID, 'home_floor' ),
        'yearBuilt'          => $year ? (int) $year : null,
        'additionalProperty' => $layout ? [
            '@type' => 'PropertyValue',
            'name'  => __( 'Huoneistoselitelmä', 'ketunkoti-features' ),
            'value' => $layout,
        ] : null,
    ];

    return array_filter( $apartment, static fn( $value ) => null !== $value );
}

/**
 * Builds the `Offer` of a listing.
 *
 * Homes for sale use the selling price as the offer price, since that is the
 * amount the buyer pays, and list every price as a named specification.
 * Rentals use the monthly rent.
 *
 * @param WP_Post $post Home or rental post.
 * @return array|null Offer entity, or null when the listing has no price.
 */
function home_offer_schema( WP_Post $post ): ?array {
    $is_rental = 'rental' === $post->post_type;
    $status    = get_home_term( $post->ID, 'home-status', 'slug' );

    if ( $is_rental ) {
        $price          = get_home_number( $post->ID, 'home_rent' );
        $specifications = [
            home_price_specification( __( 'Vuokra', 'ketunkoti-features' ), $price, true ),
        ];
    } else {
        $price          = get_home_number( $post->ID, 'home_selling_price' );
        $specifications = [
            home_price_specification( __( 'Myyntihinta', 'ketunkoti-features' ), $price ),
            home_price_specification( __( 'Velaton hinta', 'ketunkoti-features' ), get_home_number( $post->ID, 'home_debt_free_price' ) ),
            home_price_specification( __( 'Hoitovastike', 'ketunkoti-features' ), get_home_number( $post->ID, 'home_maintenance_charge' ), true ),
            home_price_specification( __( 'Pääomavastike', 'ketunkoti-features' ), get_home_number( $post->ID, 'home_capital_charge' ), true ),
        ];
    }

    if ( null === $price ) {
        return null;
    }

    $offer = [
        '@type'              => 'Offer',
        'businessFunction'   => $is_rental ? 'http://purl.org/goodrelations/v1#LeaseOut' : 'http://purl.org/goodrelations/v1#Sell',
        'price'              => $price,
        'priceCurrency'      => 'EUR',
        'availability'       => HOME_STATUS_AVAILABILITY[ $status ] ?? null,
        'availabilityStarts' => $is_rental ? get_home_text( $post->ID, 'home_available_from' ) : null,
        'priceSpecification' => array_values( array_filter( $specifications ) ),
        'itemOffered'        => home_apartment_schema( $post ),
    ];

    return array_filter( $offer, static fn( $value ) => null !== $value );
}

/**
 * Turns the page entity of a single home or rental into a listing.
 *
 * @param array $graph A sequential list of graph entities.
 * @return array Filtered graph.
 */
function add_home_listing_schema( $graph ): array {
    if ( ! is_array( $graph ) ) {
        return [];
    }

    if ( ! is_singular( SHARED_POST_TYPES ) ) {
        return $graph;
    }

    $post = get_queried_object();

    if ( ! $post instanceof WP_Post ) {
        return $graph;
    }

    $offer = home_offer_schema( $post );

    foreach ( $graph as $index => $entity ) {
        if ( 'WebPage' !== ( $entity['@type'] ?? '' ) ) {
            continue;
        }

        $graph[ $index ]['@type']      = 'RealEstateListing';
        $graph[ $index ]['datePosted'] = get_the_date( 'c', $post );

        if ( $offer ) {
            $graph[ $index ]['offers'] = $offer;
        } else {
            $graph[ $index ]['about'] = home_apartment_schema( $post );
        }
    }

    return $graph;
}
add_filter( 'the_seo_framework_schema_graph_data', __NAMESPACE__ . '\add_home_listing_schema' );
