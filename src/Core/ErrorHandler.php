<?php
namespace JetSync\Core;

class ErrorHandler {

    /**
     * Register a filter that captures any wp_die messages.
     */
    public static function register() {
        add_filter( 'wp_die_messages', [ __CLASS__, 'handle_wp_die_message' ] );
    }

    /**
     * Transform WP_Error objects into log entries.
     *
     * @param mixed $message The value hooked to `wp_die_messages`.
     * @return mixed
     */
    public static function handle_wp_die_message( $message ) {
        if ( is_wp_error( $message ) ) {
            $error = $message->get_error_message();
            error_log( '[JetSync] WP_Error: ' . $error );
        } else {
            // Anything else (e.g., direct wp_die) is also logged.
            error_log( '[JetSync] Unexpected wp_die: ' . $message );
        }

        return $message;
    }
}
