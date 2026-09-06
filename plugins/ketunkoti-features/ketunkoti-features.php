<?php
/**
 * Plugin Name:       Ketun koti Features
 * Plugin URI:        https://ketunkoti.fi
 * Description:       Site-specific features for Ketun koti: custom post types and related functionality.
 * Version:           1.0.0
 * Requires at least: 7.1
 * Requires PHP:      8.2
 * Author:            Sami Keijonen
 * Author URI:        https://ketunkoti.fi
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ketunkoti-features
 * Domain Path:       /languages
 *
 * @package Ketunkoti\Features
 */

namespace Ketunkoti;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin version, used for cache busting when assets are added later.
 */
define( 'KETUNKOTI_FEATURES_VERSION', '1.0.0' );

/**
 * Absolute path to the plugin directory, with a trailing slash.
 */
define( 'KETUNKOTI_FEATURES_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL of the plugin directory, with a trailing slash.
 */
define( 'KETUNKOTI_FEATURES_URL', plugin_dir_url( __FILE__ ) );

require_once KETUNKOTI_FEATURES_DIR . 'includes/post-types.php';
require_once KETUNKOTI_FEATURES_DIR . 'includes/taxonomies.php';
require_once KETUNKOTI_FEATURES_DIR . 'includes/meta.php';
require_once KETUNKOTI_FEATURES_DIR . 'includes/bindings.php';

/**
 * Runs on plugin activation.
 *
 * Post types and taxonomies are registered directly here because the `init`
 * hook has already fired by the time the activation hook runs. Without this,
 * the rewrite rules for the custom post type would not exist yet and flushing
 * would produce permalinks that 404 until the next manual permalink save.
 *
 * The taxonomies currently use `rewrite => false` and so add no rules of their
 * own; they are registered here anyway so the flush stays correct if one of
 * them is later given a rewrite slug.
 *
 * @return void
 */
function activate() {
    register_post_types();
    register_taxonomies();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\activate' );

/**
 * Runs on plugin deactivation.
 *
 * Clears the rewrite rules this plugin added so the custom post type URLs
 * stop resolving once the plugin is switched off.
 *
 * @return void
 */
function deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\deactivate' );

/**
 * Enqueues the block editor assets.
 *
 * The script and its dependency list come from the build produced by
 * @wordpress/scripts. Nothing is enqueued when the build is missing, so a fresh
 * checkout without `npm run build` degrades to no editor panel rather than a
 * fatal error or a 404 for the script.
 *
 * @return void
 */
function enqueue_block_editor_assets() {
    $asset_file = KETUNKOTI_FEATURES_DIR . 'build/index.asset.php';

    if ( ! file_exists( $asset_file ) ) {
        return;
    }

    $asset = require $asset_file;

    wp_enqueue_script(
        'ketunkoti-features-editor',
        KETUNKOTI_FEATURES_URL . 'build/index.js',
        $asset['dependencies'],
        $asset['version'],
        true
    );

    wp_enqueue_style(
        'ketunkoti-features-editor',
        KETUNKOTI_FEATURES_URL . 'build/index.css',
        [],
        $asset['version']
    );
}
add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\enqueue_block_editor_assets' );
