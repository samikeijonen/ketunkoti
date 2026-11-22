<?php
/**
 * The Salkku (portfolio) abilities.
 *
 * @package Ketunkoti\AI
 */

namespace Ketunkoti\AI;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the portfolio abilities: read the portfolio, add an apartment,
 * change an apartment. They are MCP-only (never a chat or REST ability) and
 * limited to administrators, because the portfolio is private.
 *
 * Validation of values lives in Ketun koti Features
 * (`Ketunkoti\validate_portfolio_fields()`); this class only shapes input
 * and output, checks locks and logs writes.
 */
final class Portfolio_Abilities {

    /**
     * Post meta that holds the write log: one list, one row per write.
     */
    public const LOG_META = '_ketunkoti_ai_portfolio_log';

    /**
     * Registers every ability. Runs on `wp_abilities_api_init`.
     *
     * @return void
     */
    public static function register(): void {
        if ( ! function_exists( 'Ketunkoti\get_portfolio_fields' ) ) {
            return;
        }

        Registrar::register(
            'ketunkoti/get-portfolio',
            [
                'label'               => __( 'Get portfolio', 'ketunkoti-ai' ),
                'description'         => __( 'Returns the private apartment portfolio (Salkku): per owner the published and private apartments with every field (label and value) and the calculated results, the drafts, pending and scheduled apartments (each with a status) that are left out of the totals, and the totals per owner and combined. Pass owner (name or slug) to get one owner only; combined is then that owner\'s totals. Read-only.', 'ketunkoti-ai' ),
                'input_schema'        => [
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => [
                        'owner' => [
                            'type'        => 'string',
                            'description' => __( 'Owner name or slug. Default: every owner.', 'ketunkoti-ai' ),
                        ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'get_portfolio' ],
                'permission_callback' => [ self::class, 'can_manage' ],
                'annotations'         => [
                    'readonly'    => true,
                    'destructive' => false,
                    'idempotent'  => true,
                ],
            ],
            'mcp_tool'
        );

        Registrar::register(
            'ketunkoti/create-portfolio-apartment',
            [
                'label'               => __( 'Create portfolio apartment', 'ketunkoti-ai' ),
                'description'         => __( 'Adds an apartment to the private portfolio (Salkku) with a title, one owner (created when new), an optional status (publish by default; a draft is saved but stays out of the totals) and optional fields. Numbers may use Finnish formatting (150 000, 1 285,50). One invalid value refuses the whole call and nothing is created. Before calling, show the user the title, owner and each field as "label: value", and call only after the user agrees. Every write is logged on the apartment. Returns the apartment as saved.', 'ketunkoti-ai' ),
                'input_schema'        => self::create_schema(),
                'execute_callback'    => [ self::class, 'create_apartment' ],
                'permission_callback' => [ self::class, 'can_manage' ],
                'annotations'         => [
                    'readonly'    => false,
                    'destructive' => false,
                    'idempotent'  => false,
                ],
            ],
            'mcp_tool'
        );

        Registrar::register(
            'ketunkoti/update-portfolio-apartment',
            [
                'label'               => __( 'Update portfolio apartment', 'ketunkoti-ai' ),
                'description'         => __( 'Changes the title, the owner or portfolio fields of an apartment in the private portfolio (Salkku) and saves at once. Before calling, read the portfolio with ketunkoti/get-portfolio and show the user each change as "label: before → after", and call only after the user agrees. null or an empty string clears a field. The owner must already exist (name or slug); an unknown owner is refused, so a typo cannot create a new owner. An apartment in the trash is refused. One invalid value refuses the whole call. Refused while another user has the apartment open in the editor. Every change is logged on the apartment with its before and after values. Returns the values as saved.', 'ketunkoti-ai' ),
                'input_schema'        => self::update_schema(),
                'execute_callback'    => [ self::class, 'update_apartment' ],
                'permission_callback' => [ self::class, 'can_manage' ],
                'annotations'         => [
                    'readonly'    => false,
                    'destructive' => true,
                    'idempotent'  => true,
                ],
            ],
            'mcp_tool'
        );
    }

    /**
     * Whether the current user may use the portfolio abilities.
     *
     * @return bool
     */
    public static function can_manage(): bool {
        return current_user_can( 'manage_options' );
    }

    /**
     * Executes ketunkoti/get-portfolio.
     *
     * @param array $input Ability input: optional `owner`.
     * @return array|\WP_Error The overview with labelled fields, or an error for an unknown owner.
     */
    public static function get_portfolio( array $input = [] ) {
        $overview = \Ketunkoti\get_portfolio_overview();
        $owner    = isset( $input['owner'] ) ? trim( (string) $input['owner'] ) : '';

        if ( '' !== $owner ) {
            $name = self::resolve_owner_name( $owner );
            if ( null === $name ) {
                return new \WP_Error(
                    'ketunkoti_ai_unknown_owner',
                    /* translators: %s: owner name given by the client. */
                    sprintf( __( 'There is no portfolio owner "%s". Call ketunkoti/get-portfolio without owner to see the owners.', 'ketunkoti-ai' ), $owner )
                );
            }

            $groups = array_values(
                array_filter(
                    $overview['groups'],
                    static fn( array $group ): bool => $group['name'] === $name
                )
            );

            $overview = [
                'combined' => $groups ? $groups[0]['totals'] : \Ketunkoti\calculate_portfolio_totals( [] ),
                'groups'   => $groups,
            ];
        }

        $labels = \Ketunkoti\get_portfolio_fields();

        foreach ( $overview['groups'] as &$group ) {
            foreach ( $group['apartments'] as &$apartment ) {
                $apartment['fields'] = self::label_fields( $apartment['fields'], $labels );
            }
            unset( $apartment );

            // Drafts are read in full too, so a client can read before it updates one.
            foreach ( $group['drafts'] as &$draft ) {
                $draft_fields    = \Ketunkoti\get_portfolio_field_values( (int) $draft['id'] );
                $draft['results'] = \Ketunkoti\calculate_portfolio_apartment( $draft_fields );
                $draft['fields']  = self::label_fields( $draft_fields, $labels );
            }
            unset( $draft );
        }
        unset( $group );

        return $overview;
    }

    /**
     * Executes ketunkoti/create-portfolio-apartment.
     *
     * Validates everything before the post is inserted, so a refusal leaves
     * nothing behind.
     *
     * @param array $input Ability input: `title`, `owner`, optional `status` and `fields`.
     * @return array|\WP_Error The apartment as saved, or an error.
     */
    public static function create_apartment( array $input ) {
        $title  = sanitize_text_field( (string) $input['title'] );
        $owner  = sanitize_text_field( (string) $input['owner'] );
        $status = $input['status'] ?? 'publish';

        if ( '' === $title ) {
            return new \WP_Error( 'ketunkoti_ai_missing_title', __( 'The apartment needs a title.', 'ketunkoti-ai' ) );
        }

        if ( '' === $owner ) {
            return new \WP_Error( 'ketunkoti_portfolio_invalid_owner', __( 'The apartment needs an owner.', 'ketunkoti-ai' ) );
        }

        if ( ! in_array( $status, [ 'publish', 'draft' ], true ) ) {
            return new \WP_Error( 'ketunkoti_ai_invalid_status', __( 'Status must be publish or draft.', 'ketunkoti-ai' ) );
        }

        $values = \Ketunkoti\validate_portfolio_fields( (array) ( $input['fields'] ?? [] ) );
        if ( is_wp_error( $values ) ) {
            return $values;
        }

        $post_id = wp_insert_post(
            [
                'post_type'   => \Ketunkoti\PORTFOLIO_POST_TYPE,
                'post_status' => $status,
                'post_title'  => $title,
                'post_author' => get_current_user_id(),
            ],
            true
        );
        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        $term_id = \Ketunkoti\set_portfolio_owner( $post_id, $owner );
        if ( is_wp_error( $term_id ) ) {
            wp_delete_post( $post_id, true );
            return $term_id;
        }

        \Ketunkoti\write_portfolio_fields( $post_id, $values );

        self::log_write(
            $post_id,
            [],
            array_merge( [ 'post_title' => $title, 'owner' => self::owner_name( $post_id ) ], $values )
        );

        return self::result( $post_id, array_keys( $values ) );
    }

    /**
     * Executes ketunkoti/update-portfolio-apartment.
     *
     * @param array $input Ability input: `id` and optional `title`, `owner`, `fields`.
     * @return array|\WP_Error The apartment as saved, or an error.
     */
    public static function update_apartment( array $input ) {
        $post_id = (int) $input['id'];

        if ( \Ketunkoti\PORTFOLIO_POST_TYPE !== get_post_type( $post_id ) ) {
            return new \WP_Error(
                'ketunkoti_ai_not_portfolio',
                /* translators: %d: post ID. */
                sprintf( __( 'Post %d is not a portfolio apartment.', 'ketunkoti-ai' ), $post_id )
            );
        }

        if ( 'trash' === get_post_status( $post_id ) ) {
            return new \WP_Error(
                'ketunkoti_ai_trashed',
                /* translators: %d: post ID. */
                sprintf( __( 'Apartment %d is in the trash. Nothing was changed. Ask the user to restore it in wp-admin first.', 'ketunkoti-ai' ), $post_id )
            );
        }

        $title = isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : null;
        $owner = isset( $input['owner'] ) ? sanitize_text_field( (string) $input['owner'] ) : null;

        if ( null === $title && null === $owner && empty( $input['fields'] ) ) {
            return new \WP_Error( 'ketunkoti_ai_nothing_to_update', __( 'Give a title, an owner or fields to change.', 'ketunkoti-ai' ) );
        }

        if ( '' === $title ) {
            return new \WP_Error( 'ketunkoti_ai_missing_title', __( 'The apartment needs a title.', 'ketunkoti-ai' ) );
        }

        if ( '' === $owner ) {
            return new \WP_Error( 'ketunkoti_portfolio_invalid_owner', __( 'The apartment needs an owner.', 'ketunkoti-ai' ) );
        }

        // Update never creates an owner: a typo would silently start a new one.
        if ( null !== $owner && ! self::owner_term_exists( $owner ) ) {
            return new \WP_Error(
                'ketunkoti_ai_unknown_owner',
                /* translators: %s: owner name given by the client. */
                sprintf( __( 'There is no portfolio owner "%s". Nothing was changed. Call ketunkoti/get-portfolio to see the owners.', 'ketunkoti-ai' ), $owner )
            );
        }

        $values = \Ketunkoti\validate_portfolio_fields( (array) ( $input['fields'] ?? [] ) );
        if ( is_wp_error( $values ) ) {
            return $values;
        }

        $locked = self::lock_error( $post_id );
        if ( $locked ) {
            return $locked;
        }

        $stored = \Ketunkoti\get_portfolio_field_values( $post_id );
        $before = array_intersect_key( $stored, $values );
        $after  = $values;

        // The owner goes first: it is the step most likely to fail, and nothing is written before it succeeds.
        if ( null !== $owner ) {
            $before['owner'] = self::owner_name( $post_id );
            $term_id         = \Ketunkoti\set_portfolio_owner( $post_id, $owner );
            if ( is_wp_error( $term_id ) ) {
                return $term_id;
            }
            $after['owner'] = self::owner_name( $post_id );
        }

        if ( null !== $title ) {
            $before['post_title'] = (string) get_post_field( 'post_title', $post_id );
            $updated              = wp_update_post(
                [
                    'ID'         => $post_id,
                    'post_title' => $title,
                ],
                true
            );
            if ( is_wp_error( $updated ) ) {
                // The owner may already be saved: log it so the write is never invisible.
                self::log_write( $post_id, array_diff_key( $before, [ 'post_title' => 1 ] ), array_diff_key( $after, [ 'post_title' => 1 ] ) );
                return $updated;
            }
            $after['post_title'] = $title;
        }

        \Ketunkoti\write_portfolio_fields( $post_id, $values );
        self::log_write( $post_id, $before, $after );

        return self::result( $post_id, array_keys( $values ) );
    }

    /**
     * Builds the result of a write: the apartment's identity and the saved values of the written fields.
     *
     * @param int      $post_id Portfolio post ID.
     * @param string[] $keys    Field keys that were written.
     * @return array
     */
    private static function result( int $post_id, array $keys ): array {
        return [
            'id'     => $post_id,
            'title'  => (string) get_post_field( 'post_title', $post_id ),
            'status' => get_post_status( $post_id ),
            'owner'  => self::owner_name( $post_id ),
            'fields' => array_intersect_key( \Ketunkoti\get_portfolio_field_values( $post_id ), array_flip( $keys ) ),
        ];
    }

    /**
     * Appends one row to the apartment's write log.
     *
     * @param int   $post_id Portfolio post ID.
     * @param array $before  Values before the write.
     * @param array $after   Values after the write.
     * @return void
     */
    private static function log_write( int $post_id, array $before, array $after ): void {
        $log = get_post_meta( $post_id, self::LOG_META, true );
        $log = is_array( $log ) ? $log : [];

        $log[] = [
            'user'   => get_current_user_id(),
            'time'   => gmdate( 'c' ),
            'before' => $before,
            'after'  => $after,
        ];

        update_post_meta( $post_id, self::LOG_META, wp_slash( $log ) );
    }

    /**
     * Name of the apartment's owner, or an empty string.
     *
     * @param int $post_id Portfolio post ID.
     * @return string
     */
    private static function owner_name( int $post_id ): string {
        $terms = get_the_terms( $post_id, \Ketunkoti\PORTFOLIO_OWNER_TAXONOMY );

        return ( is_array( $terms ) && $terms ) ? $terms[0]->name : '';
    }

    /**
     * Whether an owner term exists with this name or slug.
     *
     * @param string $owner Owner name or slug.
     * @return bool
     */
    private static function owner_term_exists( string $owner ): bool {
        if ( get_term_by( 'name', $owner, \Ketunkoti\PORTFOLIO_OWNER_TAXONOMY ) ) {
            return true;
        }

        return (bool) get_term_by( 'slug', sanitize_title( $owner ), \Ketunkoti\PORTFOLIO_OWNER_TAXONOMY );
    }

    /**
     * Resolves an owner given by name or slug to the group name used in the overview.
     *
     * @param string $owner Owner name or slug.
     * @return string|null Group name, or null when there is no such owner.
     */
    private static function resolve_owner_name( string $owner ): ?string {
        if ( \Ketunkoti\PORTFOLIO_NO_OWNER === $owner ) {
            return $owner;
        }

        $term = get_term_by( 'name', $owner, \Ketunkoti\PORTFOLIO_OWNER_TAXONOMY );
        if ( ! $term ) {
            $term = get_term_by( 'slug', sanitize_title( $owner ), \Ketunkoti\PORTFOLIO_OWNER_TAXONOMY );
        }

        return $term ? $term->name : null;
    }

    /**
     * Turns field values into key => label and value rows.
     *
     * @param array $values Field key => value.
     * @param array $fields Field definitions from `get_portfolio_fields()`.
     * @return array
     */
    private static function label_fields( array $values, array $fields ): array {
        $labelled = [];

        foreach ( $values as $key => $value ) {
            $labelled[ $key ] = [
                'label' => $fields[ $key ]['label'] ?? $key,
                'value' => $value,
            ];
        }

        return $labelled;
    }

    /**
     * Builds an error when another user has the apartment open in the editor.
     *
     * Uses the editor's own edit lock; the current user's own lock does not count.
     *
     * @param int $post_id Post ID.
     * @return \WP_Error|null The error, or null when the post is free.
     */
    private static function lock_error( int $post_id ): ?\WP_Error {
        if ( ! function_exists( 'wp_check_post_lock' ) ) {
            require_once ABSPATH . 'wp-admin/includes/post.php';
        }

        $user_id = wp_check_post_lock( $post_id );
        if ( ! $user_id ) {
            return null;
        }

        $user = get_userdata( $user_id );

        return new \WP_Error(
            'ketunkoti_ai_locked',
            sprintf(
                /* translators: %s: display name of the user who is editing the apartment. */
                __( '%s is editing this apartment right now. Nothing was changed. Ask the user to try again after it is closed in the editor.', 'ketunkoti-ai' ),
                $user ? $user->display_name : __( 'Another user', 'ketunkoti-ai' )
            )
        );
    }

    /**
     * Schema of the `fields` object, built from the registered portfolio fields.
     *
     * Numbers also accept strings so Finnish formatting passes; the real
     * validation stays in `validate_portfolio_fields()`.
     *
     * @return array
     */
    private static function fields_schema(): array {
        $properties = [];

        foreach ( \Ketunkoti\get_portfolio_fields() as $key => $field ) {
            $type = match ( $field['type'] ) {
                'number'  => [ 'number', 'string', 'null' ],
                'integer' => [ 'integer', 'string', 'null' ],
                default   => [ 'string', 'null' ],
            };

            $properties[ $key ] = [
                'type'        => $type,
                'description' => $field['label'],
            ];
        }

        return [
            'type'                 => 'object',
            'description'          => __( 'portfolio_* field => value. Numbers may use a decimal comma and spaces. Dates are YYYY-MM-DD. null or "" clears a field.', 'ketunkoti-ai' ),
            'additionalProperties' => false,
            'properties'           => $properties,
        ];
    }

    /**
     * Input schema of the create ability.
     *
     * @return array
     */
    private static function create_schema(): array {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => [ 'title', 'owner' ],
            'properties'           => [
                'title'  => [
                    'type'      => 'string',
                    'minLength' => 1,
                ],
                'owner'  => [
                    'type'        => 'string',
                    'minLength'   => 1,
                    'description' => __( 'Owner name or slug. A new name creates the owner.', 'ketunkoti-ai' ),
                ],
                'status' => [
                    'type'        => 'string',
                    'enum'        => [ 'publish', 'draft' ],
                    'description' => __( 'publish (default) or draft. A draft stays out of the totals.', 'ketunkoti-ai' ),
                ],
                'fields' => self::fields_schema(),
            ],
        ];
    }

    /**
     * Input schema of the update ability.
     *
     * @return array
     */
    private static function update_schema(): array {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => [ 'id' ],
            'properties'           => [
                'id'     => [
                    'type'    => 'integer',
                    'minimum' => 1,
                ],
                'title'  => [ 'type' => 'string' ],
                'owner'  => [
                    'type'        => 'string',
                    'description' => __( 'Name or slug of an existing owner. An unknown owner is refused.', 'ketunkoti-ai' ),
                ],
                'fields' => self::fields_schema(),
            ],
        ];
    }
}
