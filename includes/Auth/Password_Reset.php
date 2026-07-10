<?php
/**
 * Password reset handler.
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Auth;

use OSWP\Posts\Core\Service_Container;
use OSWP\Posts\Emails\Email_Service;
use OSWP\Posts\Settings\Settings_Repository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles forgot password + reset forms.
 */
class Password_Reset {
	/**
	 * Container.
	 *
	 * @var Service_Container
	 */
	protected $container;

	/**
	 * Settings.
	 *
	 * @var Settings_Repository
	 */
	protected $settings;

	/**
	 * View loader.
	 *
	 * @var \OSWP\Posts\Core\Template_Loader
	 */
	protected $view;

	/**
	 * Forgot form errors.
	 *
	 * @var WP_Error
	 */
	protected $forgot_errors;

	/**
	 * Reset form errors.
	 *
	 * @var WP_Error
	 */
	protected $reset_errors;

	/**
	 * Constructor.
	 *
	 * @param Service_Container $container Container.
	 */
	public function __construct( Service_Container $container ) {
		$this->container    = $container;
		$this->settings     = $container->get( 'settings' );
		$this->view         = $container->get( 'view' );
		$this->forgot_errors = new WP_Error();
		$this->reset_errors  = new WP_Error();
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', [ $this, 'maybe_handle_requests' ] );
	}

