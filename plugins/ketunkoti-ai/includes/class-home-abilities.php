<?php
/**
 * The plugin's abilities.
 *
 * @package Ketunkoti\AI
 */

namespace Ketunkoti\AI;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the home abilities. The callbacks check input, edit locks and
 * delegate to the reader, copier and field classes.
 *
 * The write abilities work on any home the user may edit or delete,
 * published ones included. Approval happens in the client: the MCP client,
 * or the chat's approval card, asks the user before each write runs.
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
                'description'         => __( 'Returns a whole home or rental: title, excerpt, featured image, every home_* field with its label, type, description and value, terms per taxonomy and the calculated investment figures (read-only).', 'ketunkoti-ai' ),
                'input_schema'        => self::id_schema(),
                'execute_callback'    => static fn( array $input ) => Home_Reader::get( (int) $input['id'] ),
                'permission_callback' => [ self::class, 'can_edit_post' ],
                'annotations'         => [
                    'readonly'    => true,
                    'destructive' => false,
                    'idempotent'  => true,
                ],
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
                'annotations'         => [
                    'readonly'    => true,
                    'destructive' => false,
                    'idempotent'  => true,
                ],
            ]
        );

        Registrar::register(
            'ketunkoti/copy-home',
            [
                'label'               => __( 'Copy home', 'ketunkoti-ai' ),
                'description'         => __( 'Copies a home or rental exactly (content, excerpt, template, featured image, every home_* field and every term) into a new draft, with the current user as author. Then set the home_* fields with ketunkoti/update-home-meta, and the title and content with seravo-ai-assistant/update-post when that ability is available. Terms and images stay as copied unless the editor changes them in wp-admin. SEO meta is not copied. The editor publishes the draft. Pass on every entry of notes to the user, for example a title another home already has.', 'ketunkoti-ai' ),
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
                    'idempotent'  => false,
                ],
            ]
        );

        Registrar::register(
            'ketunkoti/update-home-meta',
            [
                'label'               => __( 'Update home fields', 'ketunkoti-ai' ),
                'description'         => __( 'Sets home_* fields on any home or rental the user may edit, published ones included, and saves them at once: a published home changes on the site immediately. Before calling, read the home with ketunkoti/get-home and show the user each change as "label: before → after", and call only after the user agrees. null or an empty string clears a field. One invalid value refuses the whole call. Refused while another user has the home open in the editor. Every change is logged on the home with its before and after values. Returns the values as saved, and notes on fields that usually change together (a new rent without the deposit, for example): ask the user about each.', 'ketunkoti-ai' ),
                'input_schema'        => self::meta_schema(),
                'execute_callback'    => [ self::class, 'update_home_meta' ],
                'permission_callback' => [ self::class, 'can_edit_post' ],
                'annotations'         => [
                    'readonly'    => false,
                    'destructive' => true,
                    'idempotent'  => true,
                ],
            ]
        );

        Registrar::register(
            'ketunkoti/trash-home',
            [
                'label'               => __( 'Move a home to the trash', 'ketunkoti-ai' ),
                'description'         => __( 'Moves any home or rental the user may delete to the trash, published ones included: a published home leaves the site at once. Never deletes permanently; the editor can restore it from the trash in wp-admin. Call it only when the user asked to remove that home. Before calling, name the home and its current status to the user, and call only after the user agrees. Refused while trash is disabled on the site. Refused while another user has the home open in the editor.', 'ketunkoti-ai' ),
                'input_schema'        => self::id_schema(),
                'execute_callback'    => [ self::class, 'trash_home' ],
                'permission_callback' => [ self::class, 'can_delete_post' ],
                'annotations'         => [
                    'readonly'    => false,
                    'destructive' => true,
                    'idempotent'  => true,
                ],
            ]
        );
    }

    /**
     * Executes ketunkoti/trash-home.
     *
     * Any home the user may delete, published ones included. Trash, never a
     * permanent delete, so a mistake can be undone in wp-admin: refused when
     * the site has trash disabled (EMPTY_TRASH_DAYS 0). A home already in the
     * trash returns the same success shape, so the call is idempotent.
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

        // Already in the trash: report it as done instead of failing, so repeating the call is harmless.
        if ( 'trash' === get_post_status( $post_id ) ) {
            return [
                'id'     => $post_id,
                'title'  => get_the_title( $post_id ),
                'status' => 'trash',
            ];
        }

        // With EMPTY_TRASH_DAYS at 0, wp_trash_post() deletes permanently. This ability never does.
        if ( ! EMPTY_TRASH_DAYS ) {
            return new \WP_Error(
                'ketunkoti_ai_trash_disabled',
                __( 'Trash is disabled on this site, so the home is not removed: it would be deleted permanently. Ask the editor to remove it in wp-admin.', 'ketunkoti-ai' )
            );
        }

        $locked = self::lock_error( $post_id );
        if ( $locked ) {
            return $locked;
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
     * Writes on any home the user may edit, published ones included, unless
     * another user has it open in the editor. Logs one row per call in
     * Home_Fields::LOG_META with the values before and after the write.
     *
     * @param array $input Ability input: `id` and `meta`.
     * @return array|\WP_Error The written fields as saved, or an error.
     */
    public static function update_home_meta( array $input ) {
        $post_id = (int) $input['id'];
        $meta    = (array) $input['meta'];

        $prepared = Home_Fields::prepare( $post_id, $meta );
        if ( is_wp_error( $prepared ) ) {
            return $prepared;
        }

        $locked = self::lock_error( $post_id );
        if ( $locked ) {
            return $locked;
        }

        $before = Home_Fields::values( $post_id, array_keys( $prepared ) );
        Home_Fields::write( $post_id, $prepared );
        add_post_meta(
            $post_id,
            Home_Fields::LOG_META,
            wp_slash(
                [
                    'user_id' => get_current_user_id(),
                    'changed' => time(),
                    'before'  => $before,
                    'after'   => $prepared,
                ]
            )
        );

        return [
            'id'     => $post_id,
            'fields' => Home_Fields::values( $post_id, array_keys( $prepared ) ),
            'notes'  => Home_Fields::related_notes( $post_id, array_keys( $prepared ) ),
        ];
    }

    /**
     * Builds an error when another user has the post open in the editor.
     *
     * Uses the block editor's own edit lock, so a write never lands under
     * someone who is editing the home at that moment. The current user's own
     * lock does not count.
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
            'ketunkoti_ai_post_locked',
            sprintf(
                /* translators: %s: display name of the user who is editing the home. */
                __( '%s is editing this home right now. Nothing was changed. Ask the user to try again after the home is closed in the editor.', 'ketunkoti-ai' ),
                $user ? $user->display_name : __( 'Another user', 'ketunkoti-ai' )
            )
        );
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
     * Whether the current user may delete (trash) the post named by the input `id`.
     *
     * @param mixed $input Ability input.
     * @return bool
     */
    public static function can_delete_post( $input ): bool {
        $post_id = is_array( $input ) ? (int) ( $input['id'] ?? 0 ) : 0;

        return $post_id > 0 && current_user_can( 'delete_post', $post_id );
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
