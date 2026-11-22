<?php
/**
 * Salkku: a private apartment portfolio in wp-admin.
 *
 * The post type and taxonomy are closed on every public path and gated by
 * `manage_options`. Nothing here is reachable by a visitor, the REST API, the
 * sitemap, search or a feed.
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Post type key of one apartment in the portfolio.
 */
const PORTFOLIO_POST_TYPE = 'portfolio';

/**
 * Taxonomy key of the apartment owner.
 */
const PORTFOLIO_OWNER_TAXONOMY = 'portfolio-owner';

/**
 * Overview label for an apartment that has no owner term.
 */
const PORTFOLIO_NO_OWNER = 'Ei omistajaa';

/**
 * Returns the portfolio meta fields in edit screen order.
 *
 * Each entry is keyed by meta key and holds the Finnish label, the value type
 * (`string`, `number`, `integer` or `date`) and the group heading it appears
 * under on the edit screen.
 *
 * @return array<string, array{label: string, type: string, group: string}> Field definitions.
 */
function get_portfolio_fields(): array {
    $basics  = __( 'Perustiedot', 'ketunkoti-features' );
    $debts   = __( 'Velat', 'ketunkoti-features' );
    $rent    = __( 'Vuokra ja vastikkeet', 'ketunkoti-features' );
    $value   = __( 'Arvo ja vakuudet', 'ketunkoti-features' );
    $listing = __( 'Myynti-ilmoitus', 'ketunkoti-features' );

    return [
        'portfolio_address'              => [
            'label' => __( 'Osoite', 'ketunkoti-features' ),
            'type'  => 'string',
            'group' => $basics,
        ],
        'portfolio_area'                 => [
            'label' => __( 'Pinta-ala (m²)', 'ketunkoti-features' ),
            'type'  => 'number',
            'group' => $basics,
        ],
        'portfolio_year_built'           => [
            'label' => __( 'Valmistunut', 'ketunkoti-features' ),
            'type'  => 'integer',
            'group' => $basics,
        ],
        'portfolio_floor'                => [
            'label' => __( 'Kerros', 'ketunkoti-features' ),
            'type'  => 'string',
            'group' => $basics,
        ],
        'portfolio_debt_free_price'      => [
            'label' => __( 'Ostohinta (velaton)', 'ketunkoti-features' ),
            'type'  => 'number',
            'group' => $debts,
        ],
        'portfolio_company_loan'         => [
            'label' => __( 'Yhtiölainaa', 'ketunkoti-features' ),
            'type'  => 'number',
            'group' => $debts,
        ],
        'portfolio_company_loan_checked' => [
            'label' => __( 'Yhtiölainan tarkastuspäivä', 'ketunkoti-features' ),
            'type'  => 'date',
            'group' => $debts,
        ],
        'portfolio_bank_loan'            => [
            'label' => __( 'Pankkilainaa', 'ketunkoti-features' ),
            'type'  => 'number',
            'group' => $debts,
        ],
        'portfolio_used_collateral'      => [
            'label' => __( 'Käytetyt vakuudet', 'ketunkoti-features' ),
            'type'  => 'number',
            'group' => $value,
        ],
        'portfolio_rent'                 => [
            'label' => __( 'Vuokra + vesi (€/kk)', 'ketunkoti-features' ),
            'type'  => 'number',
            'group' => $rent,
        ],
        'portfolio_charges'              => [
            'label' => __( 'Vastikkeet (€/kk)', 'ketunkoti-features' ),
            'type'  => 'number',
            'group' => $rent,
        ],
        'portfolio_estimated_value'      => [
            'label' => __( 'Arvioitu arvo', 'ketunkoti-features' ),
            'type'  => 'number',
            'group' => $value,
        ],
        'portfolio_collateral_rate'      => [
            'label' => __( 'Vakuusarvo-%', 'ketunkoti-features' ),
            'type'  => 'number',
            'group' => $value,
        ],
        'portfolio_home_id'              => [
            'label' => __( 'Myynti-ilmoitus', 'ketunkoti-features' ),
            'type'  => 'integer',
            'group' => $listing,
        ],
    ];
}

/**
 * Returns the sanitize callback for a portfolio field type.
 *
 * Reuses the sanitizers of `meta.php`, so Finnish input such as "1 285,50"
 * works. The home ID is a post ID, so `absint` fits it better than a year.
 *
 * @param string $meta_key Meta key.
 * @param string $type     Field type.
 * @return callable Sanitize callback.
 */
function get_portfolio_sanitize_callback( string $meta_key, string $type ): callable {
    return match ( true ) {
        'portfolio_home_id' === $meta_key => 'absint',
        'number' === $type                => __NAMESPACE__ . '\sanitize_positive_number',
        'integer' === $type               => __NAMESPACE__ . '\sanitize_year',
        'date' === $type                  => __NAMESPACE__ . '\sanitize_date',
        default                           => 'sanitize_text_field',
    };
}

