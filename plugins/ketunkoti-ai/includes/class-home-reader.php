<?php
/**
 * Read model of homes and rentals for the abilities.
 *
 * @package Ketunkoti\AI
 */

namespace Ketunkoti\AI;

use MEOM\AI_Assistant\Ownership;
use WP_Error;
use WP_Query;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Builds the responses of ketunkoti/get-home and ketunkoti/list-homes.
 */
final class Home_Reader {

    /**
     * Default page size of list().
     */
    private const DEFAULT_LIMIT = 20;

    /**
     * Largest page size of list().
     */
    private const MAX_LIMIT = 100;

    /**
     * Returns a whole home: post fields, home_* fields, terms and figures.
     *
     * @param int $post_id Post ID.
     * @return array|WP_Error Home data, or an error when the post is not a home.
     */
    public static function get( int $post_id ) {
        if ( ! Home_Fields::is_home( $post_id ) ) {
            return new WP_Error(
                'ketunkoti_ai_not_a_home',
                /* translators: %d: post ID. */
                sprintf( __( 'Post %d is not a home or a rental.', 'ketunkoti-ai' ), $post_id )
            );
        }

        $post   = get_post( $post_id );
        $values = Home_Fields::values( $post_id );
        $fields = [];

        foreach ( Home_Fields::keys( $post->post_type ) as $key => $field ) {
            $fields[ $key ] = [
                'label'       => $field['label'],
                'type'        => $field['type'],
                'description' => $field['description'],
                'value'       => $values[ $key ],
            ];
        }

        return [
            'id'             => $post->ID,
            'post_type'      => $post->post_type,
            'status'         => $post->post_status,
            'title'          => $post->post_title,
            'excerpt'        => $post->post_excerpt,
            'edit_link'      => get_edit_post_link( $post->ID, 'raw' ),
            'featured_media' => self::featured_media( $post->ID ),
            'fields'         => $fields,
            'terms'          => self::terms( $post ),
            'calculated'     => self::calculated( $post ),
            'ai_owned'       => Ownership::is_ai_owned( $post->ID, get_current_user_id() ),
        ];
    }

    /**
     * Lists homes and rentals as compact rows.
     *
     * @param array $args Optional `post_type`, `status`, `search`, `limit` and `offset`.
     * @return array{total: int, items: array} Matching rows and their total count.
     */
    public static function list( array $args ): array {
        $post_type = in_array( $args['post_type'] ?? '', Home_Fields::POST_TYPES, true )
            ? $args['post_type']
            : Home_Fields::POST_TYPES;

        $query = new WP_Query(
            [
                'post_type'      => $post_type,
                'post_status'    => sanitize_key( $args['status'] ?? 'any' ),
                's'              => sanitize_text_field( $args['search'] ?? '' ),
                'posts_per_page' => min( self::MAX_LIMIT, max( 1, (int) ( $args['limit'] ?? self::DEFAULT_LIMIT ) ) ),
                'offset'         => max( 0, (int) ( $args['offset'] ?? 0 ) ),
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]
        );

        $items = [];
        foreach ( $query->posts as $post ) {
            $items[] = [
                'id'        => $post->ID,
                'post_type' => $post->post_type,
                'title'     => $post->post_title,
                'status'    => $post->post_status,
                'city'      => self::first_term_name( $post->ID, 'home-city' ),
                'rooms'     => self::first_term_name( $post->ID, 'home-rooms' ),
            ];
        }

        return [
            'total' => (int) $query->found_posts,
            'items' => $items,
        ];
    }

    /**
     * Returns the featured image of a post.
     *
     * @param int $post_id Post ID.
     * @return array{id: int, url: string, alt: string}|null Image, or null without one.
     */
    private static function featured_media( int $post_id ): ?array {
        $image_id = (int) get_post_thumbnail_id( $post_id );
        if ( ! $image_id ) {
            return null;
        }

        return [
            'id'  => $image_id,
            'url' => (string) wp_get_attachment_image_url( $image_id, 'large' ),
            'alt' => (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
        ];
    }

    /**
     * Returns the post's terms in every taxonomy of its type.
     *
     * @param \WP_Post $post Post.
     * @return array<string, array<int, array{id: int, name: string}>> Terms per taxonomy.
     */
    private static function terms( \WP_Post $post ): array {
        $terms = [];

        foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
            $found              = wp_get_object_terms( $post->ID, $taxonomy );
            $terms[ $taxonomy ] = is_wp_error( $found ) ? [] : array_map(
                static fn( \WP_Term $term ): array => [
                    'id'   => $term->term_id,
                    'name' => $term->name,
                ],
                $found
            );
        }

        return $terms;
    }

    /**
     * Returns the investment figures ketunkoti-features calculates for a home.
     *
     * They are read-only: computed from the other fields, never stored.
     *
     * @param \WP_Post $post Post.
     * @return array<string, string|null>|null Formatted figures, or null for a rental.
     */
    private static function calculated( \WP_Post $post ): ?array {
        if ( 'home' !== $post->post_type ) {
            return null;
        }

        return [
            'home_rental_yield'  => \Ketunkoti\get_rental_yield( $post->ID ),
            'home_cash_flow'     => \Ketunkoti\get_cash_flow( $post->ID ),
            'home_equity_return' => \Ketunkoti\get_equity_return( $post->ID ),
        ];
    }

    /**
     * Returns the name of the post's first term in a taxonomy.
     *
     * @param int    $post_id  Post ID.
     * @param string $taxonomy Taxonomy.
     * @return string|null Term name, or null without terms.
     */
    private static function first_term_name( int $post_id, string $taxonomy ): ?string {
        $terms = get_the_terms( $post_id, $taxonomy );

        return is_array( $terms ) && $terms ? $terms[0]->name : null;
    }
}
