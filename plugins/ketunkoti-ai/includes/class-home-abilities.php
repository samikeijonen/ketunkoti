<?php
/**
 * The plugin's abilities.
 *
 * @package Ketunkoti\AI
 */

namespace Ketunkoti\AI;

use MEOM\AI_Assistant\Ownership;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the home abilities. The callbacks only check input and delegate
 * to the reader, copier and proposal classes.
 */
final class Home_Abilities {

    /**
     * Registers every ability. Runs on `wp_abilities_api_init`.
     *
     * @return void
     */
    public static function register(): void {
        Registrar::register(
            'ketunkoti/get-home',
            [
                'label'               => __( 'Get home', 'ketunkoti-ai' ),
                'description'         => __( 'Returns a whole home or rental: title, excerpt, featured image, every home_* field with its label, type, description and value, terms per taxonomy, the calculated investment figures (read-only) and ai_owned, whether you may edit it directly with ketunkoti/update-home-meta.', 'ketunkoti-ai' ),
                'input_schema'        => self::id_schema(),
                'execute_callback'    => static fn( array $input ) => Home_Reader::get( (int) $input['id'] ),
                'permission_callback' => [ self::class, 'can_edit_post' ],
            ]
        );

        Registrar::register(
            'ketunkoti/list-homes',
            [
                'label'               => __( 'List homes', 'ketunkoti-ai' ),
                'description'         => __( 'Lists homes (for sale and investment flats) and rentals as compact rows: id, post type, title, status, city and rooms. Use it to find a home to read or copy.', 'ketunkoti-ai' ),
                'input_schema'        => [
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => [
                        'post_type' => [
                            'type' => 'string',
                            'enum' => Home_Fields::POST_TYPES,
                        ],
                        'status'    => [
                            'type'        => 'string',
                            'description' => __( 'Post status, default any (all except trash).', 'ketunkoti-ai' ),
                        ],
                        'search'    => [ 'type' => 'string' ],
                        'limit'     => [
                            'type'    => 'integer',
                            'minimum' => 1,
                            'maximum' => 100,
                        ],
                        'offset'    => [
                            'type'    => 'integer',
                            'minimum' => 0,
                        ],
                    ],
                ],
                'execute_callback'    => static fn( array $input = [] ) => Home_Reader::list( $input ),
                'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
            ]
        );

        Registrar::register(
            'ketunkoti/copy-home',
            [
                'label'               => __( 'Copy home', 'ketunkoti-ai' ),
                'description'         => __( 'Copies a home or rental exactly (content, excerpt, template, featured image, every home_* field and every term) into a new draft that you may then edit directly: home_* fields with ketunkoti/update-home-meta, title, content and terms with meom-ai/update-ai-draft. SEO meta is not copied. The editor publishes the draft. Pass on every entry of notes to the user, for example a title another home already has.', 'ketunkoti-ai' ),
                'input_schema'        => [
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'required'             => [ 'id' ],
                    'properties'           => [
                        'id'    => [
                            'type'    => 'integer',
                            'minimum' => 1,
                        ],
                        'title' => [
                            'type'        => 'string',
                            'description' => __( 'Title of the copy. Default: the source title with " (kopio)".', 'ketunkoti-ai' ),
                        ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'copy_home' ],
                'permission_callback' => [ self::class, 'can_copy' ],
                'annotations'         => [
                    'readonly'    => false,
                    'destructive' => false,
                ],
            ]
        );

        Registrar::register(
            'ketunkoti/update-home-meta',
            [
                'label'               => __( 'Update home fields on your draft', 'ketunkoti-ai' ),
                'description'         => __( 'Sets home_* fields directly on a draft you created (ai_owned true in ketunkoti/get-home). null or an empty string clears a field. For any other home use ketunkoti/propose-home-meta. Returns the values as saved, and notes on fields that usually change together (a new rent without the deposit, for example): ask the user about each.', 'ketunkoti-ai' ),
                'input_schema'        => self::meta_schema(),
                'execute_callback'    => [ self::class, 'update_home_meta' ],
                'permission_callback' => [ self::class, 'can_edit_post' ],
                'annotations'         => [
                    'readonly'    => false,
                    'destructive' => false,
                ],
            ]
        );

        Registrar::register(
            'ketunkoti/propose-home-meta',
            [
                'label'               => __( 'Propose home field changes', 'ketunkoti-ai' ),
                'description'         => __( 'Proposes home_* field changes to any home or rental, for example a published one. Changes nothing: returns proposal_id, the changes with each field\'s value before and after, and notes on fields that usually change together. Show the user every change as "label: before → after" and every note, and call ketunkoti/apply-home-meta only after the user says yes; otherwise call ketunkoti/discard-home-meta. A proposal expires in 30 minutes.', 'ketunkoti-ai' ),
                'input_schema'        => self::meta_schema(),
                'execute_callback'    => static fn( array $input ) => Meta_Proposals::propose( (int) $input['id'], get_current_user_id(), (array) $input['meta'] ),
                'permission_callback' => [ self::class, 'can_edit_post' ],
                'annotations'         => [
                    'readonly'    => false,
                    'destructive' => false,
                ],
            ]
        );

        Registrar::register(
            'ketunkoti/apply-home-meta',
            [
                'label'               => __( 'Apply approved home field changes', 'ketunkoti-ai' ),
                'description'         => __( 'Applies a proposal from ketunkoti/propose-home-meta. Call it only after the user has seen the changes and said yes. Refused if the proposal is someone else\'s, has expired, or any of its fields changed since.', 'ketunkoti-ai' ),
                'input_schema'        => self::proposal_schema(),
                'execute_callback'    => static fn( array $input ) => Meta_Proposals::apply( (string) $input['proposal_id'], get_current_user_id() ),
                'annotations'         => [
                    'readonly'    => false,
                    'destructive' => true,
                ],
            ]
        );

        Registrar::register(
            'ketunkoti/discard-home-meta',
            [
                'label'               => __( 'Discard home field changes', 'ketunkoti-ai' ),
                'description'         => __( 'Discards a proposal from ketunkoti/propose-home-meta, for example when the user says no.', 'ketunkoti-ai' ),
                'input_schema'        => self::proposal_schema(),
                'execute_callback'    => static fn( array $input ) => Meta_Proposals::discard( (string) $input['proposal_id'], get_current_user_id() ),
                'annotations'         => [
                    'readonly'    => false,
                    'destructive' => false,
                ],
            ]
        );

        Registrar::register(
            'ketunkoti/trash-home',
            [
                'label'               => __( 'Move your home draft to the trash', 'ketunkoti-ai' ),
                'description'         => __( 'Moves a home or rental draft you created (ai_owned true in ketunkoti/get-home) to the trash, for example a test copy. Call it only when the user asked to delete that home. The editor can restore it from the trash in wp-admin. Any other home is refused: the editor trashes it in wp-admin.', 'ketunkoti-ai' ),
                'input_schema'        => self::id_schema(),
                'execute_callback'    => [ self::class, 'trash_home' ],
                'permission_callback' => static fn( $input ) => is_array( $input ) && current_user_can( 'delete_post', (int) ( $input['id'] ?? 0 ) ),
                'annotations'         => [
                    'readonly'    => false,
                    'destructive' => true,
                ],
            ]
        );
    }

    /**
     * Executes ketunkoti/trash-home.
     *
     * Only Claude's own drafts: a published or hand-saved home is the
     * editor's to remove. Trash, never a permanent delete, so a mistake can
     * be undone in wp-admin.
     *
     * @param array $input Ability input: `id`.
     * @return array|\WP_Error Post ID, title and the new status, or an error.
     */
    public static function trash_home( array $input ) {
        $post_id = (int) $input['id'];

        if ( ! Home_Fields::is_home( $post_id ) ) {
            return new \WP_Error(
                'ketunkoti_ai_not_a_home',
                /* translators: %d: post ID. */
                sprintf( __( 'Post %d is not a home or a rental.', 'ketunkoti-ai' ), $post_id )
            );
        }

        if ( ! Ownership::is_ai_owned( $post_id, get_current_user_id() ) ) {
            return new \WP_Error(
                'ketunkoti_ai_not_ai_owned',
                __( 'This home is not a draft you created, or someone has saved it since. Ask the editor to move it to the trash in wp-admin.', 'ketunkoti-ai' )
            );
        }

        $title   = get_the_title( $post_id );
        $trashed = wp_trash_post( $post_id );
        if ( ! $trashed ) {
            return new \WP_Error(
                'ketunkoti_ai_trash_failed',
                __( 'The home could not be moved to the trash.', 'ketunkoti-ai' )
            );
        }

        return [
            'id'     => $post_id,
            'title'  => $title,
            'status' => get_post_status( $post_id ),
        ];
    }

    /**
     * Executes ketunkoti/copy-home.
     *
     * @param array $input Ability input: `id` and optional `title`.
     * @return array|\WP_Error New draft ID, edit link and the whole copy, or an error.
     */
    public static function copy_home( array $input ) {
        $copy_id = Home_Copier::copy( (int) $input['id'], get_current_user_id(), $input['title'] ?? null );
        if ( is_wp_error( $copy_id ) ) {
            return $copy_id;
        }

        return [
            'id'        => $copy_id,
            'edit_link' => get_edit_post_link( $copy_id, 'raw' ),
            'home'      => Home_Reader::get( $copy_id ),
            'notes'     => self::duplicate_title_notes( $copy_id ),
        ];
    }

    /**
     * Notes on other homes and rentals that have the same title as this one.
     *
     * Two listings with one title look alike in lists and search results,
     * and WordPress gives the later one a "-2" slug.
     *
     * @param int $post_id Post ID.
     * @return string[] One note, or none when the title is unique.
     */
    private static function duplicate_title_notes( int $post_id ): array {
        $title = get_post_field( 'post_title', $post_id );
        $same  = get_posts(
            [
                'post_type'      => Home_Fields::POST_TYPES,
                'post_status'    => [ 'publish', 'future', 'draft', 'pending', 'private' ],
                'title'          => $title,
                'post__not_in'   => [ $post_id ],
                'fields'         => 'ids',
                'posts_per_page' => 5,
                'no_found_rows'  => true,
            ]
        );

        if ( ! $same ) {
            return [];
        }

        return [
            sprintf(
                /* translators: 1: post title, 2: comma separated post IDs. */
                __( 'The title "%1$s" is already used by home %2$s. Suggest a title of its own to the user.', 'ketunkoti-ai' ),
                $title,
                implode( ', ', $same )
            ),
        ];
    }

    /**
     * Executes ketunkoti/update-home-meta.
     *
     * @param array $input Ability input: `id` and `meta`.
     * @return array|\WP_Error The written fields as saved, or an error.
     */
    public static function update_home_meta( array $input ) {
        $post_id = (int) $input['id'];
        $meta    = (array) $input['meta'];

        if ( ! Ownership::is_ai_owned( $post_id, get_current_user_id() ) ) {
            return new \WP_Error(
                'ketunkoti_ai_not_ai_owned',
                __( 'This home is not a draft you created, or someone has saved it since. Use ketunkoti/propose-home-meta and ask the user before applying.', 'ketunkoti-ai' )
            );
        }

        $prepared = Home_Fields::prepare( $post_id, $meta );
        if ( is_wp_error( $prepared ) ) {
            return $prepared;
        }

        Home_Fields::write( $post_id, $prepared );
        Ownership::record_write( $post_id );

        return [
            'id'     => $post_id,
            'fields' => Home_Fields::values( $post_id, array_keys( $prepared ) ),
            'notes'  => Home_Fields::related_notes( $post_id, array_keys( $prepared ) ),
        ];
    }

    /**
     * Whether the current user may copy the home named by the input `id`:
     * edit the source and create posts of its type.
     *
     * @param mixed $input Ability input.
     * @return bool
     */
    public static function can_copy( $input ): bool {
        if ( ! self::can_edit_post( $input ) ) {
            return false;
        }

        $type = get_post_type_object( (string) get_post_type( (int) $input['id'] ) );

        return $type && current_user_can( $type->cap->create_posts );
    }

    /**
     * Whether the current user may edit the post named by the input `id`.
     *
     * @param mixed $input Ability input.
     * @return bool
     */
    public static function can_edit_post( $input ): bool {
        $post_id = is_array( $input ) ? (int) ( $input['id'] ?? 0 ) : 0;

        return $post_id > 0 && current_user_can( 'edit_post', $post_id );
    }

    /**
     * Input schema with a required post ID and an object of home_* values.
     *
     * @return array
     */
    private static function meta_schema(): array {
        $schema = self::id_schema();

        $schema['required'][]         = 'meta';
        $schema['properties']['meta'] = [
            'type'                 => 'object',
            'description'          => __( 'home_* field => value. Field names, types and descriptions come from ketunkoti/get-home. Numbers may use a decimal comma. null or "" clears a field.', 'ketunkoti-ai' ),
            'minProperties'        => 1,
            'additionalProperties' => [
                'type' => [ 'string', 'number', 'integer', 'null' ],
            ],
        ];

        return $schema;
    }

    /**
     * Input schema with only a required proposal ID.
     *
     * @return array
     */
    private static function proposal_schema(): array {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => [ 'proposal_id' ],
            'properties'           => [
                'proposal_id' => [ 'type' => 'string' ],
            ],
        ];
    }

    /**
     * Input schema with only a required post ID.
     *
     * @return array
     */
    private static function id_schema(): array {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => [ 'id' ],
            'properties'           => [
                'id' => [
                    'type'    => 'integer',
                    'minimum' => 1,
                ],
            ],
        ];
    }
}
