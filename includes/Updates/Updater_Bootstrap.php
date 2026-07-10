<?php
/**
 * Plugin Auto-Updater Bootstrap
 *
 * @package OSWP\Posts\Updates
 */

namespace OSWP\Posts\Updates;

use OSWP\Posts\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Initialize plugin auto-updater.
 */
class Updater_Bootstrap {

	/**
	 * Initialize the updater.
	 */
	public static function init() {
		try {
			// Register error handler
			Update_Error_Handler::register();

			// Define the remote API endpoint (wp-host)
			$api_endpoint = defined( 'OSWP_UPDATE_URL' ) 
				? OSWP_UPDATE_URL 
				: 'http://localhost/wp-host/';

			// Validate endpoint URL
			if ( empty( $api_endpoint ) ) {
				return;
			}

			// Create remote provider
			$remote_provider = new Remote_Provider( 
				'onliveserver',
				'oswp-news-portal',
				'oswp-news-portal' 
			);

			// Create updater instance
			$updater = new Updater(
				OSWP_POSTS_PLUGIN_FILE,
				'https://api.github.com/repos/onliveserver/oswp-news-portal',
				Plugin::VERSION,
				$remote_provider
			);

			// Initialize updater hooks
			$updater->init();

			// Disable SSL verification for localhost/local tests to prevent "SSL certificate problem: self-signed certificate"
			add_filter( 'http_request_args', function( $args, $url ) {
				if ( strpos( $url, 'localhost' ) !== false ) {
					$args['sslverify'] = false;
				}
				return $args;
			}, 10, 2 );

			// Create version manager
			$version_manager = new Version_Manager( 'oswp-news-portal' );

			// Hook into upgrader process
			add_action( 'upgrader_process_complete', [ $version_manager, 'on_upgrade' ], 10, 2 );
		} catch ( \Exception $e ) {
			// Log the error but don't break the plugin
			error_log( 'OSWP Updater Bootstrap Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Display update available notice in admin.
	 */
	public static function display_update_notice() {
		// Get plugin update transient
		$update_plugins = get_site_transient( 'update_plugins' );

		if ( ! $update_plugins || ! isset( $update_plugins->response ) ) {
			return;
		}

		$plugin_file = plugin_basename( OSWP_POSTS_PLUGIN_FILE );

		if ( ! isset( $update_plugins->response[ $plugin_file ] ) ) {
			return;
		}

		$plugin_update = $update_plugins->response[ $plugin_file ];

		echo '<div class="notice notice-info is-dismissible">';
		echo '<p>';
		echo wp_kses_post( sprintf(
			__( '<strong>OSWP News Portal</strong> %s is now available. <a href="%s">Update now</a>.', 'oswp-news-portal' ),
			esc_html( $plugin_update->new_version ),
			esc_url( wp_nonce_url( admin_url( 'update.php?action=upgrade-plugin&plugin=' . $plugin_file ), 'upgrade-plugin_' . $plugin_file ) )
		) );
		echo '</p>';
		echo '</div>';
	}
}

// Initialize updater on plugins_loaded hook
add_action( 'plugins_loaded', [ 'OSWP\\Posts\\Updates\\Updater_Bootstrap', 'init' ] );
