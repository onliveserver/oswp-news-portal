<?php
/**
 * Version manager for tracking plugin versions.
 *
 * @package OSWP\Posts\Updates
 */

namespace OSWP\Posts\Updates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Version manager class that handles version tracking and checking.
 */
class Version_Manager {

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	protected $plugin_slug;

	/**
	 * Option key for storing version data.
	 *
	 * @var string
	 */
	protected $option_key;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_slug Plugin slug.
	 */
	public function __construct( $plugin_slug ) {
		$this->plugin_slug = $plugin_slug;
		$this->option_key  = 'oswp_' . $plugin_slug . '_version_data';
	}

	/**
	 * Record an update event.
	 *
	 * @param string $from_version From version number.
	 * @param string $to_version   To version number.
	 * @return bool
	 */
	public function record_update( $from_version, $to_version ) {
		$version_data = $this->get_version_data();

		$version_data['last_update'] = current_time( 'mysql' );
		$version_data['from_version'] = $from_version;
		$version_data['to_version']   = $to_version;
		$version_data['update_count'] = isset( $version_data['update_count'] ) ? $version_data['update_count'] + 1 : 1;

		if ( ! isset( $version_data['history'] ) ) {
			$version_data['history'] = [];
		}

		$version_data['history'][] = [
			'timestamp'   => current_time( 'mysql' ),
			'from'        => $from_version,
			'to'          => $to_version,
			'status'      => 'completed',
		];

		return update_option( $this->option_key, $version_data );
	}

	/**
	 * Get version data.
	 *
	 * @return array Version data.
	 */
	public function get_version_data() {
		$data = get_option( $this->option_key );

		if ( ! is_array( $data ) ) {
			$data = [];
		}

		return $data;
	}

	/**
	 * Get last update time.
	 *
	 * @return string|false Last update time or false if not available.
	 */
	public function get_last_update_time() {
		$data = $this->get_version_data();
		return isset( $data['last_update'] ) ? $data['last_update'] : false;
	}

	/**
	 * Get update history.
	 *
	 * @param int $limit Number of records to retrieve.
	 * @return array Update history.
	 */
	public function get_update_history( $limit = 10 ) {
		$data = $this->get_version_data();

		if ( ! isset( $data['history'] ) ) {
			return [];
		}

		return array_slice( $data['history'], -$limit );
	}

	/**
	 * Get total update count.
	 *
	 * @return int
	 */
	public function get_update_count() {
		$data = $this->get_version_data();
		return isset( $data['update_count'] ) ? $data['update_count'] : 0;
	}

	/**
	 * Check if update completed successfully.
	 *
	 * @return bool
	 */
	public function is_last_update_successful() {
		$data = $this->get_version_data();
		return isset( $data['history'] ) && ! empty( $data['history'] )
			? end( $data['history'] )['status'] === 'completed'
			: false;
	}

	/**
	 * Reset version data.
	 *
	 * @return bool
	 */
	public function reset() {
		return delete_option( $this->option_key );
	}

	/**
	 * Handle plugin upgrade completion.
	 *
	 * @param object $upgrader Upgrader instance.
	 * @param array  $hook_extras Hook extras.
	 * @return void
	 */
	public function on_upgrade( $upgrader, $hook_extras ) {
		// Record the update if it's our plugin
		if ( ! isset( $hook_extras['plugins'] ) ) {
			return;
		}

		foreach ( (array) $hook_extras['plugins'] as $plugin ) {
			if ( strpos( $plugin, $this->plugin_slug ) !== false ) {
				$current_version = get_option( 'oswp_' . $this->plugin_slug . '_version', '1.0.0' );
				$new_version = isset( $_REQUEST['version'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['version'] ) ) : '1.0.0';
				$this->record_update( $current_version, $new_version );
				break;
			}
		}
	}
}
