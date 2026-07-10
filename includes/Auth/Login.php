<?php
/**
 * Login handler.
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
 * Handles frontend login form.
 */
class Login {
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
	 * Last attempted username/email.
	 *
	 * @var string
	 */
	protected $last_login = '';

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
		add_action( 'init', [ $this, 'maybe_handle_login' ] );
	}

	/**
	 * Possibly handle login submission.
	 */
	public function maybe_handle_login() {
		if ( empty( $_POST['oswp_action'] ) || 'login' !== sanitize_key( wp_unslash( $_POST['oswp_action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		check_admin_referer( 'oswp_login_action', 'oswp_login_nonce' );

		$creds = [
			'user_login'    => sanitize_text_field( wp_unslash( $_POST['user_login'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'user_password' => $_POST['user_password'] ?? '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'remember'      => ! empty( $_POST['rememberme'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
		];

		$this->last_login = $creds['user_login'];

		if ( empty( $creds['user_login'] ) || empty( $creds['user_password'] ) ) {
			$this->errors->add( 'missing', __( 'Username/email and password are required.', 'oswp-posts' ) );
			return;
		}

		$user = wp_signon( $creds, false );

		if ( is_wp_error( $user ) ) {
			$this->errors = $user;
			return;
		}

		$verification = $this->container->get( 'module.auth_verify' );

		if ( $verification && 'none' !== $this->settings->get( 'email_verification_method', 'link' ) && ! $verification->is_verified( $user->ID ) ) {
			$this->errors->add( 'not_verified', __( 'Please verify your email before logging in.', 'oswp-posts' ) );
			wp_logout();
			return;
		}

		if ( $verification && ! $verification->is_active( $user->ID ) ) {
			$this->errors->add( 'not_active', __( 'Your account is not active. Contact support.', 'oswp-posts' ) );
			wp_logout();
			return;
		}

		$redirect = $this->determine_redirect();
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render login form.
	 *
	 * @param array $flash Flash message.
	 *
	 * @return string
	 */
	public function render_form( array $flash = [] ) {
		$redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return $this->view->render(
			'auth/login-form',
			[
				'errors'             => $this->errors,
				'flash'              => $flash,
				'last_login'         => $this->last_login,
				'is_logged_in'       => is_user_logged_in(),
				'dashboard_url'      => $this->get_dashboard_url(),
				'register_url'       => $this->get_register_url(),
				'forgot_password_url' => $this->get_forgot_password_url(),
				'redirect_to'        => $redirect,
			]
		);
	}

	/**
	 * Determine redirect target after login.
	 *
	 * @return string
	 */
	protected function determine_redirect() {
		$redirect = ! empty( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! $redirect && ! empty( $_GET['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$redirect = esc_url_raw( wp_unslash( $_GET['redirect_to'] ) );
		}

		if ( $redirect ) {
			// Prevent redirect loops for restricted users trying to access wp-admin
			$user = wp_get_current_user();
			if ( in_array( 'os_author', $user->roles, true ) && false !== strpos( $redirect, 'wp-admin' ) ) {
				$redirect = $this->get_dashboard_url();
			}
			return $redirect;
		}

		return $this->get_dashboard_url();
	}

	/**
	 * Dashboard URL helper.
	 *
	 * @return string
	 */
	protected function get_dashboard_url() {
		return $this->container->get( 'urls' )->get_dashboard_url();
	}

	/**
	 * Get registration URL.
	 *
	 * @return string
	 */
	protected function get_register_url() {
		return $this->container->get( 'urls' )->get_register_url();
	}

	/**
	 * Get forgot password URL.
	 *
	 * @return string
	 */
	protected function get_forgot_password_url() {
		return $this->container->get( 'urls' )->get_forgot_password_url();
	}

}
