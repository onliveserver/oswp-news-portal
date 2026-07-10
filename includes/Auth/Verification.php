<?php
/**
 * Verification handler.
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Auth;

use OSWP\Posts\Core\Service_Container;
use OSWP\Posts\Settings\Settings_Repository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles frontend verification form.
 */
class Verification {
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
	 * Template loader.
	 *
	 * @var \OSWP\Posts\Core\Template_Loader
	 */
	protected $view;

	/**
	 * Errors.
	 *
	 * @var WP_Error
	 */
	protected $errors;

	/**
	 * Constructor.
	 *
	 * @param Service_Container $container Container.
	 */
	public function __construct( Service_Container $container ) {
		$this->container = $container;
		$this->settings  = $container->get( 'settings' );
		$this->view      = $container->get( 'view' );
		$this->errors    = new WP_Error();
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', [ $this, 'maybe_handle_verification' ] );
		add_action( 'init', [ $this, 'maybe_handle_resend' ] );
	}

	/**
	 * Possibly handle verification submission.
	 */
	public function maybe_handle_verification() {
		if ( empty( $_POST['oswp_action'] ) || 'verify' !== sanitize_key( wp_unslash( $_POST['oswp_action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		check_admin_referer( 'oswp_verify_action', 'oswp_verify_nonce' );

		// Handle both old single input and new 6-box input
		$code = '';
		if ( isset( $_POST['verification_code'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$code = sanitize_text_field( wp_unslash( $_POST['verification_code'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} else {
			// Combine 6 separate code inputs
			for ( $i = 1; $i <= 6; $i++ ) {
				$input_value = sanitize_text_field( wp_unslash( $_POST[ 'code_' . $i ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
				if ( ! empty( $input_value ) && is_numeric( $input_value ) ) {
					$code .= $input_value;
				}
			}
		}

		$email = sanitize_email( wp_unslash( $_POST['user_email'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( empty( $code ) || empty( $email ) ) {
			$this->errors->add( 'missing_data', __( 'Verification code and email are required.', 'oswp-posts' ) );
			return;
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			$this->errors->add( 'invalid_email', __( 'No account found with this email address.', 'oswp-posts' ) );
			return;
		}

		$verification = $this->container->get( 'module.auth_verify' );
		if ( ! $verification ) {
			$this->errors->add( 'system_error', __( 'Verification system unavailable.', 'oswp-posts' ) );
			return;
		}

		$result = $verification->verify_token( $user->ID, $code );

		if ( $result ) {
			// Auto-login user after verification
			wp_set_current_user( $user->ID );
			wp_set_auth_cookie( $user->ID );

			// Reset OTP resend tracking after successful verification
			delete_user_meta( $user->ID, 'oswp_otp_resend_count' );
			delete_user_meta( $user->ID, 'oswp_otp_last_resend' );

			$redirect = $this->get_veried_redirect_url( 'verification_success' );
			wp_safe_redirect( $redirect );
			exit;
		} else {
			$this->errors->add( 'verification_failed', __( 'Invalid or expired verification code.', 'oswp-posts' ) );
		}
	}

	/**
	 * Handle OTP resend requests.
	 */
	public function maybe_handle_resend() {
		// Check for resend action (now only GET since we use links)
		if ( empty( $_GET['oswp_action'] ) || 'resend_otp' !== sanitize_key( wp_unslash( $_GET['oswp_action'] ) ) ) {
			return;
		}

		check_admin_referer( 'oswp_resend_action', 'oswp_resend_nonce' );

		$email = sanitize_email( wp_unslash( rawurldecode( $_GET['user_email'] ?? '' ) ) );

		if ( empty( $email ) ) {
			$this->errors->add( 'missing_email', __( 'Email address is required.', 'oswp-posts' ) );
			return;
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			$this->errors->add( 'invalid_email', __( 'No account found with this email address.', 'oswp-posts' ) );
			return;
		}

		// Check resend limits
		if ( ! $this->can_resend_otp( $user->ID ) ) {
			$this->errors->add( 'resend_limit', __( 'Too many resend requests. Please wait before trying again.', 'oswp-posts' ) );
			return;
		}

		// Generate new OTP
		$verification = $this->container->get( 'module.auth_verify' );
		if ( ! $verification ) {
			$this->errors->add( 'system_error', __( 'Verification system unavailable.', 'oswp-posts' ) );
			return;
		}

		$token = $verification->generate_token( $user->ID );

		// Send new OTP email
		$email_service = $this->container->get( 'module.emails' );
		if ( $email_service ) {
			$email_service->send(
				'verification_otp',
				$user->ID,
				[
					'verification_code' => $token,
				]
			);
		}

		// Update resend tracking
		$this->track_resend_attempt( $user->ID );

		// Redirect back with success message
		$current_params = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $current_params['oswp_action'], $current_params['oswp_resend_nonce'] ); // Clean up action params
		$current_params['oswp_message'] = 'resend_success'; // Update message
		
		$redirect_url = $this->container->get( 'urls' )->get_verification_url( $current_params );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Check if user can resend OTP based on limits.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	protected function can_resend_otp( $user_id ) {
		$max_resends = absint( $this->settings->get( 'otp_max_resends', 3 ) );
		$cooldown_minutes = absint( $this->settings->get( 'otp_resend_cooldown', 2 ) );

		if ( $max_resends === 0 ) {
			// Unlimited resends, just check cooldown
			$last_resend = get_user_meta( $user_id, 'oswp_otp_last_resend', true );
			if ( $last_resend ) {
				$time_diff = time() - strtotime( $last_resend );
				$cooldown_seconds = $cooldown_minutes * 60;
				return $time_diff >= $cooldown_seconds;
			}
			return true;
		}

		// Check resend count
		$resend_count = absint( get_user_meta( $user_id, 'oswp_otp_resend_count', true ) );
		if ( $resend_count >= $max_resends ) {
			return false;
		}

		// Check cooldown
		$last_resend = get_user_meta( $user_id, 'oswp_otp_last_resend', true );
		if ( $last_resend ) {
			$time_diff = time() - strtotime( $last_resend );
			$cooldown_seconds = $cooldown_minutes * 60;
			return $time_diff >= $cooldown_seconds;
		}

		return true;
	}

	/**
	 * Track resend attempt for rate limiting.
	 *
	 * @param int $user_id User ID.
	 */
	protected function track_resend_attempt( $user_id ) {
		$current_count = absint( get_user_meta( $user_id, 'oswp_otp_resend_count', true ) );
		update_user_meta( $user_id, 'oswp_otp_resend_count', $current_count + 1 );
		update_user_meta( $user_id, 'oswp_otp_last_resend', current_time( 'mysql' ) );
	}

	/**
	 * Render verification form.
	 *
	 * @param array $flash Flash message array.
	 *
	 * @return string
	 */
	public function render_form( array $flash = [] ) {
		// Pre-populate email from GET if available
		$email = isset( $_GET['user_email'] ) ? sanitize_email( wp_unslash( rawurldecode( $_GET['user_email'] ) ) ) : '';

		return $this->view->render(
			'auth/verification-form',
			[
				'errors'     => $this->errors,
				'flash'      => $flash,
				'login_url'  => $this->get_login_url(),
				'user_email' => $email,
			]
		);
	}

	/**
	 * Build redirect after success to dashboard.
	 *
	 * @param string $message Message slug.
	 *
	 * @return string
	 */
	protected function get_veried_redirect_url( $message ) {
		$urls = $this->container->get( 'urls' );
		$target = $urls->get_dashboard_url();

		return add_query_arg( 'oswp_message', $message, $target );
	}

	/**
	 * Build redirect after failure.
	 *
	 * @param string $message Message slug.
	 *
	 * @return string
	 */
	protected function get_redirect_url( $message ) {
		return $this->container->get( 'urls' )->get_login_url( [ 'oswp_message' => $message ] );
	}

	/**
	 * Get login URL helper.
	 *
	 * @return string
	 */
	protected function get_login_url() {
		return $this->container->get( 'urls' )->get_login_url();
	}

}