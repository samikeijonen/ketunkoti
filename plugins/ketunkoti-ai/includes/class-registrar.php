<?php
/**
 * Ability category and registration.
 *
 * @package Ketunkoti\AI
 */

namespace Ketunkoti\AI;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the plugin's ability category and exposes its abilities to MCP.
 */
final class Registrar {

    /**
     * Ability category.
     */
    public const CATEGORY = 'ketunkoti';

    /**
     * Every ability the plugin registers.
     */
    public const NAMES = [
        'ketunkoti/get-home',
        'ketunkoti/list-homes',
        'ketunkoti/copy-home',
        'ketunkoti/update-home-meta',
        'ketunkoti/trash-home',
    ];

    /**
     * Registers the ability category. Runs on `wp_abilities_api_categories_init`.
     *
     * @return void
     */
    public static function register_category(): void {
        wp_register_ability_category(
            self::CATEGORY,
            [
                'label'       => __( 'Ketun koti', 'ketunkoti-ai' ),
                'description' => __( 'Abilities for reading, copying and filling in home listings.', 'ketunkoti-ai' ),
            ]
        );
    }

    /**
     * Registers one ability with the shared arguments.
     *
     * Requires `annotations` with boolean `readonly`, `destructive` and
     * `idempotent`; otherwise the ability is not registered. Adds the category,
     * REST exposure and MCP exposure (public only for read-only abilities).
     * The ability's own permission callback is the only access check.
     *
     * @param string $name Ability name, e.g. ketunkoti/get-home.
     * @param array  $args Ability arguments, including `annotations`.
     * @return void
     */
    public static function register( string $name, array $args ): void {
        $annotations = $args['annotations'] ?? null;

        foreach ( [ 'readonly', 'destructive', 'idempotent' ] as $key ) {
            if ( ! is_array( $annotations ) || ! isset( $annotations[ $key ] ) || ! is_bool( $annotations[ $key ] ) ) {
                _doing_it_wrong(
                    __METHOD__,
                    sprintf(
                        /* translators: 1: ability name, 2: annotation key. */
                        esc_html__( 'Ability %1$s must state a boolean "%2$s" annotation.', 'ketunkoti-ai' ),
                        esc_html( $name ),
                        esc_html( $key )
                    ),
                    '1.1.0'
                );
                return;
            }
        }

        if ( empty( $args['input_schema'] ) ) {
            // MCP clients send {} to abilities without input.
            $args['input_schema'] = [
                'type'                 => 'object',
                'additionalProperties' => false,
                'default'              => [],
            ];
        } elseif ( 'object' === ( $args['input_schema']['type'] ?? '' ) && ! array_key_exists( 'default', $args['input_schema'] ) ) {
            // Lets an object schema with only optional properties be called without input.
            $args['input_schema']['default'] = [];
        }

        $args['category'] = self::CATEGORY;
        unset( $args['annotations'] );

        $args['meta'] = array_replace_recursive(
            $args['meta'] ?? [],
            [
                'annotations'  => $annotations,
                'show_in_rest' => true,
                'public'       => true,
                'mcp'          => [
                    'public' => true === $annotations['readonly'],
                    'type'   => 'tool',
                ],
            ]
        );

        wp_register_ability( $name, $args );
    }

    /**
     * Adds the plugin's abilities to the default MCP server's tools.
     *
     * Hooked to `mcp_adapter_default_server_config`. Keeps the existing config
     * keys and tools, appends this plugin's abilities and removes duplicates.
     *
     * @param mixed $config MCP server configuration.
     * @return array
     */
    public static function add_tools( $config ): array {
        $config          = is_array( $config ) ? $config : [];
        $tools           = isset( $config['tools'] ) && is_array( $config['tools'] ) ? $config['tools'] : [];
        $config['tools'] = array_values( array_unique( array_merge( $tools, self::NAMES ) ) );

        return $config;
    }
}
