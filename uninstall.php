<?php
declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

require_once __DIR__ . '/src/Core/Autoloader.php';

$loader = new \JetSync\Core\Autoloader( __DIR__ . '/src' );
$loader->register();

\JetSync\Core\Uninstaller::uninstall();

