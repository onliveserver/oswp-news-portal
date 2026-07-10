<?php
/**
 * Plugin auto-updater class.
 *
 * @package OSWP\Posts\Updates
 */

namespace OSWP\Posts\Updates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main updater class that handles plugin update checks and installations.
 */
class Updater {

	/**
	 * Plugin file path.
	 *
	 * @var string
	 */
	protected $plugin_file;

	/**
	 * Update endpoint URL.
	 *
	 * @var string
	 */
	protected $update_url;

	/**
	 * Current plugin version.
	 *
	 * @var string
	 */
	protected $current_version;

	/**
	 * Remote provider instance.
	 *
	 * @var Remote_Provider
	 */
	protected $remote_provider;

	/**
	 * Constructor.
	 *
	 * @param string         $plugin_file      Plugin file path.
	 * @param string         $update_url       Update endpoint URL.
	 * @param string         $current_version  Current plugin version.
	 * @param Remote_Provider $remote_provider  Remote provider instance.
	 */
	public function __construct( $plugin_file, $update_url, $current_version, Remote_Provider $remote_provider ) {
		$this->plugin_file      = $plugin_file;
		$this->update_url       = $update_url;
		$this->current_version  = $current_version;
		$this->remote_provider  = $remote_provider;
	}

	/**
	 * Initialize the updater.
	 */
	public function init() {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_updates' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_api' ], 10, 3 );
	}

	/**
	 * Check for plugin updates.
	 *
	 * @param object $transient WordPress update transient.
	 * @return object Modified transient.
	 */
	public function check_for_updates( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote_version = $this->remote_provider->get_latest_version();

		if ( ! $remote_version ) {
			return $transient;
		}

		if ( version_compare( $this->current_version, $remote_version, '<' ) ) {
			$plugin_slug = plugin_basename( $this->plugin_file );

			$update_data = $this->remote_provider->get_plugin_update_data();

			if ( $update_data ) {
				$transient->response[ $plugin_slug ] = $update_data;
			}
		}

		return $transient;
	}

	/**
	 * Handle plugins_api filter.
	 *
	 * @param false|object|array $result Result from plugins_api.
	 * @param string             $action Action being performed.
	 * @param object             $args   Arguments for the action.
	 * @return false|object Modified result.
	 */
	public function plugin_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		$plugin_slug = plugin_basename( $this->plugin_file );

		if ( ! isset( $args->slug ) || $args->slug !== $plugin_slug ) {
			return $result;
		}

		$plugin_data = $this->remote_provider->get_plugin_info();

		if ( $plugin_data ) {
			return $plugin_data;
		}

		return $result;
	}

	/**
	 * Get the current remote version.
	 *
	 * @return string|false Remote version or false if not found.
	 */
	public function get_remote_version() {
		return $this->remote_provider->get_latest_version();
	}

	/**
	 * Check if an update is available.
	 *
	 * @return bool
	 */
	public function has_update_available() {
		$remote_version = $this->get_remote_version();
		return $remote_version && version_compare( $this->current_version, $remote_version, '<' );
	}
}
