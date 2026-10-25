<?php
/**
 * Plugin Name: Sponsor Manager
 * Description: Booking, scheduling, and sellable-inventory system for sponsor placements (sidebar, mega menu, landing pages, single posts/pages, targetable by sponsor placement).
 * Author: Bemis Tech
 * Author URI: https://bemistech.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version: 1.5.2
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Text Domain: sponsor-manager
 * Update URI: https://updates.bemistech.com/sponsor-manager.json
 *
 * @package SponsorManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SPONSOR_MANAGER_VERSION', '1.5.2' );
define( 'SPONSOR_MANAGER_FILE', __FILE__ );
define( 'SPONSOR_MANAGER_PATH', plugin_dir_path( __FILE__ ) );
define( 'SPONSOR_MANAGER_URL', plugin_dir_url( __FILE__ ) );
define( 'SPONSOR_MANAGER_LEGACY_PAUSE_META', 'expired' );
define( 'SPONSOR_MANAGER_EVERYWHERE_SLUG', 'everywhere' );

require_once SPONSOR_MANAGER_PATH . 'includes/Autoloader.php';
SponsorManager\Autoloader::register();

require_once SPONSOR_MANAGER_PATH . 'includes/functions.php';

register_activation_hook( __FILE__, array( 'SponsorManager\Lifecycle', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SponsorManager\Lifecycle', 'deactivate' ) );

add_action( 'plugins_loaded', 'sponsor_manager_bootstrap', 5 );
/**
 * Load the plugin.
 */
function sponsor_manager_bootstrap() {
	SponsorManager\Plugin::instance()->boot();
}

add_action( 'after_setup_theme', array( 'SponsorManager\Assets', 'register_image_sizes' ) );
add_action( 'wp_enqueue_scripts', array( 'SponsorManager\Assets', 'enqueue_front' ), 20 );
add_action( 'enqueue_block_editor_assets', array( 'SponsorManager\Assets', 'enqueue_editor' ), 20 );
add_action( 'admin_enqueue_scripts', array( 'SponsorManager\Assets', 'enqueue_admin' ) );