/**
 * Permission callback for the portfolio meta fields.
 *
 * @return bool True when the current user has the `manage_options` capability.
 */
function portfolio_meta_auth_callback(): bool {
    return current_user_can( 'manage_options' );
}

/**
 * Registers the portfolio post type, the owner taxonomy and the meta fields.
 *
 * Called on `init` and also directly from the plugin activation routine. The
 * post type has no rewrite rules, so the flush is only for consistency.
 *
 * @return void
 */
function register_portfolio(): void {
    $capabilities = [];

    foreach (
        [
            'edit_post',
            'read_post',
            'delete_post',
            'edit_posts',
            'edit_others_posts',
            'delete_posts',
            'publish_posts',
            'read_private_posts',
            'delete_private_posts',
            'delete_published_posts',
            'delete_others_posts',
            'edit_private_posts',
            'edit_published_posts',
            'create_posts',
        ] as $capability
    ) {
        $capabilities[ $capability ] = 'manage_options';
    }

    register_post_type(
        PORTFOLIO_POST_TYPE,
        [
            'labels'              => [
                'name'          => __( 'Asunnot', 'ketunkoti-features' ),
                'singular_name' => __( 'Asunto', 'ketunkoti-features' ),
                'menu_name'     => __( 'Salkku', 'ketunkoti-features' ),
                'all_items'     => __( 'Kaikki asunnot', 'ketunkoti-features' ),
                'add_new'       => __( 'Lisää uusi asunto', 'ketunkoti-features' ),
                'add_new_item'  => __( 'Lisää uusi asunto', 'ketunkoti-features' ),
                'new_item'      => __( 'Uusi asunto', 'ketunkoti-features' ),
                'edit_item'     => __( 'Muokkaa asuntoa', 'ketunkoti-features' ),
                'search_items'  => __( 'Etsi asuntoja', 'ketunkoti-features' ),
                'not_found'     => __( 'Asuntoja ei löytynyt', 'ketunkoti-features' ),
            ],
            'description'         => __( 'Yksityinen asuntosalkku.', 'ketunkoti-features' ),
            'menu_icon'           => 'dashicons-portfolio',
            'public'              => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'query_var'           => false,
            'rewrite'             => false,
            'has_archive'         => false,
            'show_in_rest'        => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_nav_menus'   => false,
            'show_in_admin_bar'   => false,
            'can_export'          => true,
            'hierarchical'        => false,
            'supports'            => [ 'title', 'revisions' ],
            'capabilities'        => $capabilities,
            'map_meta_cap'        => false,
        ]
    );

    register_taxonomy(
        PORTFOLIO_OWNER_TAXONOMY,
        PORTFOLIO_POST_TYPE,
        [
            'labels'             => get_taxonomy_labels(
                __( 'Omistajat', 'ketunkoti-features' ),
                __( 'Omistaja', 'ketunkoti-features' )
            ),
            'hierarchical'       => true,
            'public'             => false,
            'publicly_queryable' => false,
            'show_in_rest'       => false,
            'show_ui'            => true,
            'show_admin_column'  => true,
            'query_var'          => false,
            'rewrite'            => false,
            'meta_box_cb'        => false,
            'capabilities'       => [
                'manage_terms' => 'manage_options',
                'edit_terms'   => 'manage_options',
                'delete_terms' => 'manage_options',
                'assign_terms' => 'manage_options',
            ],
        ]
    );

    foreach ( get_portfolio_fields() as $meta_key => $field ) {
        register_post_meta(
            PORTFOLIO_POST_TYPE,
            $meta_key,
            [
                'type'              => 'date' === $field['type'] ? 'string' : $field['type'],
                'label'             => $field['label'],
                'single'            => true,
                'show_in_rest'      => false,
                'sanitize_callback' => get_portfolio_sanitize_callback( $meta_key, $field['type'] ),
                'auth_callback'     => __NAMESPACE__ . '\portfolio_meta_auth_callback',
            ]
        );
    }
}
add_action( 'init', __NAMESPACE__ . '\register_portfolio' );

/**
 * Returns the saved field values of one apartment, typed.
 *
 * Numbers come back as float, integers as int and the rest as string. A field
 * that has never been saved, or is saved empty, is `null`, so the calculator
 * can tell "not entered" from zero.
 *
 * @param int $post_id Portfolio post ID.
 * @return array<string, float|int|string|null> Meta key to typed value.
 */
function get_portfolio_field_values( int $post_id ): array {
    $values = [];

    foreach ( get_portfolio_fields() as $meta_key => $field ) {
        $raw = get_post_meta( $post_id, $meta_key, true );

        if ( '' === $raw || false === $raw || null === $raw ) {
            $values[ $meta_key ] = null;
            continue;
        }

        $values[ $meta_key ] = match ( $field['type'] ) {
            'number'  => (float) $raw,
            'integer' => (int) $raw,
            default   => (string) $raw,
        };
    }

    return $values;
}
