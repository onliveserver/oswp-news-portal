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

			// Intercept GitHub Release requests for local offline testing if the mock zip exists
			$zip_path = WP_CONTENT_DIR . '/uploads/oswp-news-portal.zip';
			if ( file_exists( $zip_path ) ) {
				add_filter( 'pre_http_request', function( $preempt, $parsed_args, $url ) {
					// Mock the GitHub Releases latest tag endpoint
					if ( strpos( $url, 'api.github.com/repos/onliveserver/oswp-news-portal/releases/latest' ) !== false ) {
						$version = \OSWP\Posts\Plugin::VERSION;
						$zip_path = WP_CONTENT_DIR . '/uploads/oswp-news-portal.zip';
						if ( file_exists( $zip_path ) && class_exists( 'ZipArchive' ) ) {
							$zip = new \ZipArchive();
							if ( $zip->open( $zip_path ) === true ) {
								$content = $zip->getFromName( 'oswp-news-portal/oswp-news-portal.php' );
								if ( $content && preg_match( '/Version:\s*([0-9.]+)/i', $content, $matches ) ) {
									$version = $matches[1];
								}
								$zip->close();
							}
						}

						$body = json_encode([
							'tag_name'    => 'v' . $version,
							'zipball_url' => 'https://api.github.com/repos/onliveserver/oswp-news-portal/zipball/v' . $version,
							'html_url'    => 'https://github.com/onliveserver/oswp-news-portal/releases/tag/v' . $version,
							'body'        => 'Testing GitHub Releases auto-updater with version ' . $version . '.',
						]);
						return [
							'headers'  => [ 'content-type' => 'application/json' ],
							'body'     => $body,
							'response' => [ 'code' => 200, 'message' => 'OK' ],
							'cookies'  => [],
							'filename' => null,
						];
					}

					// Mock the download of the zipball package
					if ( preg_match( '#api\.github\.com/repos/onliveserver/oswp-news-portal/zipball/v[0-9.]+#i', $url ) ) {
						$zip_path = WP_CONTENT_DIR . '/uploads/oswp-news-portal.zip';
						if ( file_exists( $zip_path ) ) {
							if ( ! empty( $parsed_args['filename'] ) ) {
								copy( $zip_path, $parsed_args['filename'] );
							}
							return [
								'headers'  => [ 'content-type' => 'application/zip' ],
								'body'     => empty( $parsed_args['filename'] ) ? file_get_contents( $zip_path ) : null,
								'response' => [ 'code' => 200, 'message' => 'OK' ],
								'cookies'  => [],
								'filename' => ! empty( $parsed_args['filename'] ) ? $parsed_args['filename'] : null,
							];
						}
					}
					return $preempt;
				}, 10, 3 );
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
			// Also add Authorization header for GitHub private repository requests if token is defined
			add_filter( 'http_request_args', function( $args, $url ) {
				if ( strpos( $url, 'localhost' ) !== false ) {
					$args['sslverify'] = false;
				}
				if ( defined( 'OSWP_GITHUB_TOKEN' ) && OSWP_GITHUB_TOKEN ) {
					if ( strpos( $url, 'api.github.com' ) !== false || strpos( $url, 'codeload.github.com' ) !== false ) {
						if ( ! isset( $args['headers'] ) ) {
							$args['headers'] = [];
						}
						$args['headers']['Authorization'] = 'token ' . OSWP_GITHUB_TOKEN;
					}
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
