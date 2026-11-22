<?php
/**
 * Salkku snapshots: monthly frozen copies of the portfolio.
 *
 * A snapshot stores the money figures of every apartment and the stored totals
 * as they were when it was taken. History is never recalculated on read. The
 * post type is closed exactly like `portfolio` and gated by `manage_options`.
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Post type key of one portfolio snapshot.
 */
const PORTFOLIO_SNAPSHOT_POST_TYPE = 'portfolio_snapshot';

/**
 * Meta key of the snapshot month (`YYYY-MM`), used to find a month's snapshot.
 */
const PORTFOLIO_SNAPSHOT_MONTH_META = 'portfolio_snapshot_month';

/**
 * Meta key of the stored snapshot data array.
 */
const PORTFOLIO_SNAPSHOT_DATA_META = 'portfolio_snapshot_data';

/**
 * Registers the snapshot post type and its two meta keys.
 *
 * Registered like `portfolio` in privacy terms: no public output of any kind.
 * Creating posts by hand is disabled, so snapshots only come from the code.
 *
 * @return void
 */
function register_portfolio_snapshots(): void {
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
        ] as $capability
    ) {
        $capabilities[ $capability ] = 'manage_options';
    }

    $capabilities['create_posts'] = 'do_not_allow';

    register_post_type(
        PORTFOLIO_SNAPSHOT_POST_TYPE,
        [
            'labels'              => [
                'name'          => __( 'Tilannekuvat', 'ketunkoti-features' ),
                'singular_name' => __( 'Tilannekuva', 'ketunkoti-features' ),
                'menu_name'     => __( 'Tilannekuvat', 'ketunkoti-features' ),
                'all_items'     => __( 'Tilannekuvat', 'ketunkoti-features' ),
                'edit_item'     => __( 'Muokkaa tilannekuvaa', 'ketunkoti-features' ),
                'search_items'  => __( 'Etsi tilannekuvia', 'ketunkoti-features' ),
                'not_found'     => __( 'Tilannekuvia ei löytynyt', 'ketunkoti-features' ),
            ],
            'description'         => __( 'Salkun kuukausittaiset tilannekuvat.', 'ketunkoti-features' ),
            'public'              => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'query_var'           => false,
            'rewrite'             => false,
            'has_archive'         => false,
            'show_in_rest'        => false,
            'show_ui'             => true,
            'show_in_menu'        => 'edit.php?post_type=' . PORTFOLIO_POST_TYPE,
            'show_in_nav_menus'   => false,
            'show_in_admin_bar'   => false,
            'can_export'          => true,
            'hierarchical'        => false,
            'supports'            => [ 'title' ],
            'capabilities'        => $capabilities,
            'map_meta_cap'        => false,
        ]
    );

    register_post_meta(
        PORTFOLIO_SNAPSHOT_POST_TYPE,
        PORTFOLIO_SNAPSHOT_MONTH_META,
        [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => __NAMESPACE__ . '\portfolio_meta_auth_callback',
        ]
    );

    register_post_meta(
        PORTFOLIO_SNAPSHOT_POST_TYPE,
        PORTFOLIO_SNAPSHOT_DATA_META,
        [
            'type'          => 'object',
            'single'        => true,
            'show_in_rest'  => false,
            'auth_callback' => __NAMESPACE__ . '\portfolio_meta_auth_callback',
        ]
    );
}
add_action( 'init', __NAMESPACE__ . '\register_portfolio_snapshots', 11 );

/**
 * Returns the month of a timestamp in the site timezone.
 *
 * @param int $timestamp Unix timestamp.
 * @return string Month as `YYYY-MM`.
 */
function get_portfolio_snapshot_month( int $timestamp ): string {
    return wp_date( 'Y-m', $timestamp );
}

