<?php
/**
 * Rules for the home_* meta fields of homes and rentals.
 *
 * @package Ketunkoti\AI
 */

namespace Ketunkoti\AI;

use WP_Error;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Discovers, validates and writes the home_* fields.
 *
 * The field list is read at runtime from the meta that ketunkoti-features
 * registers, so a new field there is picked up with no change here. The
 * calculated investment figures are not registered meta and so are never
 * writable.
 */
final class Home_Fields {

    /**
     * Post types that hold home listings.
     */
    public const POST_TYPES = [ 'home', 'rental' ];

    /**
     * Prefix of the editable meta keys.
     */
    private const PREFIX = 'home_';

    /**
     * Fields that usually change together: changing the key without the
     * listed fields earns a note (see related_notes()).
     */
    private const RELATED = [
        'home_rent'            => [ 'home_deposit' ],
        'home_debt_free_price' => [ 'home_selling_price' ],
        'home_selling_price'   => [ 'home_debt_free_price' ],
    ];

    /**
     * Whether the post exists and is a home or a rental.
     *
     * @param int $post_id Post ID.
     * @return bool
     */
    public static function is_home( int $post_id ): bool {
        $post = get_post( $post_id );

        return $post && in_array( $post->post_type, self::POST_TYPES, true );
    }

    /**
     * Lists the editable fields of a post type, in registration order.
     *
     * @param string $post_type Post type.
     * @return array<string, array{type: string, label: string, description: string}> Fields keyed by meta key.
     */
    public static function keys( string $post_type ): array {
        $fields = [];

        foreach ( get_registered_meta_keys( 'post', $post_type ) as $key => $args ) {
            if ( ! str_starts_with( $key, self::PREFIX ) ) {
                continue;
            }

            $fields[ $key ] = [
                'type'        => (string) ( $args['type'] ?? 'string' ),
                'label'       => (string) ( $args['label'] ?? $key ),
                'description' => (string) ( $args['description'] ?? '' ),
            ];
        }

        return $fields;
    }

    /**
     * Returns the stored values of a home's fields.
     *
     * @param int           $post_id Post ID.
     * @param string[]|null $keys    Keys to read, or null for every field of the post type.
     * @return array<string, mixed> Value per key, null when the field is not set.
     */
    public static function values( int $post_id, ?array $keys = null ): array {
        if ( null === $keys ) {
            $keys = array_keys( self::keys( (string) get_post_type( $post_id ) ) );
        }

        $values = [];
        foreach ( $keys as $key ) {
            $values[ $key ] = metadata_exists( 'post', $post_id, $key )
                ? get_post_meta( $post_id, $key, true )
                : null;
        }

        return $values;
    }

    /**
     * Validates and sanitizes field values for a home.
     *
     * Runs each field's registered sanitizer, the same one the block editor
     * uses. The ketunkoti-features sanitizers never fail: a bad value turns
     * into 0 or ''. So a non-empty text value that sanitizes to empty is
     * refused, and a number is refused when it is not numeric or the
     * sanitizer would change it (see sanitize_number()). Blank input and null
     * clear the field. One bad key refuses the whole call.
     *
     * @param int   $post_id Post ID.
     * @param array $meta    Meta key => raw value.
     * @return array<string, mixed>|WP_Error Sanitized value per key (null = delete), or an error.
     */
    public static function prepare( int $post_id, array $meta ) {
        if ( ! self::is_home( $post_id ) ) {
            return new WP_Error(
                'ketunkoti_ai_not_a_home',
                /* translators: %d: post ID. */
                sprintf( __( 'Post %d is not a home or a rental.', 'ketunkoti-ai' ), $post_id )
            );
        }

        $post_type = (string) get_post_type( $post_id );
        $fields    = self::keys( $post_type );
        $prepared  = [];

        foreach ( $meta as $key => $value ) {
            $key = (string) $key;

            if ( ! isset( $fields[ $key ] ) ) {
                return new WP_Error(
                    'ketunkoti_ai_unknown_field',
                    sprintf(
                        /* translators: 1: meta key, 2: post type, 3: comma separated list of allowed keys. */
                        __( 'The field %1$s cannot be set on a %2$s. Allowed fields: %3$s.', 'ketunkoti-ai' ),
                        $key,
                        $post_type,
                        implode( ', ', array_keys( $fields ) )
                    )
                );
            }

            if ( self::is_blank( $value ) ) {
                $prepared[ $key ] = null;
                continue;
            }

            if ( ! is_scalar( $value ) ) {
                return self::invalid_value( $fields[ $key ]['label'], $value );
            }

            if ( in_array( $fields[ $key ]['type'], [ 'integer', 'number' ], true ) ) {
                $sanitized = self::sanitize_number( $key, $value, $post_type );
            } else {
                $sanitized = sanitize_meta( $key, $value, 'post', $post_type );
                $sanitized = self::is_empty( $sanitized ) ? null : $sanitized;
            }

            if ( null === $sanitized ) {
                return self::invalid_value( $fields[ $key ]['label'], $value );
            }

            $prepared[ $key ] = $sanitized;
        }

        return $prepared;
    }

