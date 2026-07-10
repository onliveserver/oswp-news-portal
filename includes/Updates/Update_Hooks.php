<?php
/**
 * Update Hooks Manager
 *
 * Handles integration with WordPress update hooks.
 *
 * @package OSWP\Posts\Updates
 */

namespace OSWP\Posts\Updates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages WordPress upgrade hooks and version tracking.
 */
class Update_Hooks {

	/**
	 * Version manager instance.
	 *
	 * @var Version_Manager
	 */
	protected $version_manager;

	/**
	 * Current plugin version.
	 *
	 * @var string
	 */
	protected $current_version;

	/**
	 * Constructor.
	 *
	 * @param Version_Manager $version_manager Version manager instance.
	 * @param string          $current_version Current plugin version.
	 */
	public function __construct( Version_Manager $version_manager, $current_version ) {
		$this->version_manager = $version_manager;
		$this->current_version = $current_version;
	}

	/**
	 * Handle plugin upgrade completion.
	 *
	 * @param object $upgrader     WP_Upgrader instance.
	 * @param array  $hook_extras  Extra hook arguments.
	 */
	public function on_upgrader_process_complete( $upgrader, $hook_extras ) {
		// Check if this is a plugin update
		if ( ! isset( $hook_extras['type'] ) || 'plugin' !== $hook_extras['type'] ) {
			return;
		}

		// Check if our plugin was updated
		if ( ! isset( $hook_extras['plugins'] ) || ! is_array( $hook_extras['plugins'] ) ) {
			return;
		}

		$plugin_basename = plugin_basename( OSWP_POSTS_PLUGIN_FILE );

		if ( ! in_array( $plugin_basename, $hook_extras['plugins'], true ) ) {
			return;
		}

		// Get the new version
		$plugin_data = get_plugin_data( OSWP_POSTS_PLUGIN_FILE );
		$new_version = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : $this->current_version;

		// Record the update
		$this->version_manager->record_update( $this->current_version, $new_version );

		// Run any custom update actions
		do_action( 'oswp_plugin_updated', $this->current_version, $new_version );

		// Clear update cache
		wp_clean_plugins_cache();

		// Automatic cleanup of temporary offline update files and transients
		delete_transient( 'oswp_plugin_update_data_oswp-news-portal' );
		delete_site_transient( 'update_plugins' );

		$upload_dir = wp_upload_dir();
		if ( empty( $upload_dir['error'] ) ) {
			$zip_path = trailingslashit( $upload_dir['basedir'] ) . 'oswp-news-portal.zip';
			if ( file_exists( $zip_path ) ) {
				unlink( $zip_path );
			}
		}
	}

	/**
	 * Rename GitHub repository zip folder to oswp-news-portal.
	 *
	 * @param string $source        Path to temporary directory.
	 * @param string $remote_source Remote source path.
	 * @param object $upgrader      WP_Upgrader instance.
	 * @param array  $hook_extras   Extra upgrader arguments.
	 *
	 * @return string Source path.
	 */
	public function upgrader_source_selection( $source, $remote_source, $upgrader, $hook_extras = [] ) {
		$plugin_basename = plugin_basename( OSWP_POSTS_PLUGIN_FILE );

		if ( empty( $hook_extras['plugin'] ) || $plugin_basename !== $hook_extras['plugin'] ) {
			return $source;
		}

		// Exclude portal folder by deleting it recursively from temporary folder
		$portal_dir = trailingslashit( $source ) . 'portal';
		if ( is_dir( $portal_dir ) ) {
			$this->recursive_rmdir( $portal_dir );
		}

		$correct_dir = 'oswp-news-portal';
		$source_dir  = basename( $source );

		// If the source is the same as the remote source (no subfolder in ZIP), create the subfolder structure
		if ( untrailingslashit( $source ) === untrailingslashit( $remote_source ) ) {
			$new_source = trailingslashit( $remote_source ) . $correct_dir;
			if ( ! is_dir( $new_source ) ) {
				mkdir( $new_source );
			}

			// Move all files/folders from remote_source into new_source (except new_source itself)
			$files = scandir( $remote_source );
			foreach ( $files as $file ) {
				if ( '.' !== $file && '..' !== $file && $correct_dir !== $file ) {
					rename( trailingslashit( $remote_source ) . $file, trailingslashit( $new_source ) . $file );
				}
			}
			return $new_source;
		}

		if ( $source_dir === $correct_dir ) {
			return $source;
		}

		$new_source = trailingslashit( $remote_source ) . $correct_dir;

		if ( rename( $source, $new_source ) ) {
			return $new_source;
		}

		return $source;
	}

	/**
	 * Recursively delete a directory and its contents.
	 *
	 * @param string $dir Path to directory.
	 */
	protected function recursive_rmdir( $dir ) {
		if ( is_dir( $dir ) ) {
			$objects = scandir( $dir );
			foreach ( $objects as $object ) {
				if ( '.' !== $object && '..' !== $object ) {
					$path = $dir . DIRECTORY_SEPARATOR . $object;
					if ( is_dir( $path ) && ! is_link( $path ) ) {
						$this->recursive_rmdir( $path );
					} else {
						unlink( $path );
					}
				}
			}
			rmdir( $dir );
		}
	}

	/**
	 * Bypasses downloading for local plugin packages.
	 *
	 * @param bool|string|\WP_Error $reply        The current download reply.
	 * @param string                $package      The package URL.
	 * @param \WP_Upgrader          $upgrader     The WP_Upgrader instance.
	 * @param array                 $hook_extras  Extra arguments.
	 *
	 * @return bool|string|\WP_Error Local package path or original reply.
	 */
	public function upgrader_pre_download( $reply, $package, $upgrader, $hook_extras = [] ) {
		if ( false !== $reply ) {
			return $reply;
		}

		$plugin_basename = \plugin_basename( OSWP_POSTS_PLUGIN_FILE );

		if ( empty( $hook_extras['plugin'] ) || $plugin_basename !== $hook_extras['plugin'] ) {
			return $reply;
		}

		// Check if the package is a local zip file in the uploads directory
		if ( strpos( $package, 'oswp-news-portal.zip' ) !== false ) {
			$upload_dir = \wp_upload_dir();
			if ( empty( $upload_dir['error'] ) ) {
				$local_path = \trailingslashit( $upload_dir['basedir'] ) . 'oswp-news-portal.zip';
				if ( \file_exists( $local_path ) ) {
					return $local_path;
				}
			}
		}

		return $reply;
	}

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'upgrader_process_complete', [ $this, 'on_upgrader_process_complete' ], 10, 2 );
		add_filter( 'upgrader_source_selection', [ $this, 'upgrader_source_selection' ], 10, 4 );
		add_filter( 'upgrader_pre_download', [ $this, 'upgrader_pre_download' ], 10, 4 );
	}
}
