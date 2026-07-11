<?php
/**
 * Auth REST controller.
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

class Auth_Controller {

	const NAMESPACE = 'oswp/v1';

	protected $container;
	protected $settings;

	public function __construct( Service_Container $container ) {
		$this->container = $container;
		$this->settings  = $container->get( 'settings' );
	}

	public function register_routes() {
		register_rest_route( self::NAMESPACE, '/auth/me', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'me' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NAMESPACE, '/auth/login', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'login' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NAMESPACE, '/auth/register', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'register' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NAMESPACE, '/auth/logout', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'logout' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NAMESPACE, '/auth/forgot-password', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'forgot_password' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NAMESPACE, '/auth/verify-reset-otp', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'verify_reset_otp' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NAMESPACE, '/auth/reset-password', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'reset_password' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NAMESPACE, '/auth/verify-otp', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'verify_otp' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NAMESPACE, '/auth/resend-otp', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'resend_otp' ],
			'permission_callback' => '__return_true',
		] );
	}

	/**
	 * Current user info.
	 */
	public function me( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_REST_Response( [ 'user' => null ], 200 );
		}

		$user = wp_get_current_user();

		return new WP_REST_Response( [
			'user' => $this->format_user( $user ),
		], 200 );
	}

	/**
	 * Login.
	 */
	public function login( WP_REST_Request $request ) {
		$login    = sanitize_text_field( $request->get_param( 'login' ) );
		$password = $request->get_param( 'password' );

		if ( empty( $login ) || empty( $password ) ) {
			return new WP_Error( 'missing_fields', __( 'Email and password are required.', 'oswp-posts' ), [ 'status' => 400 ] );
		}

		$creds = [
			'user_login'    => $login,
			'user_password' => $password,
			'remember'      => true,
		];

		$user = wp_signon( $creds, is_ssl() );

		if ( is_wp_error( $user ) ) {
			return new WP_Error( 'login_failed', __( 'Invalid email or password.', 'oswp-posts' ), [ 'status' => 401 ] );
		}

		$verification = $this->container->get( 'module.auth_verify' );
		$method       = $this->settings->get( 'email_verification_method', 'otp' );

		if ( $verification && 'none' !== $method && ! $verification->is_verified( $user->ID ) ) {
			wp_logout();
			return new WP_REST_Response( [
				'needs_verification' => true,
				'message'            => __( 'Please verify your email before signing in.', 'oswp-posts' ),
			], 200 );
		}

		if ( $verification && ! $verification->is_active( $user->ID ) ) {
			wp_logout();
			return new WP_Error( 'account_inactive', __( 'Your account is not active. Contact support.', 'oswp-posts' ), [ 'status' => 403 ] );
		}

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );

		return new WP_REST_Response( [
			'user' => $this->format_user( $user ),
		], 200 );
	}

	/**
	 * Register.
	 */
	public function register( WP_REST_Request $request ) {
		$fields = $this->settings->get( 'registration_fields', [] );
		$data   = [];

		foreach ( $fields as $field ) {
			$id   = $field['id'];
			$type = $field['type'];

			if ( 'tab' === $type ) {
				continue;
			}

			$val = $request->get_param( $id );

			// Sanitize by type
			switch ( $type ) {
				case 'email':
					$data[ $id ] = sanitize_email( $val ?? '' );
					break;
				case 'url':
					$data[ $id ] = esc_url_raw( $val ?? '' );
					break;
				case 'password':
					$data[ $id ] = $val ?? '';
					break;
				default:
					$data[ $id ] = sanitize_text_field( $val ?? '' );
					break;
			}
		}

		// Validate required fields
		foreach ( $fields as $field ) {
			if ( 'tab' === $field['type'] ) {
				continue;
			}
			if ( ! empty( $field['required'] ) && empty( $data[ $field['id'] ] ) ) {
				return new WP_Error( 'validation', sprintf( __( '%s is required.', 'oswp-posts' ), $field['label'] ), [ 'status' => 422 ] );
			}
		}

		if ( ! empty( $data['email'] ) && ! is_email( $data['email'] ) ) {
			return new WP_Error( 'validation', __( 'Please provide a valid email address.', 'oswp-posts' ), [ 'status' => 422 ] );
		}

		if ( ! empty( $data['email'] ) && email_exists( $data['email'] ) ) {
			return new WP_Error( 'validation', __( 'This email is already registered.', 'oswp-posts' ), [ 'status' => 422 ] );
		}

		if ( ! empty( $data['password'] ) && strlen( $data['password'] ) < 8 ) {
			return new WP_Error( 'validation', __( 'Password must be at least 8 characters.', 'oswp-posts' ), [ 'status' => 422 ] );
		}

		// Generate username from email
		$email_prefix = strstr( $data['email'], '@', true );
		$username     = sanitize_user( $email_prefix, true );
		if ( username_exists( $username ) ) {
			$username = $username . wp_rand( 100, 9999 );
		}

		$role = $this->settings->get( 'default_registration_role', 'subscriber' );

		$user_id = wp_insert_user( [
			'user_login' => $username,
			'user_email' => $data['email'],
			'user_pass'  => $data['password'],
			'first_name' => $data['first_name'] ?? '',
			'last_name'  => $data['last_name'] ?? '',
			'role'        => $role,
		] );

		if ( is_wp_error( $user_id ) ) {
			return new WP_Error( 'registration_failed', $user_id->get_error_message(), [ 'status' => 500 ] );
		}

		// Save custom meta fields
		foreach ( $fields as $field ) {
			if ( 'tab' === $field['type'] || $field['is_builtin'] ?? false ) {
				continue;
			}
			$meta_key = $field['meta_key'] ?? $field['id'];
			if ( isset( $data[ $field['id'] ] ) ) {
				update_user_meta( $user_id, $meta_key, $data[ $field['id'] ] );
			}
		}

		// Set default meta
		$verification = $this->container->get( 'module.auth_verify' );
		$method       = $this->settings->get( 'email_verification_method', 'otp' );
		$email_service = $this->container->get( 'module.emails' );
		$already_verified = $verification ? $verification->is_verified( $user_id ) : false;
		$account_active   = $verification ? $verification->is_active( $user_id ) : true;

		// Send registration emails
		if ( $email_service ) {
			$email_service->send( 'user_registration', $user_id );
			$email_service->send( 'admin_notification', $user_id );
		}

		if ( $already_verified && $account_active ) {
			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id, true, is_ssl() );

			return new WP_REST_Response( [
				'user' => $this->format_user( get_userdata( $user_id ) ),
			], 201 );
		}

		if ( 'otp' === $method && $verification && ! $already_verified ) {
			$token = $verification->generate_token( $user_id );

			if ( $email_service ) {
				$email_service->send( 'verification_otp', $user_id, [
					'verification_code' => $token,
				] );
			}

			return new WP_REST_Response( [
				'needs_verification' => true,
				'message'            => __( 'Account created. Please verify your email.', 'oswp-posts' ),
			], 201 );
		}

		if ( 'none' === $method ) {
			if ( ! $account_active ) {
				return new WP_REST_Response( [
					'message' => __( 'Account created. An administrator still needs to activate it.', 'oswp-posts' ),
				], 201 );
			}

			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id, true, is_ssl() );

			return new WP_REST_Response( [
				'user' => $this->format_user( get_userdata( $user_id ) ),
			], 201 );
		}

		// Link-based verification
		if ( $email_service && $verification ) {
			$urls  = $this->container->get( 'urls' );
			$token = $verification->generate_token( $user_id );
			$email_service->send( 'verification', $user_id, [
				'verification_link' => $urls->get_verification_url( [
					'oswp_verify' => $user_id,
					'oswp_token'  => $token,
				] ),
			] );
		}

		return new WP_REST_Response( [
			'needs_verification' => true,
			'message'            => __( 'Account created. Check your email for verification.', 'oswp-posts' ),
		], 201 );
	}

	/**
	 * Logout.
	 */
	public function logout( WP_REST_Request $request ) {
		wp_logout();
		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	/**
	 * Forgot password - send OTP.
	 */
	public function forgot_password( WP_REST_Request $request ) {
		$email = sanitize_email( $request->get_param( 'email' ) );

		if ( empty( $email ) ) {
			return new WP_Error( 'missing_email', __( 'Email is required.', 'oswp-posts' ), [ 'status' => 400 ] );
		}

		$user = get_user_by( 'email', $email );

		// Always return success to avoid email enumeration
		if ( ! $user ) {
			return new WP_REST_Response( [ 'message' => __( 'If an account exists, a reset code has been sent.', 'oswp-posts' ) ], 200 );
		}

		$verification = $this->container->get( 'module.auth_verify' );
		if ( ! $verification ) {
			return new WP_Error( 'system_error', __( 'Reset system unavailable.', 'oswp-posts' ), [ 'status' => 500 ] );
		}

		$token = $verification->generate_token( $user->ID );

		$email_service = $this->container->get( 'module.emails' );
		if ( $email_service ) {
			$email_service->send( 'password_reset_otp', $user->ID, [
				'reset_code' => $token,
			] );
		}

		return new WP_REST_Response( [
			'message' => __( 'If an account exists, a reset code has been sent.', 'oswp-posts' ),
		], 200 );
	}

	/**
	 * Verify reset OTP.
	 */
	public function verify_reset_otp( WP_REST_Request $request ) {
		$email = sanitize_email( $request->get_param( 'email' ) );
		$code  = sanitize_text_field( $request->get_param( 'code' ) );

		if ( empty( $email ) || empty( $code ) ) {
			return new WP_Error( 'missing_fields', __( 'Email and code are required.', 'oswp-posts' ), [ 'status' => 400 ] );
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return new WP_Error( 'invalid_code', __( 'Invalid or expired code.', 'oswp-posts' ), [ 'status' => 400 ] );
		}

		$verification = $this->container->get( 'module.auth_verify' );
		if ( ! $verification ) {
			return new WP_Error( 'system_error', __( 'Verification unavailable.', 'oswp-posts' ), [ 'status' => 500 ] );
		}

		// Verify token without marking user as verified (just password reset)
		$hashed = get_user_meta( $user->ID, 'oswp_verification_token', true );
		if ( empty( $hashed ) || ! wp_check_password( $code, $hashed ) ) {
			return new WP_Error( 'invalid_code', __( 'Invalid or expired code.', 'oswp-posts' ), [ 'status' => 400 ] );
		}

		// Check OTP expiration
		$created = get_user_meta( $user->ID, 'oswp_verification_token_created', true );
		if ( $created ) {
			$elapsed = current_time( 'timestamp' ) - strtotime( $created );
			$expiry_hours = max( 1, absint( $this->settings->get( 'otp_expiry_hours', 24 ) ) );
			if ( $elapsed > ( $expiry_hours * HOUR_IN_SECONDS ) ) {
				return new WP_Error( 'expired_code', __( 'Code has expired. Please request a new one.', 'oswp-posts' ), [ 'status' => 400 ] );
			}
		}

		// Generate session token for password reset
		$session_token = wp_generate_password( 32, false );
		update_user_meta( $user->ID, 'oswp_password_reset_token', $session_token );
		update_user_meta( $user->ID, 'oswp_password_reset_expires', time() + 900 );

		// Clean up OTP
		delete_user_meta( $user->ID, 'oswp_verification_token' );
		delete_user_meta( $user->ID, 'oswp_verification_token_created' );

		return new WP_REST_Response( [
			'token' => $session_token,
		], 200 );
	}

	/**
	 * Reset password.
	 */
	public function reset_password( WP_REST_Request $request ) {
		$email    = sanitize_email( $request->get_param( 'email' ) );
		$token    = sanitize_text_field( $request->get_param( 'token' ) );
		$password = $request->get_param( 'password' );

		if ( empty( $email ) || empty( $token ) || empty( $password ) ) {
			return new WP_Error( 'missing_fields', __( 'All fields are required.', 'oswp-posts' ), [ 'status' => 400 ] );
		}

		if ( strlen( $password ) < 8 ) {
			return new WP_Error( 'weak_password', __( 'Password must be at least 8 characters.', 'oswp-posts' ), [ 'status' => 422 ] );
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return new WP_Error( 'invalid_request', __( 'Invalid reset request.', 'oswp-posts' ), [ 'status' => 400 ] );
		}

		$stored_token = get_user_meta( $user->ID, 'oswp_password_reset_token', true );
		$expires      = get_user_meta( $user->ID, 'oswp_password_reset_expires', true );

		if ( ! hash_equals( (string) $stored_token, (string) $token ) || time() > (int) $expires ) {
			return new WP_Error( 'expired_session', __( 'Reset session expired. Please start over.', 'oswp-posts' ), [ 'status' => 400 ] );
		}

		reset_password( $user, $password );

		delete_user_meta( $user->ID, 'oswp_password_reset_token' );
		delete_user_meta( $user->ID, 'oswp_password_reset_expires' );

		return new WP_REST_Response( [
			'message' => __( 'Password updated successfully.', 'oswp-posts' ),
		], 200 );
	}

	/**
	 * Verify registration OTP.
	 */
	public function verify_otp( WP_REST_Request $request ) {
		$email = sanitize_email( $request->get_param( 'email' ) );
		$code  = sanitize_text_field( $request->get_param( 'code' ) );

		if ( empty( $email ) || empty( $code ) ) {
			return new WP_Error( 'missing_fields', __( 'Email and code are required.', 'oswp-posts' ), [ 'status' => 400 ] );
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return new WP_Error( 'invalid_email', __( 'No account found.', 'oswp-posts' ), [ 'status' => 400 ] );
		}

		$verification = $this->container->get( 'module.auth_verify' );
		if ( ! $verification ) {
			return new WP_Error( 'system_error', __( 'Verification unavailable.', 'oswp-posts' ), [ 'status' => 500 ] );
		}

		$result = $verification->verify_token( $user->ID, $code );
		if ( ! $result ) {
			return new WP_Error( 'invalid_code', __( 'Invalid or expired verification code.', 'oswp-posts' ), [ 'status' => 400 ] );
		}

		// Auto-login
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );

		// Clean resend tracking
		delete_user_meta( $user->ID, 'oswp_otp_resend_count' );
		delete_user_meta( $user->ID, 'oswp_otp_last_resend' );

		return new WP_REST_Response( [
			'user' => $this->format_user( $user ),
		], 200 );
	}

	/**
	 * Resend OTP.
	 */
	public function resend_otp( WP_REST_Request $request ) {
		$email = sanitize_email( $request->get_param( 'email' ) );

		if ( empty( $email ) ) {
			return new WP_Error( 'missing_email', __( 'Email is required.', 'oswp-posts' ), [ 'status' => 400 ] );
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return new WP_Error( 'invalid_email', __( 'No account found.', 'oswp-posts' ), [ 'status' => 400 ] );
		}

		// Rate limiting
		$max_resends      = absint( $this->settings->get( 'otp_max_resends', 3 ) );
		$cooldown_minutes = absint( $this->settings->get( 'otp_resend_cooldown', 2 ) );
		$resend_count     = absint( get_user_meta( $user->ID, 'oswp_otp_resend_count', true ) );
		$last_resend      = get_user_meta( $user->ID, 'oswp_otp_last_resend', true );

		if ( $max_resends > 0 && $resend_count >= $max_resends ) {
			return new WP_Error( 'limit_reached', __( 'Maximum resend attempts reached.', 'oswp-posts' ), [ 'status' => 429 ] );
		}

		if ( $last_resend ) {
			$elapsed = time() - strtotime( $last_resend );
			if ( $elapsed < $cooldown_minutes * 60 ) {
				return new WP_Error( 'cooldown', __( 'Please wait before requesting another code.', 'oswp-posts' ), [ 'status' => 429 ] );
			}
		}

		$verification = $this->container->get( 'module.auth_verify' );
		if ( ! $verification ) {
			return new WP_Error( 'system_error', __( 'Verification unavailable.', 'oswp-posts' ), [ 'status' => 500 ] );
		}

		$token = $verification->generate_token( $user->ID );

		$email_service = $this->container->get( 'module.emails' );
		if ( $email_service ) {
			$email_service->send( 'verification_otp', $user->ID, [
				'verification_code' => $token,
			] );
		}

		update_user_meta( $user->ID, 'oswp_otp_resend_count', $resend_count + 1 );
		update_user_meta( $user->ID, 'oswp_otp_last_resend', current_time( 'mysql' ) );

		return new WP_REST_Response( [
			'message' => __( 'Verification code resent.', 'oswp-posts' ),
		], 200 );
	}

	/**
	 * Format user data for API response.
	 */
	protected function format_user( $user ) {
		return [
			'id'         => $user->ID,
			'email'      => $user->user_email,
			'first_name' => $user->first_name,
			'last_name'  => $user->last_name,
			'role'       => $user->roles[0] ?? '',
			'verified'   => (bool) get_user_meta( $user->ID, 'oswp_verified', true ),
		];
	}
}