    /**
     * Writes prepared values. Null deletes the field.
     *
     * @param int   $post_id  Post ID.
     * @param array $prepared Output of prepare().
     * @return void
     */
    public static function write( int $post_id, array $prepared ): void {
        foreach ( $prepared as $key => $value ) {
            if ( null === $value ) {
                delete_post_meta( $post_id, $key );
                continue;
            }

            update_post_meta( $post_id, $key, wp_slash( $value ) );
        }
    }

    /**
     * Notes on fields that usually change with the given ones but were left out.
     *
     * For example a new rent without the deposit: the copy of a home keeps
     * the old deposit, which then no longer matches. The note asks Claude to
     * check the field with the user; nothing is changed.
     *
     * @param int      $post_id Post ID.
     * @param string[] $changed Keys written or proposed in one call.
     * @return string[] One note per related field left out.
     */
    public static function related_notes( int $post_id, array $changed ): array {
        $fields = self::keys( (string) get_post_type( $post_id ) );
        $notes  = [];

        foreach ( $changed as $key ) {
            foreach ( self::RELATED[ $key ] ?? [] as $related ) {
                if ( ! isset( $fields[ $key ], $fields[ $related ] ) || in_array( $related, $changed, true ) || isset( $notes[ $related ] ) ) {
                    continue;
                }

                $value             = self::values( $post_id, [ $related ] )[ $related ];
                $notes[ $related ] = sprintf(
                    /* translators: 1: changed field label, 2: related field label, 3: related field value. */
                    __( '"%1$s" changed but "%2$s" did not (now %3$s). Ask the user whether "%2$s" should change too.', 'ketunkoti-ai' ),
                    $fields[ $key ]['label'],
                    $fields[ $related ]['label'],
                    null === $value ? __( 'empty', 'ketunkoti-ai' ) : $value
                );
            }
        }

        return array_values( $notes );
    }

    /**
     * Whether an input means "clear the field".
     *
     * @param mixed $value Raw value.
     * @return bool
     */
    private static function is_blank( $value ): bool {
        return null === $value || ( is_string( $value ) && '' === trim( $value ) );
    }

    /**
     * Whether a sanitized value is empty, the "not set" of the sanitizers.
     *
     * @param mixed $value Sanitized value.
     * @return bool
     */
    private static function is_empty( $value ): bool {
        return '' === $value || 0 === $value || 0.0 === $value || '0' === $value;
    }

    /**
     * Sanitizes a number written with or without Finnish formatting.
     *
     * Spaces and non-breaking spaces as thousands separators are removed and
     * a decimal comma becomes a point before the field's own sanitizer runs,
     * so "250 000" stays 250000 instead of absint() reading 250. A value the
     * sanitizer would change (a negative made positive, decimals cut from an
     * integer) is refused rather than saved as something else. Zero is a
     * real value for numbers.
     *
     * @param string $key       Meta key.
     * @param mixed  $value     Raw scalar value.
     * @param string $post_type Post type.
     * @return int|float|null Sanitized number, or null when the input is not a valid number for the field.
     */
    private static function sanitize_number( string $key, $value, string $post_type ) {
        $number = str_replace( [ ' ', "\u{00A0}", "\u{202F}", ',' ], [ '', '', '', '.' ], (string) $value );
        if ( ! is_numeric( $number ) ) {
            return null;
        }

        $sanitized = sanitize_meta( $key, $number, 'post', $post_type );

        return is_numeric( $sanitized ) && abs( (float) $sanitized - (float) $number ) < 0.000001 ? $sanitized : null;
    }

    /**
     * Builds the error for a value the field cannot hold.
     *
     * @param string $label Field label.
     * @param mixed  $value Raw value.
     * @return WP_Error
     */
    private static function invalid_value( string $label, $value ): WP_Error {
        return new WP_Error(
            'ketunkoti_ai_invalid_value',
            sprintf(
                /* translators: 1: field label, 2: the refused value. */
                __( 'The value %2$s is not valid for the field "%1$s".', 'ketunkoti-ai' ),
                $label,
                is_scalar( $value ) ? '"' . $value . '"' : wp_json_encode( $value )
            )
        );
    }
}
