<?php
/**
 * Block bindings registration.
 *
 * Exposes the "home" meta fields to core blocks so their values can be output
 * from templates and patterns without custom blocks or shortcodes.
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Name of the block bindings source registered by this plugin.
 */
const BINDINGS_SOURCE = 'ketunkoti/home-details';

/**
 * Binding keys that read a taxonomy rather than post meta.
 *
 * Keyed by the binding key, valued by the taxonomy it reads.
 */
const TAXONOMY_FIELDS = [
    'home_city'  => 'home-city',
    'home_rooms' => 'home-rooms',
];

/**
 * Registers the block bindings source for the "home" meta fields.
 *
 * @return void
 */
function register_bindings() {
    register_block_bindings_source(
        BINDINGS_SOURCE,
        [
            'label'              => __( 'Kodin tiedot', 'ketunkoti-features' ),
            'get_value_callback' => __NAMESPACE__ . '\get_binding_value',
            'uses_context'       => [ 'postId', 'postType' ],
        ]
    );
}
add_action( 'init', __NAMESPACE__ . '\register_bindings' );

/**
 * Returns a post's term names for a taxonomy as a plain, separated list.
 *
 * The Post Terms block always renders terms as links, which is not wanted
 * everywhere. Returning the names as plain text lets a bound paragraph show
 * the same information without a link.
 *
 * @param int    $post_id  Post to read the terms from.
 * @param string $taxonomy Taxonomy to read.
 * @return string|null Separated term names, or null when the post has none.
 */
function get_term_names( int $post_id, string $taxonomy ): ?string {
    $terms = get_the_terms( $post_id, $taxonomy );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return null;
    }

    return implode( ', ', wp_list_pluck( $terms, 'name' ) );
}

/**
 * Returns a display-formatted meta value for a bound block attribute.
 *
 * The post ID comes from the block's context where available, falling back to
 * the main query. Relying on get_the_ID() alone breaks when a bound block is
 * rendered outside the main loop, such as inside a Query Loop.
 *
 * Values are returned as plain text rather than markup: these are numbers that
 * need no HTML, and plain text avoids any ambiguity about escaping in bound
 * block attributes.
 *
 * @param array    $source_args    Arguments passed to the binding, expects a 'key'.
 * @param WP_Block $block_instance The block instance being rendered.
 * @return string|null Formatted value, or null when there is nothing to show.
 */
function get_binding_value( array $source_args, $block_instance = null ): ?string {
    if ( empty( $source_args['key'] ) ) {
        return null;
    }

    $key     = $source_args['key'];
    $post_id = $block_instance->context['postId'] ?? get_the_ID();

    if ( ! $post_id ) {
        return null;
    }

    // Taxonomy backed values are not post meta, so they are resolved before
    // the meta lookup below.
    if ( isset( TAXONOMY_FIELDS[ $key ] ) ) {
        return get_term_names( $post_id, TAXONOMY_FIELDS[ $key ] );
    }

    $value = get_post_meta( $post_id, $key, true );

    if ( '' === $value || null === $value ) {
        return null;
    }

    switch ( $key ) {
        case 'home_area':
            $area = (float) $value;

            if ( $area <= 0 ) {
                return null;
            }

            /* translators: %s: Living area in square metres, for example "85,5". */
            return sprintf( __( '%s m²', 'ketunkoti-features' ), number_format_i18n( $area, 1 ) );

        case 'home_year_built':
            $year = (int) $value;

            if ( $year <= 0 ) {
                return null;
            }

            // Deliberately not run through number_format_i18n(): a year takes
            // no thousands separator, which would render 1987 as "1 987".
            return (string) $year;

        case 'home_floor':
        case 'home_address':
        case 'home_room_layout':
            // Free text fields are shown as entered.
            return (string) $value;

        case 'home_maintenance_charge':
        case 'home_capital_charge':
            $charge = (float) $value;

            // Zero stands in for "no charge entered", so render nothing.
            if ( $charge <= 0 ) {
                return null;
            }

            /* translators: %s: Monthly charge in euros, for example "285,50". */
            return sprintf( __( '%s €/kk', 'ketunkoti-features' ), number_format_i18n( $charge, 2 ) );

        case 'home_debt_free_price':
        case 'home_selling_price':
            $price = (int) $value;

            // Zero stands in for "no price entered", so render nothing.
            if ( $price <= 0 ) {
                return null;
            }

            /* translators: %s: Price in euros, for example "285 000". */
            return sprintf( __( '%s €', 'ketunkoti-features' ), number_format_i18n( $price ) );

        default:
            return null;
    }
}
