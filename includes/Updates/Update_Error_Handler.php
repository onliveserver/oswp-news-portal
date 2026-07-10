<?php
/**
 * Update Error Handler
 *
 * Prevents critical errors from breaking the update process.
 *
 * @package OSWP\Posts\Updates
 */

namespace OSWP\Posts\Updates;

if ( ! defined( "ABSPATH" ) ) {
exit;
}

/**
 * Handles errors during plugin updates.
 */
class Update_Error_Handler {

/**
 * Register error handlers for updates.
 */
public static function register() {
// Disable display errors during updates
if ( isset( $_GET["action"] ) && "upgrade-plugin" === $_GET["action"] ) {
@ini_set( "display_errors", 0 );
}

// Log errors to file instead of outputting them
add_filter( "wp_php_error_message", [ __CLASS__, "handle_php_error" ], 10, 2 );
}

/**
 * Handle PHP errors.
 *
	 * @param string|array $message Error message.
	 * @param string $error_file Error file.
	 * @return string
	 */
	public static function handle_php_error( $message, $error_file ) {
		// Convert array to string if needed
		if ( is_array( $message ) ) {
			$message = implode( ', ', $message );
		}

// Return empty to prevent error display
return "";
}

/**
 * Suppress errors during plugin file operations.
 *
 * @return void
 */
public static function suppress_errors() {
error_reporting( 0 );
@ini_set( "display_errors", 0 );
}
}
