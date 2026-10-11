<?php
/**
 * Plugin Name:       JetSync
 * Plugin URI:        https://github.com/sorinciupitu/jetsync
 * Description:       A professional alternative and successor to JetEngine for custom post types, taxonomies, meta fields, relations, and listings.
 * Version:           1.6.4
 * Author:            MAXDESIGN S.R.L.
 * Author URI:        https://max-design.ro
 * License:           GPL-2.0-or-later
 * Update URI: https://github.com/sorinciupitu/jetsync
 * GitHub Plugin URI: https://github.com/sorinciupitu/jetsync
 * Primary Branch: main
 * Release Asset: true
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
define( 'JETSYNC_VERSION', '1.6.4' );
define( 'JETSYNC_PATH', \plugin_dir_path( __FILE__ ) );
define( 'JETSYNC_URL', \plugin_dir_url( __FILE__ ) );

// Load the Autoloader.
require_once JETSYNC_PATH . 'src/Core/Autoloader.php';

$autoloader = new \JetSync\Core\Autoloader( JETSYNC_PATH . 'src' );
$autoloader->register();

// ==========================================
// Plugin Update Checker Initialization
// ==========================================
$puc_path = JETSYNC_PATH . 'includes/plugin-update-checker/plugin-update-checker.php';

// Verificăm dacă fișierul există pentru a nu genera o eroare fatală dacă lipsește accidental
if ( file_exists( $puc_path ) ) {
    require_once $puc_path;

    // Folosim namespace-ul complet (\YahnisElsts...) pentru a fi compatibili cu structura ta de cod (declare strict_types)
    $myUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/sorinciupitu/jetsync',
    __FILE__,
    'jetsync'
);

    // Forțează verificarea prin GitHub Releases
$myUpdateChecker->getVcsApi()->enableReleaseAssets();

    // Setăm branch-ul principal din care să citească update-urile (main)
    $myUpdateChecker->setBranch( 'main' );

    // ATENȚIE: Dacă repository-ul este PRIVAT, decomentează linia de mai jos 
    // și adaugă Personal Access Token-ul (PAT) din GitHub:
    // $myUpdateChecker->setAuthentication( 'TOKENUL_TAU_GITHUB_AICI' );
}

// Register Activation and Deactivation hooks.
\register_activation_hook( __FILE__, [ \JetSync\Core\JetSync::class, 'activate' ] );
\register_deactivation_hook( __FILE__, [ \JetSync\Core\JetSync::class, 'deactivate' ] );

// Initialize the main plugin instance.
\add_action( 'plugins_loaded', function() {
    \JetSync\Core\JetSync::get_instance();
} );