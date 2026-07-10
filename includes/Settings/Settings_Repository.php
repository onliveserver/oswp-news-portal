<?php
/**
 * Settings repository.
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles retrieving and saving plugin settings.
 */
class Settings_Repository {
	/**
	 * Option key.
	 *
	 * @var string
	 */
	protected $option_key;

	/**
	 * Cached settings.
	 *
	 * @var array<string, mixed>
	 */
	protected $settings;

	/**
	 * Constructor.
	 *
	 * @param string $option_key Option key.
	 */
	public function __construct( $option_key ) {
		$this->option_key = $option_key;
		$this->settings   = $this->load();
	}

	/**
	 * Load settings from DB.
	 *
	 * @return array
	 */
	protected function load() {
		$defaults = $this->defaults();
		$stored   = get_option( $this->option_key, [] );

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		$settings = wp_parse_args( $stored, $defaults );

		// Deep-merge field arrays: ensure any default field whose id is absent
		// from the stored list is appended and missing properties are backfilled
		// (handles new field properties added after first save).
		foreach ( [ 'post_fields', 'registration_fields' ] as $field_key ) {
			if ( ! empty( $defaults[ $field_key ] ) && isset( $stored[ $field_key ] ) && is_array( $stored[ $field_key ] ) ) {
				$default_map = [];
				foreach ( $defaults[ $field_key ] as $default_field ) {
					if ( ! empty( $default_field['id'] ) ) {
						$default_map[ $default_field['id'] ] = $default_field;
					}
				}

				$merged = [];
				foreach ( $settings[ $field_key ] as $stored_field ) {
					$field_id = $stored_field['id'] ?? '';
					if ( $field_id && isset( $default_map[ $field_id ] ) ) {
						$merged[] = wp_parse_args( $stored_field, $default_map[ $field_id ] );
						unset( $default_map[ $field_id ] );
					} else {
						$merged[] = $stored_field;
					}
				}

				foreach ( $default_map as $default_field ) {
					$merged[] = $default_field;
				}

				$settings[ $field_key ] = array_values( $merged );
			}
		}

		return $settings;
	}