/**
 * Builds the stored row of one apartment.
 *
 * Only money figures are kept: address, area, year built and floor are left
 * out on purpose, so history holds as little personal data as it can.
 *
 * @param int                  $id      Apartment post ID.
 * @param string               $title   Raw apartment title.
 * @param string               $owner   Owner name or the no-owner label.
 * @param array<string, mixed> $fields  Typed apartment fields.
 * @param array<string, float> $results Calculated figures.
 * @param bool                 $counted Whether the apartment feeds the totals.
 * @return array<string, mixed> Stored apartment row.
 */
function build_portfolio_snapshot_apartment( int $id, string $title, string $owner, array $fields, array $results, bool $counted ): array {
    return [
        'id'                   => $id,
        'title'                => $title,
        'owner'                => $owner,
        'status'               => (string) get_post_status( $id ),
        'estimated_value'      => $fields['portfolio_estimated_value'],
        'company_loan'         => $fields['portfolio_company_loan'],
        'bank_loan'            => $fields['portfolio_bank_loan'],
        'used_collateral'      => $fields['portfolio_used_collateral'],
        'collateral_rate'      => $fields['portfolio_collateral_rate'],
        'rent'                 => $fields['portfolio_rent'],
        'charges'              => $fields['portfolio_charges'],
        'debt_free_price'      => $fields['portfolio_debt_free_price'],
        'company_loan_checked' => $fields['portfolio_company_loan_checked'],
        'debts'                => $results['debts'],
        'collateral_value'     => $results['collateral_value'],
        'free_collateral'      => $results['free_collateral'],
        'net_value'            => $results['net_value'],
        'counted'              => $counted,
    ];
}

/**
 * Builds the snapshot data of the portfolio as it is right now.
 *
 * Totals come from `get_portfolio_overview()`, so a snapshot equals what the
 * summary page showed. Apartments that the overview leaves out of the totals
 * (draft, pending, future) are read and calculated here and stored with
 * `counted => false`; they never feed the stored totals.
 *
 * @param string $trigger   What took the snapshot: `cron` or `manual`.
 * @param int    $timestamp Moment the snapshot is taken.
 * @return array<string, mixed> Snapshot data, shape version 1.
 */
function build_portfolio_snapshot_data( string $trigger, int $timestamp ): array {
    $overview   = get_portfolio_overview();
    $apartments = [];
    $groups     = [];

    foreach ( $overview['groups'] as $group ) {
        foreach ( $group['apartments'] as $apartment ) {
            $apartments[] = build_portfolio_snapshot_apartment(
                (int) $apartment['id'],
                (string) $apartment['title'],
                (string) $group['name'],
                $apartment['fields'],
                $apartment['results'],
                true
            );
        }

        foreach ( $group['drafts'] as $draft ) {
            $fields       = get_portfolio_field_values( (int) $draft['id'] );
            $apartments[] = build_portfolio_snapshot_apartment(
                (int) $draft['id'],
                (string) $draft['title'],
                (string) $group['name'],
                $fields,
                calculate_portfolio_apartment( $fields ),
                false
            );
        }

        $groups[] = [
            'name'   => (string) $group['name'],
            'totals' => $group['totals'],
        ];
    }

    return [
        'version'    => 1,
        'month'      => get_portfolio_snapshot_month( $timestamp ),
        'taken_at'   => gmdate( 'c', $timestamp ),
        'trigger'    => $trigger,
        'apartments' => $apartments,
        'groups'     => $groups,
        'combined'   => $overview['combined'],
    ];
}

/**
 * Returns the ID of the published snapshot of a month.
 *
 * Trashed and other non-published snapshots are ignored.
 *
 * @param string $month Month as `YYYY-MM`.
 * @return int Snapshot post ID, or 0 when the month has none.
 */
function get_portfolio_snapshot_id( string $month ): int {
    $ids = get_posts(
        [
            'post_type'              => PORTFOLIO_SNAPSHOT_POST_TYPE,
            'post_status'            => 'publish',
            'meta_key'               => PORTFOLIO_SNAPSHOT_MONTH_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- A handful of posts.
            'meta_value'             => $month, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- A handful of posts.
            'posts_per_page'         => 1,
            'orderby'                => 'ID',
            'order'                  => 'ASC',
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
        ]
    );

    return empty( $ids ) ? 0 : (int) $ids[0];
}

