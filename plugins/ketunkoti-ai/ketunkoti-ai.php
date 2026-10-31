<?php
/**
 * Plugin Name:       Ketun koti AI
 * Plugin URI:        https://ketunkoti.fi
 * Description:       Home abilities for Claude: read, copy and fill in home listings. Add-on for MEOM AI Assistant.
 * Version:           1.0.0
 * Requires at least: 7.1
 * Requires PHP:      8.2
 * Requires Plugins:  meom-ai-assistant, ketunkoti-features
 * Author:            Sami Keijonen
 * Author URI:        https://ketunkoti.fi
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ketunkoti-ai
 * Domain Path:       /languages
 *
 * @package Ketunkoti\AI
 */

namespace Ketunkoti\AI;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Absolute path to the plugin directory, with a trailing slash.
 */
define( 'KETUNKOTI_AI_DIR', plugin_dir_path( __FILE__ ) );

require_once KETUNKOTI_AI_DIR . 'includes/class-home-fields.php';
require_once KETUNKOTI_AI_DIR . 'includes/class-home-reader.php';
require_once KETUNKOTI_AI_DIR . 'includes/class-home-copier.php';
require_once KETUNKOTI_AI_DIR . 'includes/class-meta-proposals.php';
require_once KETUNKOTI_AI_DIR . 'includes/class-registrar.php';
require_once KETUNKOTI_AI_DIR . 'includes/class-home-abilities.php';
require_once KETUNKOTI_AI_DIR . 'includes/class-plugin.php';

add_action( 'plugins_loaded', [ Plugin::class, 'init' ] );
