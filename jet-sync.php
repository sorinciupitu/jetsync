<?php
/**
 * Plugin Name:       JetSync
 * Plugin URI:        https://github.com/sorinciupitu/jetsync
 * Description:       A professional alternative and successor to JetEngine for custom post types, taxonomies, meta fields, relations, and listings.
 * Version:           1.5.1
 * Author:            Google DeepMind Antigravity
 * Author URI:        https://deepmind.google
 * License:           GPL-2.0-or-later
 * Update URI: https://github.com/sorinciupitu/jetsync
 * GitHub Plugin URI: https://github.com/sorinciupitu/jetsync
 * Primary Branch: main
 * Text Domain:       jet-sync
 * Domain Path:       /languages
 * Requires PHP:      8.1
 * Requires At Least: 6.0
 */

declare( strict_types=1 );

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define Plugin Constants.
define( 'JETSYNC_VERSION', '1.5.1' );
define( 'JETSYNC_PATH', \plugin_dir_path( __FILE__ ) );
define( 'JETSYNC_URL', \plugin_dir_url( __FILE__ ) );

// Load the Autoloader.
require_once JETSYNC_PATH . 'src/Core/Autoloader.php';

$autoloader = new \JetSync\Core\Autoloader( JETSYNC_PATH . 'src' );
$autoloader->register();

// Register Activation and Deactivation hooks.
\register_activation_hook( __FILE__, [ \JetSync\Core\JetSync::class, 'activate' ] );
\register_deactivation_hook( __FILE__, [ \JetSync\Core\JetSync::class, 'deactivate' ] );

// Initialize the main plugin instance.
\add_action( 'plugins_loaded', function() {
    \JetSync\Core\JetSync::get_instance();
} );
