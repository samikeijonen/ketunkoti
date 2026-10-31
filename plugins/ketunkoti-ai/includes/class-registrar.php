<?php
/**
 * Ability category and registration.
 *
 * @package Ketunkoti\AI
 */

namespace Ketunkoti\AI;

use MEOM\AI_Assistant\Settings;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the plugin's ability category and exposes its abilities to MCP.
 *
 * Mirrors MEOM\AI_Assistant\Abilities\Registrar::register() so the abilities
 * behave like the assistant's own, but in the plugin's own category.
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
        'ketunkoti/propose-home-meta',
        'ketunkoti/apply-home-meta',
        'ketunkoti/discard-home-meta',
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
     * Adds the category, annotations, REST and MCP exposure (the MCP Adapter
     * reads `meta.mcp.public`) and puts the assistant's own access check in
     * front of the ability's permission callback, so the abilities switch off
     * with the assistant.
     *
     * @param string $name Ability name, e.g. ketunkoti/get-home.
     * @param array  $args Ability arguments. `annotations` and `permission_callback` are optional.
     * @return void
     */
    public static function register( string $name, array $args ): void {
        $inner = $args['permission_callback'] ?? null;

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

        $args['category']            = self::CATEGORY;
        $args['permission_callback'] = static function ( $input = null ) use ( $inner ) {
            if ( ! Settings::user_can_chat() ) {
                return false;
            }

            return $inner ? (bool) $inner( $input ) : true;
        };

        $meta        = $args['meta'] ?? [];
        $annotations = $args['annotations'] ?? [ 'readonly' => true ];
        unset( $args['annotations'] );

        $args['meta'] = array_replace_recursive(
            [
                'annotations'  => $annotations,
                'show_in_rest' => true,
                'mcp'          => [ 'public' => true ],
            ],
            $meta
        );

        wp_register_ability( $name, $args );
    }

    /**
     * Adds the plugin's abilities to those MCP clients may see and run.
     *
     * Without this the assistant hides every ability outside `meom-ai/*`
     * from MCP. Hooked to `meom_ai_mcp_abilities`.
     *
     * @param string[] $names Ability names other plugins have added.
     * @return string[]
     */
    public static function mcp_names( array $names ): array {
        return array_values( array_unique( array_merge( $names, self::NAMES ) ) );
    }
}
