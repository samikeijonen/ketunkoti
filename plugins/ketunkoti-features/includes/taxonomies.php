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
 * Taxonomy holding the listing purpose.
 *
 * The terms themselves are editorial content, deliberately not seeded from code.
 * Nothing here branches on a specific term, so fixing a vocabulary in PHP would
 * buy nothing and cost something: seeded terms are database rows, so the name
 * would be frozen in whichever locale happened to be active at activation, and
 * renaming a term afterwards would leave it with a slug that no longer matches.
 */
const PURPOSE_TAXONOMY = 'home-purpose';

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
        [ 'home', 'rental' ],
        get_shared_taxonomy_args(
            get_taxonomy_labels(
                __( 'Sijainnit', 'ketunkoti-features' ),
                __( 'Sijainti', 'ketunkoti-features' )
            ),
            __( 'Kodin sijainti.', 'ketunkoti-features' )
        )
    );

    // Status. Shared with rentals on purpose: the status badge and the
    // `home_status` binding then work for "Vuokrattu" with no extra code.
    register_taxonomy(
        'home-status',
        [ 'home', 'rental' ],
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
        [ 'home', 'rental' ],
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
        [ 'home', 'rental' ],
        get_shared_taxonomy_args(
            get_taxonomy_labels(
                __( 'Huoneiden lukumäärät', 'ketunkoti-features' ),
                __( 'Huoneiden lukumäärä', 'ketunkoti-features' )
            ),
            __( 'Kodin huoneiden lukumäärä.', 'ketunkoti-features' )
        )
    );

    // Plot ownership, for example oma tontti or vuokratontti. Homes for sale
    // only: it matters to buyers, both investors and owner-occupiers, but not
    // to tenants.
    register_taxonomy(
        'home-plot',
        [ 'home' ],
        get_shared_taxonomy_args(
            get_taxonomy_labels(
                __( 'Tontit', 'ketunkoti-features' ),
                __( 'Tontti', 'ketunkoti-features' )
            ),
            __( 'Onko tontti oma vai vuokrattu.', 'ketunkoti-features' )
        )
    );

    // Listing purpose. Hierarchical only so that the editor renders a checkbox
    // list; the vocabulary is flat. The tag style input a non-hierarchical
    // taxonomy would give invites typo'd duplicates of a fixed three term set.
    register_taxonomy(
        PURPOSE_TAXONOMY,
        [ 'home' ],
        get_shared_taxonomy_args(
            get_taxonomy_labels(
                __( 'Käyttötarkoitukset', 'ketunkoti-features' ),
                __( 'Käyttötarkoitus', 'ketunkoti-features' )
            ),
            __( 'Onko kohde myynnissä, vuokrattavana vai sijoitusasunto.', 'ketunkoti-features' )
        )
    );
}
add_action( 'init', __NAMESPACE__ . '\register_taxonomies' );
