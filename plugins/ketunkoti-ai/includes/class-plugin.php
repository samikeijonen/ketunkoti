<?php
/**
 * Plugin bootstrap: hooks the abilities into WordPress.
 *
 * @package Ketunkoti\AI
 */

namespace Ketunkoti\AI;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Wires the plugin's classes to WordPress hooks.
 */
final class Plugin {

    /**
     * Adds the plugin's hooks. Runs on `plugins_loaded`.
     *
     * Needs only the Abilities API (WordPress core) and, for MCP, the MCP
     * Adapter. No assistant plugin is required.
     *
     * @return void
     */
    public static function init(): void {
        if ( ! function_exists( 'wp_register_ability' ) ) {
            return;
        }

        add_action( 'wp_abilities_api_categories_init', [ Registrar::class, 'register_category' ] );
        add_action( 'wp_abilities_api_init', [ Home_Abilities::class, 'register' ] );
        add_filter( 'mcp_adapter_default_server_config', [ Registrar::class, 'add_tools' ] );
    }
}