/**
 * Saves the snapshot of the month a timestamp falls in.
 *
 * A month that already has a published snapshot is replaced in place: same
 * post, new data, date and trigger. A trashed snapshot is ignored, so its
 * month gets a new post.
 *
 * @param string   $trigger   What takes the snapshot: `cron` or `manual`.
 * @param int|null $timestamp Moment of the snapshot, now when null.
 * @return int|\WP_Error Snapshot post ID, or an error when saving failed.
 */
function save_portfolio_snapshot( string $trigger, ?int $timestamp = null ): int|\WP_Error {
    if ( ! in_array( $trigger, [ 'cron', 'manual' ], true ) ) {
        return new \WP_Error(
            'ketunkoti_portfolio_invalid_trigger',
            __( 'Tilannekuvan tallennustapa ei kelpaa.', 'ketunkoti-features' )
        );
    }

    $timestamp = $timestamp ?? time();
    $data      = build_portfolio_snapshot_data( $trigger, $timestamp );
    $month     = $data['month'];
    $existing  = get_portfolio_snapshot_id( $month );

    $post = [
        'post_type'     => PORTFOLIO_SNAPSHOT_POST_TYPE,
        'post_status'   => 'publish',
        'post_title'    => $month,
        'post_date'     => wp_date( 'Y-m-d H:i:s', $timestamp ),
        'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $timestamp ),
        'post_author'   => get_current_user_id(),
    ];

    /*
     * A snapshot is never scheduled. WordPress turns a publish post with a
     * date ahead of now into `future`, so keep it published for the save.
     */
    $keep_published = static function ( array $post_data ): array {
        if ( PORTFOLIO_SNAPSHOT_POST_TYPE === ( $post_data['post_type'] ?? '' ) && 'future' === ( $post_data['post_status'] ?? '' ) ) {
            $post_data['post_status'] = 'publish';
        }

        return $post_data;
    };
    add_filter( 'wp_insert_post_data', $keep_published );

    if ( $existing > 0 ) {
        $post['ID'] = $existing;
        $post_id    = wp_update_post( wp_slash( $post ), true );
    } else {
        $post_id = wp_insert_post( wp_slash( $post ), true );
    }

    remove_filter( 'wp_insert_post_data', $keep_published );

    if ( is_wp_error( $post_id ) ) {
        return $post_id;
    }

    // Meta values are unslashed on save, so slash them to keep backslashes in titles.
    update_post_meta( $post_id, PORTFOLIO_SNAPSHOT_MONTH_META, $month );
    update_post_meta( $post_id, PORTFOLIO_SNAPSHOT_DATA_META, wp_slash( $data ) );

    return (int) $post_id;
}

/**
 * Returns the stored data of the published snapshots, oldest month first.
 *
 * Entries whose data is not an array with `month` and `combined` are skipped.
 * Figures are returned as stored, never recalculated.
 *
 * @param string|null $from First month to include (`YYYY-MM`), or null for no lower bound.
 * @param string|null $to   Last month to include (`YYYY-MM`), or null for no upper bound.
 * @return array<int, array<string, mixed>> Snapshot data arrays.
 */
function get_portfolio_snapshots( ?string $from = null, ?string $to = null ): array {
    $ids = get_posts(
        [
            'post_type'              => PORTFOLIO_SNAPSHOT_POST_TYPE,
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
        ]
    );

    $snapshots = [];

    foreach ( $ids as $id ) {
        $data = get_post_meta( (int) $id, PORTFOLIO_SNAPSHOT_DATA_META, true );

        if ( ! is_array( $data ) || ! isset( $data['month'], $data['combined'] ) ) {
            continue;
        }

        if ( ( null !== $from && $data['month'] < $from ) || ( null !== $to && $data['month'] > $to ) ) {
            continue;
        }

        $snapshots[] = $data;
    }

    usort(
        $snapshots,
        static fn ( array $a, array $b ): int => strcmp( (string) $a['month'], (string) $b['month'] )
    );

    return $snapshots;
}
