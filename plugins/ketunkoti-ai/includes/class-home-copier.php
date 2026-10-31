<?php
/**
 * Copies a home or rental into a new draft owned by the assistant.
 *
 * @package Ketunkoti\AI
 */

namespace Ketunkoti\AI;

use MEOM\AI_Assistant\Ownership;
use WP_Error;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Duplicates a listing exactly: content, excerpt, menu order, template,
 * featured image, every home_* field and every term. Nothing else is copied,
 * so SEO meta, edit locks and other plugins' private meta start fresh.
 */
final class Home_Copier {

    /**
     * Copies a home into a new draft that the assistant may edit freely.
     *
     * @param int         $source_id Post ID of the home to copy.
     * @param int         $user_id   User who becomes the author and owner.
     * @param string|null $title     Title of the copy; default is the source title with " (kopio)".
     * @return int|WP_Error ID of the new draft, or an error.
     */
    public static function copy( int $source_id, int $user_id, ?string $title = null ) {
        if ( ! Home_Fields::is_home( $source_id ) ) {
            return new WP_Error(
                'ketunkoti_ai_not_a_home',
                /* translators: %d: post ID. */
                sprintf( __( 'Post %d is not a home or a rental.', 'ketunkoti-ai' ), $source_id )
            );
        }

        $source = get_post( $source_id );
        $title  = null === $title || '' === trim( $title )
            ? $source->post_title . ' ' . __( '(kopio)', 'ketunkoti-ai' )
            : sanitize_text_field( $title );

        $copy_id = wp_insert_post(
            wp_slash(
                [
                    'post_type'    => $source->post_type,
                    'post_status'  => 'draft',
                    'post_author'  => $user_id,
                    'post_title'   => $title,
                    'post_content' => $source->post_content,
                    'post_excerpt' => $source->post_excerpt,
                    'menu_order'   => $source->menu_order,
                ]
            ),
            true
        );

        if ( is_wp_error( $copy_id ) ) {
            return $copy_id;
        }

        self::copy_meta( $source_id, $copy_id );
        self::copy_terms( $source, $copy_id );

        // Last, so the ownership fingerprint matches the finished copy.
        Ownership::mark( $copy_id, $user_id );

        return $copy_id;
    }

    /**
     * Copies the home_* fields, the template and the featured image.
     *
     * The template is written as meta: wp_insert_post() refuses a template
     * the current theme does not list, and the copy must match the source.
     *
     * @param int $source_id Source post ID.
     * @param int $copy_id   Copy post ID.
     * @return void
     */
    private static function copy_meta( int $source_id, int $copy_id ): void {
        foreach ( Home_Fields::values( $source_id ) as $key => $value ) {
            if ( null !== $value ) {
                update_post_meta( $copy_id, $key, wp_slash( $value ) );
            }
        }

        $template = get_post_meta( $source_id, '_wp_page_template', true );
        if ( '' !== $template ) {
            update_post_meta( $copy_id, '_wp_page_template', wp_slash( $template ) );
        }

        $image_id = (int) get_post_thumbnail_id( $source_id );
        if ( $image_id && get_post( $image_id ) ) {
            update_post_meta( $copy_id, '_thumbnail_id', $image_id );
        }
    }

    /**
     * Copies the terms of every taxonomy of the post type.
     *
     * @param \WP_Post $source  Source post.
     * @param int      $copy_id Copy post ID.
     * @return void
     */
    private static function copy_terms( \WP_Post $source, int $copy_id ): void {
        foreach ( get_object_taxonomies( $source->post_type ) as $taxonomy ) {
            $term_ids = wp_get_object_terms( $source->ID, $taxonomy, [ 'fields' => 'ids' ] );
            if ( ! is_wp_error( $term_ids ) && $term_ids ) {
                wp_set_object_terms( $copy_id, array_map( 'intval', $term_ids ), $taxonomy );
            }
        }
    }
}
