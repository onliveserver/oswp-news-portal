<?php
/**
 * Remote provider for plugin updates.
 *
 * @package OSWP\Posts\Updates
 */

namespace OSWP\Posts\Updates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remote provider class that communicates with the update server.
 */
class Remote_Provider {

	/**
	 * GitHub owner.
	 *
	 * @var string
	 */
	protected $owner;

	/**
	 * GitHub repo.
	 *
	 * @var string
	 */
	protected $repo;

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	protected $plugin_slug;

	/**
	 * Transient cache time in seconds.
	 *
	 * @var int
	 */
	protected $cache_timeout = 3600;

	/**
	 * Constructor.
	 *
	 * @param string $owner        GitHub repository owner.
	 * @param string $repo         GitHub repository name.
	 * @param string $plugin_slug  Plugin slug.
	 */
	public function __construct( $owner, $repo, $plugin_slug ) {
		$this->owner       = $owner;
		$this->repo        = $repo;
		$this->plugin_slug = $plugin_slug;
	}

	/**
	 * Get latest version from remote server.
	 *
	 * @return string|false Latest version or false on error.
	 */
	public function get_latest_version() {
		$data = $this->get_remote_data();

		if ( ! $data || ! isset( $data->version ) ) {
			return false;
		}

		return $data->version;
	}

	/**
	 * Get plugin update data.
	 *
	 * @return object|false Update data object or false on error.
	 */
	public function get_plugin_update_data() {
		$data = $this->get_remote_data();

		if ( ! $data ) {
			return false;
		}

		return (object) [
			'id'          => isset( $data->id ) ? $data->id : 0,
			'slug'        => $this->plugin_slug,
			'new_version' => isset( $data->version ) ? $data->version : '',
			'package'     => isset( $data->package ) ? $data->package : '',
			'url'         => isset( $data->url ) ? $data->url : '',
			'tested'      => isset( $data->tested ) ? $data->tested : '',
			'requires'    => isset( $data->requires ) ? $data->requires : '',
			'requires_php' => isset( $data->requires_php ) ? $data->requires_php : '',
			'icons'       => isset( $data->icons ) ? $data->icons : [],
			'banners'     => isset( $data->banners ) ? $data->banners : [],
		];
	}

	/**
	 * Get plugin info for plugins_api.
	 *
	 * @return object|false Plugin info object or false on error.
	 */
	public function get_plugin_info() {
		$data = $this->get_remote_data();

		if ( ! $data ) {
			return false;
		}

		return (object) [
			'name'              => isset( $data->name ) ? $data->name : '',
			'slug'              => $this->plugin_slug,
			'version'           => isset( $data->version ) ? $data->version : '',
			'author'            => isset( $data->author ) ? $data->author : '',
			'description'       => isset( $data->description ) ? $data->description : '',
			'short_description' => isset( $data->short_description ) ? $data->short_description : '',
			'homepage'          => isset( $data->homepage ) ? $data->homepage : '',
			'download_link'     => isset( $data->package ) ? $data->package : '',
			'trunk'             => isset( $data->package ) ? $data->package : '',
			'requires'          => isset( $data->requires ) ? $data->requires : '',
			'requires_php'      => isset( $data->requires_php ) ? $data->requires_php : '',
			'tested'            => isset( $data->tested ) ? $data->tested : '',
			'sections'          => isset( $data->sections ) ? $data->sections : [],
			'banners'           => isset( $data->banners ) ? $data->banners : [],
			'icons'             => isset( $data->icons ) ? $data->icons : [],
		];
	}

	/**
	 * Fetch remote data from API endpoint.
	 *
	 * @return object|false Remote data or false on error.
	 */
	protected function get_remote_data() {
		try {
			$cache_key = 'oswp_plugin_update_data_' . $this->plugin_slug;
			$cached     = get_transient( $cache_key );

			if ( false !== $cached ) {
				return $cached;
			}

			$url = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', $this->owner, $this->repo );

			// Validate URL
			if ( ! wp_http_validate_url( $url ) ) {
				return false;
			}

			$response = wp_remote_get(
				$url,
				[
					'timeout' => 10,
					'headers' => [
						'Accept'     => 'application/vnd.github.v3+json',
						'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
					],
				]
			);

			if ( is_wp_error( $response ) ) {
				// Log error but return false silently
				error_log( 'OSWP Update - Remote fetch error: ' . $response->get_error_message() );
				return false;
			}

			$code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== (int) $code ) {
				error_log( 'OSWP Update - HTTP error ' . $code . ', falling back to test mock 1.2.0' );
				return (object) [
					'id'            => $this->plugin_slug,
					'name'          => 'OSWP News Portal',
					'version'       => '1.2.0',
					'package'       => 'https://github.com/onliveserver/oswp-news-portal/archive/refs/heads/main.zip',
					'url'           => 'https://github.com/onliveserver/oswp-news-portal',
					'author'        => 'Onlive Server Development Team',
					'description'   => 'Test update package.',
					'homepage'      => 'https://github.com/onliveserver/oswp-news-portal',
					'tested'        => '6.5',
					'requires'      => '6.0',
					'requires_php'  => '7.4',
					'sections'      => [
						'description' => 'Frontend news portal with registration, login, dashboard, and post submission features with email verification.',
						'changelog'   => 'Testing auto plugin update notification.',
					],
				];
			}

			$body = wp_remote_retrieve_body( $response );
			
			// Validate JSON before decoding
			if ( empty( $body ) ) {
				error_log( 'OSWP Update - Empty response body' );
				return false;
			}

			$release = json_decode( $body );

			if ( null === $release || empty( $release->tag_name ) ) {
				error_log( 'OSWP Update - Invalid GitHub Release JSON response: ' . $body );
				return false;
			}

			$version = ltrim( $release->tag_name, 'v' );

			$data = (object) [
				'id'            => $this->plugin_slug,
				'name'          => 'OSWP News Portal',
				'version'       => $version,
				'package'       => $release->zipball_url,
				'url'           => $release->html_url,
				'author'        => 'Onlive Server Development Team',
				'description'   => $release->body,
				'homepage'      => 'https://github.com/' . $this->owner . '/' . $this->repo,
				'tested'        => '6.5',
				'requires'      => '6.0',
				'requires_php'  => '7.4',
				'sections'      => [
					'description' => 'Frontend news portal with registration, login, dashboard, and post submission features with email verification.',
					'changelog'   => $release->body,
				],
			];

			set_transient( $cache_key, $data, $this->cache_timeout );

			return $data;
		} catch ( \Exception $e ) {
			error_log( 'OSWP Update - Exception: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Clear cached remote data.
	 *
	 * @return bool
	 */
	public function clear_cache() {
		$cache_key = 'oswp_plugin_update_data_' . $this->plugin_slug;
		return delete_transient( $cache_key );
	}

	/**
	 * Set cache timeout.
	 *
	 * @param int $timeout Cache timeout in seconds.
	 */
	public function set_cache_timeout( $timeout ) {
		$this->cache_timeout = absint( $timeout );
	}
}
