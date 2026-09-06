<?php
/**
 * Custom taxonomy registration.
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Returns the shared arguments used by every taxonomy in this plugin.
 *
 * The taxonomies are not public, so they get no pretty term archives. They are
 * publicly queryable on purpose: the Query Loop and Post Terms blocks only list
 * taxonomies whose `publicly_queryable` visibility flag is true.
 *
 * @param array $labels      Taxonomy labels.
 * @param string $description Taxonomy description.
 * @return array Arguments for register_taxonomy().
 */
function get_shared_taxonomy_args( array $labels, string $description ): array {
    return [
        'labels'             => $labels,
        'description'        => $description,
        'hierarchical'       => true,
        'public'             => false,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => true,
        'show_admin_column'  => true,
        'query_var'          => true,
        'rewrite'            => false,
    ];
}

/**
 * Builds a full label set for a hierarchical taxonomy.
 *
 * @param string $plural   Plural taxonomy name, already translated.
 * @param string $singular Singular taxonomy name, already translated.
 * @return array Taxonomy labels.
 */
function get_taxonomy_labels( string $plural, string $singular ): array {
    return [
        'name'              => $plural,
        'singular_name'     => $singular,
        'menu_name'         => $plural,
        'all_items'         => $plural,
        'edit_item'         => sprintf(
            /* translators: %s: Singular taxonomy name, for example "Sijainti". */
            __( 'Muokkaa: %s', 'ketunkoti-features' ),
            $singular
        ),
        'view_item'         => sprintf(
            /* translators: %s: Singular taxonomy name, for example "Sijainti". */
            __( 'Näytä: %s', 'ketunkoti-features' ),
            $singular
        ),
        'update_item'       => sprintf(
            /* translators: %s: Singular taxonomy name, for example "Sijainti". */
            __( 'Päivitä: %s', 'ketunkoti-features' ),
            $singular
        ),
        'add_new_item'      => sprintf(
            /* translators: %s: Singular taxonomy name, for example "Sijainti". */
            __( 'Lisää uusi: %s', 'ketunkoti-features' ),
            $singular
        ),
        'new_item_name'     => sprintf(
            /* translators: %s: Singular taxonomy name, for example "Sijainti". */
            __( 'Uusi nimi: %s', 'ketunkoti-features' ),
            $singular
        ),
        'parent_item'       => sprintf(
            /* translators: %s: Singular taxonomy name, for example "Sijainti". */
            __( 'Ylätaso: %s', 'ketunkoti-features' ),
            $singular
        ),
        'parent_item_colon' => sprintf(
            /* translators: %s: Singular taxonomy name, for example "Sijainti". */
            __( 'Ylätaso: %s:', 'ketunkoti-features' ),
            $singular
        ),
        'search_items'      => sprintf(
            /* translators: %s: Plural taxonomy name, for example "Sijainnit". */
            __( 'Etsi: %s', 'ketunkoti-features' ),
            $plural
        ),
        'not_found'         => __( 'Ei tuloksia.', 'ketunkoti-features' ),
        'back_to_items'     => sprintf(
            /* translators: %s: Plural taxonomy name, for example "Sijainnit". */
            __( 'Takaisin: %s', 'ketunkoti-features' ),
            $plural
        ),
    ];
}

/**
 * Registers the custom taxonomies for the "home" post type.
 *
 * Called on `init` and also directly from the plugin activation routine, so
 * that any future rewrite rules are in place before they are flushed.
 *
 * @return void
 */
function register_taxonomies() {
    // Location.
    register_taxonomy(
        'home-city',
        [ 'home' ],
        get_shared_taxonomy_args(
            get_taxonomy_labels(
                __( 'Sijainnit', 'ketunkoti-features' ),
                __( 'Sijainti', 'ketunkoti-features' )
            ),
            __( 'Kodin sijainti.', 'ketunkoti-features' )
        )
    );

    // Status.
    register_taxonomy(
        'home-status',
        [ 'home' ],
        get_shared_taxonomy_args(
            get_taxonomy_labels(
                __( 'Status', 'ketunkoti-features' ),
                __( 'Status', 'ketunkoti-features' )
            ),
            __( 'Kodin status.', 'ketunkoti-features' )
        )
    );

    // Type.
    register_taxonomy(
        'home-type',
        [ 'home' ],
        get_shared_taxonomy_args(
            get_taxonomy_labels(
                __( 'Tyypit', 'ketunkoti-features' ),
                __( 'Tyyppi', 'ketunkoti-features' )
            ),
            __( 'Kodin tyyppi.', 'ketunkoti-features' )
        )
    );

    // Number of rooms. A taxonomy rather than meta so that homes can be
    // grouped and filtered by room count; the exact layout lives in the
    // `home_room_layout` meta field.
    register_taxonomy(
        'home-rooms',
        [ 'home' ],
        get_shared_taxonomy_args(
            get_taxonomy_labels(
                __( 'Huoneiden lukumäärät', 'ketunkoti-features' ),
                __( 'Huoneiden lukumäärä', 'ketunkoti-features' )
            ),
            __( 'Kodin huoneiden lukumäärä.', 'ketunkoti-features' )
        )
    );
}
add_action( 'init', __NAMESPACE__ . '\register_taxonomies' );