	/**
	 * Get defaults.
	 *
	 * @return array
	 */
	public function defaults() {
		$admin_email = get_option( 'admin_email', '' );

		return [
			// Email verification method: 'none', 'link', or 'otp'
			'email_verification_method' => 'otp',
			'enable_email_verification' => true,
			'otp_max_resends'          => 3,
			'otp_resend_cooldown'      => 2,
			'otp_expiry_hours'         => 24,
			'default_registration_role' => 'subscriber',
			'dashboard_page_id'         => 0,
			'login_page_id'             => 0,
			'verification_page_id'      => 0,
			'registration_page_id'      => 0,
			'forgot_password_page_id'   => 0,
			'reset_password_page_id'    => 0,
			'logout_page_id'            => 0,
			'verification_required'     => true,
			'menu_visibility_enabled'   => true,
			'menu_visibility_default'   => 'logged_out',
			'post_creation_roles'       => [ 'os_author' ],
			'post_monthly_limit'        => 5,
			'post_auto_approve'         => true,
			'post_limit_period'         => 'monthly',
			'post_limit_message'        => __( 'You have reached your post limit for this period.', 'oswp-posts' ),
			'max_tags_per_post'         => 5,
			'auto_focus_keyword_from_first_tag' => true,
			'allowed_mime_types'        => [ 'image/jpeg', 'image/png', 'image/gif' ],
			'notify_admin_email'        => $admin_email,
			'email_templates'           => [
				'user_registration' => [
					'enabled' => true,
					'subject' => __( 'Welcome to {site_name}!', 'oswp-posts' ),
					'body'    => __( "Hi {first_name},\n\nThank you for registering at {site_name}. Your account has been created successfully.\n\nYou can now log in to your dashboard and start submitting articles.\n\nIf you did not create this account, please ignore this email.\n\nBest regards,\nThe {site_name} Team", 'oswp-posts' ),
				],
				'admin_notification' => [
					'subject' => __( '[{site_name}] New user registration: {email}', 'oswp-posts' ),
					'body'    => __( "Hello Admin,\n\nA new user has registered on your site:\n\nName: {first_name} {last_name}\nEmail: {email}\nDate: {date}\n\nYou can review and manage this user from the WordPress admin panel.\n\nBest regards,\n{site_name}", 'oswp-posts' ),
				],
				'verification' => [
					'subject' => __( 'Verify your email – {site_name}', 'oswp-posts' ),
					'body'    => __( "Hi {first_name},\n\nPlease click the link below to verify your email address:\n\n{verification_link}\n\nThis link will expire in 24 hours. If you did not create an account, please ignore this email.\n\nBest regards,\nThe {site_name} Team", 'oswp-posts' ),
				],
				'verification_otp' => [
					'subject' => __( 'Your verification code – {site_name}', 'oswp-posts' ),
					'body'    => __( "Hi {first_name},\n\nYour account verification code is:\n\n{verification_code}\n\nEnter this code on the verification page to activate your account. This code will expire in 10 minutes.\n\nIf you did not request this, please ignore this email.\n\nBest regards,\nThe {site_name} Team", 'oswp-posts' ),
				],
				'password_reset' => [
					'subject' => __( 'Password reset request – {site_name}', 'oswp-posts' ),
					'body'    => __( "Hi {first_name},\n\nWe received a request to reset your password for your {site_name} account.\n\nClick the link below to set a new password:\n\n{reset_link}\n\nThis link will expire in 1 hour. If you did not request a password reset, you can safely ignore this email.\n\nBest regards,\nThe {site_name} Team", 'oswp-posts' ),
				],
				'password_reset_otp' => [
					'enabled' => true,
					'subject' => __( 'Your password reset code – {site_name}', 'oswp-posts' ),
					'body'    => __( "Hi {first_name},\n\nYour password reset code is:\n\n{reset_code}\n\nEnter this code on the reset password page to set a new password. This code will expire in 10 minutes.\n\nIf you did not request this, please ignore this email.\n\nBest regards,\nThe {site_name} Team", 'oswp-posts' ),
				],
			],
			'menu_visibility_rules'     => [],
			'post_status_default'       => 'pending',
			'auto_create_pages'         => true,
			'dashboard_sections'        => [ 'overview', 'profile', 'posts', 'new_post', 'password' ],
			'default_user_verified'     => false,
			'default_user_account_active' => true,
			'default_user_approved_to_post' => false,
			'registration_fields'       => [
				[ 'id' => 'account_tab', 'label' => __( 'Account Information', 'oswp-posts' ), 'type' => 'tab', 'required' => false, 'is_builtin' => false, 'width' => '100' ],
				[ 'id' => 'first_name', 'label' => __( 'First Name', 'oswp-posts' ), 'type' => 'text', 'required' => true, 'is_builtin' => true, 'width' => '50' ],
				[ 'id' => 'last_name', 'label' => __( 'Last Name', 'oswp-posts' ), 'type' => 'text', 'required' => true, 'is_builtin' => true, 'width' => '50' ],
				[ 'id' => 'email', 'label' => __( 'Email', 'oswp-posts' ), 'type' => 'email', 'required' => true, 'is_builtin' => true, 'width' => '100' ],
				[ 'id' => 'password', 'label' => __( 'Password', 'oswp-posts' ), 'type' => 'password', 'required' => true, 'is_builtin' => true, 'width' => '100' ],
				[ 'id' => 'additional_tab', 'label' => __( 'Additional Information', 'oswp-posts' ), 'type' => 'tab', 'required' => false, 'is_builtin' => false, 'width' => '100' ],
				[ 'id' => 'phone', 'label' => __( 'Phone', 'oswp-posts' ), 'type' => 'tel', 'required' => false, 'is_builtin' => false, 'meta_key' => 'oswp_phone', 'width' => '50' ],
				[ 'id' => 'country', 'label' => __( 'Country', 'oswp-posts' ), 'type' => 'text', 'required' => false, 'is_builtin' => false, 'meta_key' => 'oswp_country', 'width' => '50' ],
				[ 'id' => 'organization', 'label' => __( 'Organization', 'oswp-posts' ), 'type' => 'text', 'required' => false, 'is_builtin' => false, 'meta_key' => 'oswp_organization', 'width' => '100' ],
				[ 'id' => 'website', 'label' => __( 'Website', 'oswp-posts' ), 'type' => 'url', 'required' => false, 'is_builtin' => false, 'meta_key' => 'oswp_website', 'width' => '100' ],
			],
			'post_fields'               => [
				[ 'id' => 'post_title', 'label' => __( 'Post Title', 'oswp-posts' ), 'type' => 'text', 'required' => true, 'is_builtin' => true, 'width' => '100', 'min_limit' => 30, 'max_limit' => 60, 'description' => __( 'Between 30-60 characters recommended', 'oswp-posts' ) ],
				[ 'id' => 'post_category', 'label' => __( 'Category', 'oswp-posts' ), 'type' => 'category', 'required' => true, 'is_builtin' => true, 'width' => '50' ],
				[ 'id' => 'post_tags', 'label' => __( 'Tags', 'oswp-posts' ), 'type' => 'text', 'required' => false, 'is_builtin' => true, 'width' => '50', 'max_limit' => 100, 'description' => __( 'Maximum 5 tags (comma-separated). First tag will be used as focus keyword.', 'oswp-posts' ) ],
				[ 'id' => 'post_thumbnail', 'label' => __( 'Featured Image', 'oswp-posts' ), 'type' => 'media', 'required' => false, 'is_builtin' => true, 'width' => '100' ],
				[ 'id' => 'post_content', 'label' => __( 'Post Content', 'oswp-posts' ), 'type' => 'wysiwyg', 'required' => true, 'is_builtin' => true, 'width' => '100', 'min_limit' => 300, 'max_limit' => 5000, 'description' => __( 'Between 300-5000 characters', 'oswp-posts' ) ],
				[ 'id' => 'post_break_1', 'label' => __( 'SEO Settings', 'oswp-posts' ), 'type' => 'tab', 'required' => false, 'is_builtin' => false, 'width' => '100' ],
				[ 'id' => '_yoast_wpseo_metadesc', 'label' => __( 'Meta Description', 'oswp-posts' ), 'type' => 'textarea', 'required' => false, 'is_builtin' => false, 'meta_key' => '_yoast_wpseo_metadesc', 'width' => '100', 'min_limit' => 150, 'max_limit' => 160, 'description' => __( 'Between 150-160 characters', 'oswp-posts' ) ],
			],
			'post_form_description'     => __( 'Please follow the guidelines below when submitting your article. Content must be between 800–2000 words, original, and may include at most one hyperlink (not in the first 5 paragraphs). A featured image is recommended for better visibility.', 'oswp-posts' ),
			'post_content_min_words'    => 800,
			'post_content_max_words'    => 2000,
		];
	}

	/**
	 * Get all settings.
	 *
	 * @return array
	 */
	public function all() {
		return $this->settings;
	}

	/**
	 * Get single setting.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 *
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	/**
	 * Update settings array.
	 *
	 * @param array $data Data.
	 */
	public function update( array $data ) {
		$this->settings = wp_parse_args( $data, $this->defaults() );
		update_option( $this->option_key, $this->settings );
	}

	/**
	 * Set individual setting.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 */
	public function set( $key, $value ) {
		$this->settings[ $key ] = $value;
		update_option( $this->option_key, $this->settings );
	}

	/**
	 * Reload settings from storage.
	 */
	public function refresh() {
		$this->settings = $this->load();
	}
}
