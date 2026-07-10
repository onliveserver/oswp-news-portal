<?php
/**
 * URL manager helper.
 *
 * Centralizes generation and manipulation of plugin URLs (login, register,
 * dashboard, reset password, verification, etc.) and preserves query params.
 *
 * @package OSWP\Posts\Core
 */

namespace OSWP\Posts\Core;

use OSWP\Posts\Portal\Portal_Page;
use OSWP\Posts\Settings\Settings_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Url_Manager {
	/** @var \OSWP\Posts\Core\Service_Container */
	protected $container;

	/** @var Settings_Repository */
	protected $settings;

	public function __construct( $container ) {
		$this->container = $container;
		$this->settings  = $container->get( 'settings' );
	}

	/**
	 * Generic getter for named URLs.
	 *
	 * @param string $key    One of: login, register, dashboard, reset_password, verification, registration
	 * @param array  $params Query params to apply
	 * @return string URL
	 */
	public function get( $key, array $params = [] ) {
		switch ( $key ) {
			case 'login':
				return $this->get_login_url( $params );
			case 'register':
			case 'registration':
				return $this->get_register_url( $params );
			case 'dashboard':
				return $this->get_dashboard_url( $params );
			case 'reset_password':
				return $this->get_reset_password_url( $params );
			case 'verification':
				return $this->get_verification_url( $params );
			default:
				return $this->build( home_url(), $params );
		}
	}

	/** Return login URL (filterable). */
	public function get_login_url( array $params = [] ) {
		$url = apply_filters( 'oswp_login_url', null );
		if ( ! $url ) {
			// Default to the React portal route rather than shortcode pages.
			$url = home_url( '/' . Portal_Page::SLUG . '/login' );
		}
		return $this->build( $url, $params );
	}

	/** Return registration URL (filterable). */
	public function get_register_url( array $params = [] ) {
		$url = apply_filters( 'oswp_register_url', null );
		if ( ! $url ) {
			$url = home_url( '/' . Portal_Page::SLUG . '/register' );
		}
		return $this->build( $url, $params );
	}

	/** Return dashboard URL (filterable). */
	public function get_dashboard_url( array $params = [] ) {
		$url = apply_filters( 'oswp_dashboard_url', null );
		if ( ! $url ) {
			$url = home_url( '/' . Portal_Page::SLUG . '/dashboard' );
		}
		return $this->build( $url, $params );
	}

	/** Return reset password URL (filterable). */
	public function get_reset_password_url( array $params = [] ) {
		$url = apply_filters( 'oswp_reset_password_url', null );
		if ( ! $url ) {
			$url = home_url( '/' . Portal_Page::SLUG . '/reset-password' );
		}
		return $this->build( $url, $params );
	}

	/** Return forgot password URL (filterable). */
	public function get_forgot_password_url( array $params = [] ) {
		$url = apply_filters( 'oswp_forgot_password_url', null );
		if ( ! $url ) {
			$url = home_url( '/' . Portal_Page::SLUG . '/forgot-password' );
		}
		return $this->build( $url, $params );
	}

	/** Return verification page URL. */
	public function get_verification_url( array $params = [] ) {
		$url = home_url( '/' . Portal_Page::SLUG . '/verify' );
		return $this->build( $url, $params );
	}

	/**
	 * Build a URL with query params (preserves existing query string when string passed).
	 *
	 * @param string $base   Base URL.
	 * @param array  $params Query params to add/replace.
	 * @return string
	 */
	public function build( $base, array $params = [] ) {
		if ( empty( $params ) ) {
			return $base;
		}
		return add_query_arg( $params, $base );
	}

	/**
	 * Find a published page that contains a given shortcode tag.
	 *
	 * @param string $shortcode_tag Shortcode tag (without brackets)
	 * @return string|null Permalink or null
	 */
	public function find_page_with_shortcode( $shortcode_tag ) {
		if ( empty( $shortcode_tag ) ) {
			return null;
		}

		// 1) Prefer explicitly configured page if it contains the shortcode
		$settings = get_option( 'oswp_posts_settings', [] );
		$page_id_key = str_replace( 'oswp_', '', $shortcode_tag ) . '_page_id'; // e.g., 'login_page_id'
		$page_id = absint( $settings[ $page_id_key ] ?? 0 );
		if ( $page_id ) {
			$content = get_post_field( 'post_content', $page_id );
			if ( ! empty( $content ) && ( has_shortcode( $content, $shortcode_tag ) || false !== strpos( $content, '[' . $shortcode_tag ) ) ) {
				return get_permalink( $page_id );
			}
		}

		// 2) Try a page whose path/slug matches the shortcode tag
		$post_by_path = get_page_by_path( $shortcode_tag );
		if ( $post_by_path && 'publish' === get_post_status( $post_by_path ) ) {
			$content = get_post_field( 'post_content', $post_by_path->ID );
			if ( empty( $content ) || has_shortcode( $content, $shortcode_tag ) || false !== strpos( $content, '[' . $shortcode_tag ) ) {
				return get_permalink( $post_by_path );
			}
		}

		// 3) Search all published pages for the shortcode
		$pages = get_posts([
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		]);

		$needle = '[' . $shortcode_tag;
		$pattern = '/\[' . preg_quote( $shortcode_tag, '/' ) . '\b/';

		foreach ( $pages as $page_id ) {
			$content = get_post_field( 'post_content', $page_id );
			if ( empty( $content ) ) {
				continue;
			}

			if ( has_shortcode( $content, $shortcode_tag ) || false !== strpos( $content, $needle ) || preg_match( $pattern, $content ) ) {
				return get_permalink( $page_id );
			}
		}

		return null;
	}
}
