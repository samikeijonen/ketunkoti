<?php
/**
 * Custom post type registration.
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the custom post types.
 *
 * Called on `init` and also directly from the plugin activation routine, so
 * that rewrite rules are in place before they are flushed.
 *
 * @return void
 */
function register_post_types() {
    $labels = [
        'name'                  => __( 'Kodit', 'ketunkoti-features' ),
        'singular_name'         => __( 'Koti', 'ketunkoti-features' ),
        'menu_name'             => __( 'Kodit', 'ketunkoti-features' ),
        'all_items'             => __( 'Kaikki kodit', 'ketunkoti-features' ),
        'add_new'               => __( 'Lisää uusi koti', 'ketunkoti-features' ),
        'add_new_item'          => __( 'Lisää uusi koti', 'ketunkoti-features' ),
        'new_item'              => __( 'Uusi koti', 'ketunkoti-features' ),
        'edit_item'             => __( 'Muokkaa kotia', 'ketunkoti-features' ),
        'view_item'             => __( 'Näytä koti', 'ketunkoti-features' ),
        'view_items'            => __( 'Näytä kodit', 'ketunkoti-features' ),
        'search_items'          => __( 'Etsi koteja', 'ketunkoti-features' ),
        'not_found'             => __( 'Koteja ei löytynyt', 'ketunkoti-features' ),
        'not_found_in_trash'    => __( 'Roskakorissa ei ole koteja', 'ketunkoti-features' ),
        'attributes'            => __( 'Kodin ominaisuudet', 'ketunkoti-features' ),
        'featured_image'        => __( 'Kodin kuva', 'ketunkoti-features' ),
        'set_featured_image'    => __( 'Aseta kodin kuva', 'ketunkoti-features' ),
        'remove_featured_image' => __( 'Poista kodin kuva', 'ketunkoti-features' ),
        'use_featured_image'    => __( 'Käytä kodin kuvana', 'ketunkoti-features' ),
        'item_published'        => __( 'Koti julkaistu', 'ketunkoti-features' ),
        'item_trashed'          => __( 'Koti siirretty roskakoriin', 'ketunkoti-features' ),
        'item_updated'          => __( 'Koti on päivitetty', 'ketunkoti-features' ),
        'item_link'             => __( 'Kodin linkki', 'ketunkoti-features' ),
        'item_link_description' => __( 'Linkki kotiin', 'ketunkoti-features' ),
    ];

    $args = [
        'labels'             => $labels,
        'description'        => __( 'Kodit.', 'ketunkoti-features' ),
        'menu_icon'          => 'dashicons-admin-home',
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => true,
        'query_var'          => true,
        'rewrite'            => [ 'slug' => 'koti' ],
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => 20,
        // 'custom-fields' is required for registered post meta to appear in the
        // REST API, which the block editor needs to read and write it.
        'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'page-attributes', 'custom-fields' ],
    ];

    register_post_type( 'home', $args );
}
add_action( 'init', __NAMESPACE__ . '\register_post_types' );
