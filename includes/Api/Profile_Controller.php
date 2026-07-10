<?php
/**
 * Profile REST controller.
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Api;

use OSWP\Posts\Core\Service_Container;
use OSWP\Posts\Settings\Settings_Repository;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Profile_Controller {

	const NAMESPACE = 'oswp/v1';

	protected $container;
	protected $settings;

	public function __construct( Service_Container $container ) {
		$this->container = $container;
		$this->settings  = $container->get( 'settings' );
	}

	public function register_routes() {
		register_rest_route( self::NAMESPACE, '/profile', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_profile' ],
				'permission_callback' => 'is_user_logged_in',
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_profile' ],
				'permission_callback' => 'is_user_logged_in',
			],
		] );

		register_rest_route( self::NAMESPACE, '/profile/password', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'change_password' ],
			'permission_callback' => 'is_user_logged_in',
		] );
	}

	/**
	 * Get current user profile.
	 */
	public function get_profile( WP_REST_Request $request ) {
		$user   = wp_get_current_user();
		$fields = $this->settings->get( 'registration_fields', [] );
		$data   = [
			'id'         => $user->ID,
			'email'      => $user->user_email,
			'first_name' => $user->first_name,
			'last_name'  => $user->last_name,
			'meta'       => [],
		];

		foreach ( $fields as $field ) {
			if ( 'tab' === $field['type'] || ( $field['is_builtin'] ?? false ) ) {
				continue;
			}
			$meta_key = $field['meta_key'] ?? $field['id'];
			$data['meta'][ $field['id'] ] = get_user_meta( $user->ID, $meta_key, true );
		}

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Update profile.
	 */
	public function update_profile( WP_REST_Request $request ) {
		$user   = wp_get_current_user();
		$fields = $this->settings->get( 'registration_fields', [] );

		$user_data = [ 'ID' => $user->ID ];

		foreach ( $fields as $field ) {
			if ( 'tab' === $field['type'] || 'password' === $field['id'] || 'email' === $field['id'] ) {
				continue;
			}

			$val = $request->get_param( $field['id'] );
			if ( null === $val ) {
				continue;
			}

			$val = sanitize_text_field( $val );

			if ( $field['is_builtin'] ?? false ) {
				$user_data[ $field['id'] ] = $val;
			} else {
				$meta_key = $field['meta_key'] ?? $field['id'];
				update_user_meta( $user->ID, $meta_key, $val );
			}
		}

		$result = wp_update_user( $user_data );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'update_failed', $result->get_error_message(), [ 'status' => 500 ] );
		}

		return new WP_REST_Response( [ 'message' => __( 'Profile updated.', 'oswp-posts' ) ], 200 );
	}

	/**
	 * Change password.
	 */
	public function change_password( WP_REST_Request $request ) {
		$user    = wp_get_current_user();
		$current = $request->get_param( 'current_password' );
		$new     = $request->get_param( 'new_password' );

		if ( empty( $current ) || empty( $new ) ) {
			return new WP_Error( 'missing_fields', __( 'Both passwords are required.', 'oswp-posts' ), [ 'status' => 400 ] );
		}

		if ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
			return new WP_Error( 'wrong_password', __( 'Current password is incorrect.', 'oswp-posts' ), [ 'status' => 400 ] );
		}

		if ( strlen( $new ) < 8 ) {
			return new WP_Error( 'weak_password', __( 'New password must be at least 8 characters.', 'oswp-posts' ), [ 'status' => 422 ] );
		}

		wp_set_password( $new, $user->ID );

		// Re-authenticate
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );

		return new WP_REST_Response( [ 'message' => __( 'Password changed.', 'oswp-posts' ) ], 200 );
	}
}