	/**
	 * Handle both forgot + reset requests.
	 */
	public function maybe_handle_requests() {
		if ( ! empty( $_POST['oswp_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$action = sanitize_key( wp_unslash( $_POST['oswp_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			if ( 'forgot_password' === $action ) {
				$this->handle_forgot_request();
			} elseif ( 'reset_password' === $action ) {
				$this->handle_reset_request();
			}
		}
	}

	/**
	 * Render forgot password form.
	 *
	 * @param array $flash Flash message.
	 *
	 * @return string
	 */
	public function render_forgot_form( array $flash = [] ) {
		return $this->view->render(
			'auth/forgot-password-form',
			[
				'errors'     => $this->forgot_errors,
				'flash'      => $flash,
				'login_url'  => $this->get_login_url(),
			]
		);
	}

	/**
	 * Render reset password form.
	 *
	 * @param array $flash Flash message.
	 *
	 * @return string
	 */
	public function render_reset_form( array $flash = [] ) {
		$step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$user_email = isset( $_GET['user_email'] ) ? sanitize_email( wp_unslash( $_GET['user_email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$user_id = isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		return $this->view->render(
			'auth/reset-password-form',
			[
				'errors'               => $this->reset_errors,
				'flash'                => $flash,
				'step'                 => $step,
				'user_email'           => $user_email,
				'user_id'              => $user_id,
				'token'                => $token,
				'login_url'            => $this->get_login_url(),
				'forgot_password_url'  => $this->get_forgot_password_url(),
			]
		);
	}

	/**
	 * Handle forgot password submissions.
	 */
	protected function handle_forgot_request() {
		check_admin_referer( 'oswp_forgot_action', 'oswp_forgot_nonce' );

		$value = sanitize_text_field( wp_unslash( $_POST['user_login'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( empty( $value ) ) {
			$this->forgot_errors->add( 'user_login', __( 'Please enter your email address.', 'oswp-posts' ) );
			return;
		}

		$user = get_user_by( 'email', $value );
		if ( ! $user ) {
			$user = get_user_by( 'login', $value );
		}

		if ( ! $user ) {
			// Do not reveal whether the email exists; still redirect success.
			$this->redirect_with_message( 'reset_sent' );
			return;
		}

		// Generate OTP for password reset
		$verification = $this->container->get( 'module.auth_verify' );
		if ( ! $verification ) {
			$this->forgot_errors->add( 'system_error', __( 'Password reset system unavailable.', 'oswp-posts' ) );
			return;
		}

		$token = $verification->generate_token( $user->ID );

		// Send OTP email
		$email_service = $this->container->get( 'module.emails' );
		if ( $email_service ) {
			$email_service->send(
				'password_reset_otp',
				$user->ID,
				[
					'reset_code' => $token,
				]
			);
		}

		// Redirect to OTP verification step
		$reset_url = $this->container->get( 'urls' )->get_reset_password_url( [
			'user_email' => $user->user_email,
			'step' => 'verify_otp'
		] );
		wp_safe_redirect( $reset_url );
		exit;

		// Redirect to reset password page with user email for OTP verification
		$reset_url = $this->container->get( 'urls' )->get_reset_password_url( [
			'user_email' => urlencode( $user->user_email ),
			'step' => 'verify_otp'
		] );
		wp_safe_redirect( $reset_url );
		exit;
	}

	/**
	 * Handle reset form submissions.
	 */
	protected function handle_reset_request() {
		check_admin_referer( 'oswp_reset_action', 'oswp_reset_nonce' );

		$step = sanitize_key( wp_unslash( $_GET['step'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( 'verify_otp' === $step ) {
			$this->handle_otp_verification();
		} elseif ( 'set_password' === $step ) {
			$this->handle_password_reset();
		} else {
			$this->reset_errors->add( 'invalid_step', __( 'Invalid reset step.', 'oswp-posts' ) );
		}
	}

	/**
	 * Handle OTP verification for password reset.
	 */
	protected function handle_otp_verification() {
		// Combine individual code inputs into single OTP code
		$otp_code = '';
		for ( $i = 1; $i <= 6; $i++ ) {
			$code_part = sanitize_text_field( wp_unslash( $_POST[ 'code_' . $i ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! is_numeric( $code_part ) || strlen( $code_part ) !== 1 ) {
				$this->reset_errors->add( 'otp_code', __( 'Please enter a valid 6-digit verification code.', 'oswp-posts' ) );
				return;
			}
			$otp_code .= $code_part;
		}

		$user_email = sanitize_email( wp_unslash( $_GET['user_email'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( empty( $otp_code ) || strlen( $otp_code ) !== 6 ) {
			$this->reset_errors->add( 'otp_code', __( 'Please enter the complete verification code.', 'oswp-posts' ) );
			return;
		}

		if ( empty( $user_email ) ) {
			$this->reset_errors->add( 'user_email', __( 'User email is missing.', 'oswp-posts' ) );
			return;
		}

		$user = get_user_by( 'email', $user_email );
		if ( ! $user ) {
			$this->reset_errors->add( 'user_not_found', __( 'User not found.', 'oswp-posts' ) );
			return;
		}

		$verification = $this->container->get( 'module.auth_verify' );
		if ( ! $verification ) {
			$this->reset_errors->add( 'system_error', __( 'Verification system unavailable.', 'oswp-posts' ) );
			return;
		}

		$verified = $verification->verify_token( $user->ID, $otp_code );
		if ( ! $verified ) {
			$this->reset_errors->add( 'invalid_otp', __( 'Invalid or expired verification code.', 'oswp-posts' ) );
			return;
		}

		// Generate a session token for password reset
		$session_token = wp_generate_password( 32, false );
		update_user_meta( $user->ID, 'oswp_password_reset_token', $session_token );
		update_user_meta( $user->ID, 'oswp_password_reset_expires', time() + 900 ); // 15 minutes

		// Redirect to password reset form
		$reset_url = $this->container->get( 'urls' )->get_reset_password_url( [
			'user_id' => $user->ID,
			'token' => $session_token,
			'step' => 'set_password'
		] );
		wp_safe_redirect( $reset_url );
		exit;
	}

	/**
	 * Handle actual password reset after OTP verification.
	 */
	protected function handle_password_reset() {
		$password = $_POST['password'] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$confirm  = $_POST['confirm_password'] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$user_id  = intval( $_GET['user_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$token    = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( strlen( $password ) < 8 ) {
			$this->reset_errors->add( 'password', __( 'Password must be at least 8 characters.', 'oswp-posts' ) );
		}

		if ( $password !== $confirm ) {
			$this->reset_errors->add( 'confirm', __( 'Passwords do not match.', 'oswp-posts' ) );
		}

		if ( empty( $user_id ) || empty( $token ) ) {
			$this->reset_errors->add( 'invalid_request', __( 'Invalid password reset request.', 'oswp-posts' ) );
		}

		if ( $this->reset_errors->has_errors() ) {
			return;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			$this->reset_errors->add( 'user_not_found', __( 'User not found.', 'oswp-posts' ) );
			return;
		}

		$stored_token = get_user_meta( $user->ID, 'oswp_password_reset_token', true );
		$expires = get_user_meta( $user->ID, 'oswp_password_reset_expires', true );

		if ( $token !== $stored_token || time() > $expires ) {
			$this->reset_errors->add( 'expired_token', __( 'Password reset session has expired. Please start over.', 'oswp-posts' ) );
			return;
		}

		// Reset password
		reset_password( $user, $password );

		// Clean up session tokens
		delete_user_meta( $user->ID, 'oswp_password_reset_token' );
		delete_user_meta( $user->ID, 'oswp_password_reset_expires' );

		$this->redirect_with_message( 'password_reset_success' );
	}

	/**
	 * Build reset link for email.
	 *
	 * @param string $login Login.
	 * @param string $key   Key.
	 *
	 * @return string
	 */
	protected function build_reset_link( $login, $key ) {
		$base = $this->container->get( 'urls' )->get_reset_password_url();

		return add_query_arg(
			[
				'login' => $login,
				'key'   => $key,
			],
			$base
		);
	}

	/**
	 * Get forgot password URL.
	 *
	 * @return string
	 */
	protected function get_forgot_password_url() {
		return $this->container->get( 'urls' )->get_forgot_password_url();
	}

	/**
	 * Get login URL.
	 *
	 * @return string
	 */
	protected function get_login_url() {
		return $this->container->get( 'urls' )->get_login_url();
	}

	/**
	 * Redirect with message.
	 *
	 * @param string $message Message key.
	 */
	protected function redirect_with_message( $message ) {
		$url = $this->container->get( 'urls' )->get_login_url( [ 'oswp_message' => $message ] );

		wp_safe_redirect( $url );
		exit;
	}
}
