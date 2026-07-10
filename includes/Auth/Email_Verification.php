<?php
/**
 * Email verification handler.
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Auth;

use OSWP\Posts\Core\Service_Container;
use OSWP\Posts\Settings\Settings_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles verification tokens, links, and admin controls.
 */
class Email_Verification {
	const META_VERIFIED       = 'oswp_verified';
	const META_VERIFIED_AT    = 'oswp_verified_at';
	const META_TOKEN          = 'oswp_verification_token';
	const META_TOKEN_CREATED  = 'oswp_verification_token_created';
	const META_ACCOUNT_ACTIVE = 'oswp_account_active';
	const META_POST_APPROVED  = 'oswp_post_approved';

	/**
	 * Container.
	 *
	 * @var Service_Container
	 */
	protected $container;

	/**
	 * Settings repository.
	 *
	 * @var Settings_Repository
	 */
	protected $settings;

	/**
	 * Constructor.
	 *
	 * @param Service_Container $container Container.
	 */
	public function __construct( Service_Container $container ) {
		$this->container = $container;
		$this->settings  = $container->get( 'settings' );
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'template_redirect', [ $this, 'maybe_handle_verification' ] );
		add_action( 'user_register', [ $this, 'handle_new_user' ] );
		add_action( 'show_user_profile', [ $this, 'render_admin_fields' ] );
		add_action( 'edit_user_profile', [ $this, 'render_admin_fields' ] );
		add_action( 'personal_options_update', [ $this, 'save_admin_fields' ] );
		add_action( 'edit_user_profile_update', [ $this, 'save_admin_fields' ] );
	}

	/**
	 * Handle verification links on template redirect.
	 */
	public function maybe_handle_verification() {
		if ( empty( $_GET['oswp_verify'] ) || empty( $_GET['oswp_token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$user_id = absint( wp_unslash( $_GET['oswp_verify'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token   = sanitize_text_field( wp_unslash( $_GET['oswp_token'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $user_id <= 0 || empty( $token ) ) {
			return;
		}

		$result   = $this->verify_token( $user_id, $token );
		$message  = $result ? 'verified' : 'verification_failed';
		$redirect = $this->get_redirect_url( $message );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Create default meta when a user registers.
	 *
	 * @param int $user_id User ID.
	 */
	public function handle_new_user( $user_id ) {
		$method              = (string) $this->settings->get( 'email_verification_method', 'otp' );
		$default_verified    = (bool) $this->settings->get( 'default_user_verified', false );
		$default_active      = (bool) $this->settings->get( 'default_user_account_active', true );
		$default_post_access = (bool) $this->settings->get( 'default_user_approved_to_post', false );

		if ( 'none' === $method ) {
			$default_verified = true;
		}

		update_user_meta( $user_id, self::META_VERIFIED, $default_verified ? 1 : 0 );
		update_user_meta( $user_id, self::META_ACCOUNT_ACTIVE, $default_active ? 1 : 0 );
		update_user_meta( $user_id, self::META_POST_APPROVED, $default_post_access ? 1 : 0 );

		if ( $default_verified ) {
			update_user_meta( $user_id, self::META_VERIFIED_AT, current_time( 'mysql' ) );
			return;
		}

		$this->generate_token( $user_id );
	}

	/**
	 * Render admin profile fields for verification.
	 *
	 * @param \WP_User $user User object.
	 */
	public function render_admin_fields( $user ) {
		if ( ! current_user_can( 'promote_users' ) ) {
			return;
		}

		$verified   = (bool) get_user_meta( $user->ID, self::META_VERIFIED, true );
		$active     = (bool) get_user_meta( $user->ID, self::META_ACCOUNT_ACTIVE, true );
		$approved   = (bool) get_user_meta( $user->ID, self::META_POST_APPROVED, true );
		$verified_at = get_user_meta( $user->ID, self::META_VERIFIED_AT, true );
		?>
		<h2><?php esc_html_e( 'OSWP User Verification', 'oswp-posts' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Verified', 'oswp-posts' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="oswp_verified" value="1" <?php checked( $verified ); ?> />
						<?php esc_html_e( 'Mark as verified', 'oswp-posts' ); ?>
					</label>
					<?php if ( $verified && $verified_at ) : ?>
						<p class="description"><?php printf( esc_html__( 'Verified on %s', 'oswp-posts' ), esc_html( $verified_at ) ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Account status', 'oswp-posts' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="oswp_account_active" value="1" <?php checked( $active ); ?> />
						<?php esc_html_e( 'Account is active', 'oswp-posts' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Approved to Post', 'oswp-posts' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="oswp_post_approved" value="1" <?php checked( $approved ); ?> />
						<?php esc_html_e( 'User can submit posts', 'oswp-posts' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Allow this user to create and submit posts from the frontend.', 'oswp-posts' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save admin profile fields.
	 *
	 * @param int $user_id User ID.
	 */
	public function save_admin_fields( $user_id ) {
		if ( ! current_user_can( 'promote_users' ) || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		check_admin_referer( 'update-user_' . $user_id );

		$verified = ! empty( $_POST['oswp_verified'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$active   = ! empty( $_POST['oswp_account_active'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$approved = ! empty( $_POST['oswp_post_approved'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $verified ) {
			$this->mark_verified( $user_id );
		} else {
			$this->mark_unverified( $user_id );
			$this->generate_token( $user_id );
		}

		update_user_meta( $user_id, self::META_ACCOUNT_ACTIVE, $active ? 1 : 0 );
		update_user_meta( $user_id, self::META_POST_APPROVED, $approved ? 1 : 0 );
	}

	/**
	 * Generate a fresh verification token or OTP.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return string Plain token/OTP for emailing.
	 */
	public function generate_token( $user_id ) {
		$method = $this->settings->get( 'email_verification_method', 'link' );

		if ( 'otp' === $method ) {
			$token = $this->generate_otp();
		} else {
			$token = wp_generate_password( 20, false, false );
		}

		$hashed    = wp_hash_password( $token );
		$timestamp = current_time( 'mysql' );

		update_user_meta( $user_id, self::META_TOKEN, $hashed );
		update_user_meta( $user_id, self::META_TOKEN_CREATED, $timestamp );

		return $token;
	}

	/**
	 * Generate a 6-digit OTP.
	 *
	 * @return string
	 */
	protected function generate_otp() {
		return str_pad( wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
	}

	/**
	 * Verify a token or OTP for the given user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $token   Token/OTP provided by user.
	 *
	 * @return bool
	 */
	public function verify_token( $user_id, $token ) {
		$hashed = get_user_meta( $user_id, self::META_TOKEN, true );

		if ( empty( $hashed ) || empty( $token ) ) {
			return false;
		}

		if ( ! wp_check_password( $token, $hashed ) ) {
			return false;
		}

		$method = $this->settings->get( 'email_verification_method', 'link' );

		// For OTP, check expiration (10 minutes)
		if ( 'otp' === $method ) {
			$created = get_user_meta( $user_id, self::META_TOKEN_CREATED, true );
			if ( empty( $created ) ) {
				return false;
			}

			$created_time = strtotime( $created );
			$current_time = current_time( 'timestamp' );
			$expiry_hours = max( 1, absint( $this->settings->get( 'otp_expiry_hours', 24 ) ) );

			if ( ( $current_time - $created_time ) > ( $expiry_hours * HOUR_IN_SECONDS ) ) {
				return false;
			}
		}

		$this->mark_verified( $user_id );
		delete_user_meta( $user_id, self::META_TOKEN );
		delete_user_meta( $user_id, self::META_TOKEN_CREATED );

		return true;
	}

	/**
	 * Mark a user verified.
	 * Also auto-approves the user to submit posts upon successful verification.
	 *
	 * @param int $user_id User ID.
	 */
	public function mark_verified( $user_id ) {
		update_user_meta( $user_id, self::META_VERIFIED, 1 );
		update_user_meta( $user_id, self::META_VERIFIED_AT, current_time( 'mysql' ) );
		// Auto-grant post submission access once the user verifies their email
		update_user_meta( $user_id, self::META_POST_APPROVED, 1 );
		update_user_meta( $user_id, self::META_ACCOUNT_ACTIVE, 1 );
	}

	/**
	 * Mark a user unverified.
	 *
	 * @param int $user_id User ID.
	 */
	public function mark_unverified( $user_id ) {
		update_user_meta( $user_id, self::META_VERIFIED, 0 );
		delete_user_meta( $user_id, self::META_VERIFIED_AT );
	}

	/**
	 * Check verification state.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool
	 */
	public function is_verified( $user_id ) {
		return (bool) get_user_meta( $user_id, self::META_VERIFIED, true );
	}

	/**
	 * Check if account is active.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool
	 */
	public function is_active( $user_id ) {
		$value = get_user_meta( $user_id, self::META_ACCOUNT_ACTIVE, true );
		return '' === $value ? true : (bool) $value;
	}

	/**
	 * Check if user is approved to post.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool
	 */
	public function is_approved_to_post( $user_id ) {
		return (bool) get_user_meta( $user_id, self::META_POST_APPROVED, true );
	}

	/**
	 * Build redirect URL with status message.
	 *
	 * @param string $message Message slug.
	 *
	 * @return string
	 */
	protected function get_redirect_url( $message ) {
		$page_id = absint( $this->settings->get( 'login_page_id' ) );
		$url     = $page_id ? get_permalink( $page_id ) : home_url( '/' );

		return add_query_arg(
			[
				'oswp_message' => $message,
			],
			$url
		);
	}
}
