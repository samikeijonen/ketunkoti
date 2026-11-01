<?php
/**
 * Plugin Name:       Ketun koti Blocks
 * Plugin URI:        https://ketunkoti.fi
 * Description:       Custom blocks for Ketun koti, starting with the yield calculator for homes.
 * Version:           1.0.0
 * Requires at least: 7.1
 * Requires PHP:      8.2
 * Requires Plugins:  ketunkoti-features
 * Author:            Sami Keijonen
 * Author URI:        https://ketunkoti.fi
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ketunkoti-blocks
 * Domain Path:       /languages
 *
 * @package Ketunkoti\Blocks
 */

namespace Ketunkoti\Blocks;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Absolute path to the plugin directory, with a trailing slash.
 */
define( 'KETUNKOTI_BLOCKS_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL of the plugin directory, with a trailing slash.
 */
define( 'KETUNKOTI_BLOCKS_URL', plugin_dir_url( __FILE__ ) );

require_once KETUNKOTI_BLOCKS_DIR . 'includes/euribor.php';
require_once KETUNKOTI_BLOCKS_DIR . 'includes/numbers.php';
require_once KETUNKOTI_BLOCKS_DIR . 'includes/yield-calculator.php';
require_once KETUNKOTI_BLOCKS_DIR . 'includes/investment-calculator.php';

/**
 * Adds a "Ketun koti" category to the block inserter, first in the list.
 *
 * @param array[] $categories Registered block categories.
 * @return array[] Categories with Ketun koti prepended.
 */
function register_block_category( array $categories ): array {
    array_unshift(
        $categories,
        [
            'slug'  => 'ketunkoti',
            'title' => __( 'Ketun koti', 'ketunkoti-blocks' ),
            'icon'  => null,
        ]
    );

    return $categories;
}
add_filter( 'block_categories_all', __NAMESPACE__ . '\register_block_category' );

/**
 * Registers the plugin's blocks from their build folders.
 *
 * Every folder in `build/` with a `block.json` is registered, so a new block
 * needs no code here. Nothing is registered when the build is missing, so a
 * fresh checkout without `npm run build` degrades to no blocks rather than a
 * fatal error.
 *
 * @return void
 */
function register_blocks(): void {
    $block_files = glob( KETUNKOTI_BLOCKS_DIR . 'build/*/block.json' );

    if ( ! $block_files ) {
        return;
    }

    foreach ( $block_files as $block_file ) {
        register_block_type( dirname( $block_file ) );
    }
}
add_action( 'init', __NAMESPACE__ . '\register_blocks' );
