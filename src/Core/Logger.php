<?php
namespace JetSync\Core;

class Logger {

    private $prefix = '[JetSync]';

    /** Log an informational message */
    public function info( string $message ) {
        $this->log( 'INFO', $message );
    }

    /** Log an error / warning message */
    public function error( string $message ) {
        $this->log( 'ERROR', $message );
    }

    /** Internal helper – writes to PHP error log */
    private function log( string $level, string $message ) {
        $settings = get_option( 'jetsync_settings', [] );
        $debug_mode = false;
        if ( is_array( $settings ) ) {
            $debug_mode = ! empty( $settings['debug_mode'] );
        }

        if ( ! $debug_mode && ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) ) {
            return;
        }

        $line = sprintf( '%s %s: %s', $this->prefix, strtoupper( $level ), $message );
        error_log( $line );
    }
}
