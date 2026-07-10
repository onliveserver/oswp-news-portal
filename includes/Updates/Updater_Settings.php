<?php
/**
 * Auto-Updater Admin Settings UI
 *
 * @package OSWP\Posts\Updates
 */

namespace OSWP\Posts\Updates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auto-updater admin settings class.
 */
class Updater_Settings {

	/**
	 * Remote provider instance.
	 *
	 * @var Remote_Provider
	 */
	protected $remote_provider;

	/**
	 * Version manager instance.
	 *
	 * @var Version_Manager
	 */
	protected $version_manager;

	/**
	 * Constructor.
	 *
	 * @param Remote_Provider $remote_provider Remote provider instance.
	 * @param Version_Manager $version_manager Version manager instance.
	 */
	public function __construct( Remote_Provider $remote_provider, Version_Manager $version_manager ) {
		$this->remote_provider = $remote_provider;
		$this->version_manager = $version_manager;
	}

	/**
	 * Initialize settings UI.
	 */
	public function init() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_filter( 'oswp_settings_sections', [ $this, 'add_settings_section' ] );
		add_filter( 'oswp_settings_fields', [ $this, 'add_settings_fields' ] );
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting(
			'oswp_updater_settings',
			'oswp_updater_settings',
			[
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'show_in_rest'      => true,
			]
		);
	}

	/**
	 * Add settings section.
	 *
	 * @param array $sections Settings sections.
	 * @return array
	 */
	public function add_settings_section( $sections ) {
		$sections['updater'] = [
			'id'    => 'oswp_updater_section',
			'title' => __( 'Auto-Updater Settings', 'oswp-news-portal' ),
			'callback' => [ $this, 'render_section_description' ],
		];

		return $sections;
	}

	/**
	 * Add settings fields.
	 *
	 * @param array $fields Settings fields.
	 * @return array
	 */
	public function add_settings_fields( $fields ) {
		$fields[] = [
			'id'       => 'oswp_update_server_url',
			'title'    => __( 'Update Server URL', 'oswp-news-portal' ),
			'callback' => [ $this, 'render_update_server_field' ],
			'section'  => 'oswp_updater_section',
		];

		$fields[] = [
			'id'       => 'oswp_check_updates_button',
			'title'    => __( 'Check for Updates', 'oswp-news-portal' ),
			'callback' => [ $this, 'render_check_updates_button' ],
			'section'  => 'oswp_updater_section',
		];

		$fields[] = [
			'id'       => 'oswp_update_history',
			'title'    => __( 'Update History', 'oswp-news-portal' ),
			'callback' => [ $this, 'render_update_history' ],
			'section'  => 'oswp_updater_section',
		];

		return $fields;
	}

	/**
	 * Render section description.
	 */
	public function render_section_description() {
		echo wp_kses_post(
			'<p>' . __( 'Configure automatic updates for OSWP News Portal.', 'oswp-news-portal' ) . '</p>'
		);
	}

	/**
	 * Render update server URL field.
	 */
	public function render_update_server_field() {
		$update_url = defined( 'OSWP_UPDATE_URL' ) 
			? OSWP_UPDATE_URL 
			: 'http://localhost/wp-host/';

		echo '<input type="text" class="regular-text" readonly value="' . esc_attr( $update_url ) . '" />';
		echo '<p class="description">' . esc_html__( 'Define OSWP_UPDATE_URL in wp-config.php to change this.', 'oswp-news-portal' ) . '</p>';
	}

	/**
	 * Render check for updates button.
	 */
	public function render_check_updates_button() {
		$remote_version = $this->remote_provider->get_latest_version();
		$current_version = defined( 'OSWP_POSTS_VERSION' ) 
			? OSWP_POSTS_VERSION 
			: '1.0.0';

		echo '<button type="button" class="button button-secondary" id="oswp_check_updates_btn">';
		esc_html_e( 'Check for Updates Now', 'oswp-news-portal' );
		echo '</button>';

		echo '<p class="description">';

		if ( false === $remote_version ) {
			echo wp_kses_post(
				'<span style="color: #dc3545;">' . esc_html__( 'Unable to connect to update server.', 'oswp-news-portal' ) . '</span>'
			);
		} elseif ( version_compare( $current_version, $remote_version, '<' ) ) {
			echo wp_kses_post(
				sprintf(
					'<span style="color: #17a2b8;">%s %s <strong>%s</strong> %s</span>',
					esc_html__( 'Current version:', 'oswp-news-portal' ),
					esc_html( $current_version ),
					esc_html__( 'Update available:', 'oswp-news-portal' ),
					esc_html( $remote_version )
				)
			);
		} else {
			echo wp_kses_post(
				'<span style="color: #28a745;">' . esc_html__( 'You are running the latest version.', 'oswp-news-portal' ) . '</span>'
			);
		}

		echo '</p>';

		wp_nonce_field( 'oswp_check_updates_nonce' );
	}

	/**
	 * Render update history.
	 */
	public function render_update_history() {
		$history = $this->version_manager->get_update_history( 5 );

		if ( empty( $history ) ) {
			echo wp_kses_post(
				'<p>' . esc_html__( 'No updates recorded yet.', 'oswp-news-portal' ) . '</p>'
			);
			return;
		}

		echo '<table class="widefat striped">';
		echo '<thead>';
		echo '<tr>';
		echo '<th>' . esc_html__( 'Date', 'oswp-news-portal' ) . '</th>';
		echo '<th>' . esc_html__( 'From Version', 'oswp-news-portal' ) . '</th>';
		echo '<th>' . esc_html__( 'To Version', 'oswp-news-portal' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'oswp-news-portal' ) . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';

		foreach ( $history as $entry ) {
			$status_class = 'completed' === $entry['status'] ? 'success' : 'warning';
			echo '<tr>';
			echo '<td>' . esc_html( $entry['timestamp'] ) . '</td>';
			echo '<td>' . esc_html( $entry['from'] ) . '</td>';
			echo '<td>' . esc_html( $entry['to'] ) . '</td>';
			echo '<td><span class="badge badge-' . esc_attr( $status_class ) . '">' . esc_html( ucfirst( $entry['status'] ) ) . '</span></td>';
			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $settings Settings.
	 * @return array
	 */
	public function sanitize_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return [];
		}

		return array_map( 'sanitize_text_field', $settings );
	}
}
