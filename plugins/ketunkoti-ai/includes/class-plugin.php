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
     * Does nothing without MEOM AI Assistant, whose ownership and settings
     * the abilities rely on. WordPress already refuses to activate the plugin
     * without it; this covers the assistant being removed later.
     *
     * @return void
     */
    public static function init(): void {
        if ( ! class_exists( \MEOM\AI_Assistant\Settings::class ) ) {
            return;
        }

        add_action( 'wp_abilities_api_categories_init', [ Registrar::class, 'register_category' ] );
        add_action( 'wp_abilities_api_init', [ Home_Abilities::class, 'register' ] );
        add_filter( 'meom_ai_mcp_abilities', [ Registrar::class, 'mcp_names' ] );
    }
}
