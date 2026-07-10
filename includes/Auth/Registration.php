<?php
/**
 * Registration handler.
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
 * Handles frontend registration workflow.
 */
class Registration {
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
	 * View loader.
	 *
	 * @var \OSWP\Posts\Core\Template_Loader
	 */
	protected $view;

	/**
	 * Collected errors.
	 *
	 * @var WP_Error
	 */
	protected $errors;

	/**
	 * Old/sanitized input for sticky form.
	 *
	 * @var array
	 */
	protected $old_input = [];

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
		add_action( 'init', [ $this, 'maybe_handle_submission' ] );
	}

	/**
	 * Process form submission.
	 */
	public function maybe_handle_submission() {
		if ( empty( $_POST['oswp_action'] ) || 'register' !== sanitize_key( wp_unslash( $_POST['oswp_action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		check_admin_referer( 'oswp_register_action', 'oswp_register_nonce' );

		$data = $this->sanitize_input( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$this->old_input = $data;

		$this->validate( $data );

		if ( $this->errors->has_errors() ) {
			return;
		}

		$user_id = $this->create_user( $data );

		if ( is_wp_error( $user_id ) ) {
			$this->errors = $user_id;
			return;
		}

		$this->handle_after_registration( $user_id, $data );

		$method = $this->settings->get( 'email_verification_method', 'otp' );

		// Handle redirects after registration and optionally auto-login when no verification is required.
		if ( 'otp' === $method ) {
			// OTP flow: redirect back to registration page with OTP step
			$base_url = $this->container->get( 'urls' )->get_register_url();

			$redirect = add_query_arg(
				[
					'step'         => 'verify-otp',
					'user_email'   => urlencode( $data['email'] ),
					'oswp_message' => 'registration_success',
				],
				$base_url
			);
		} elseif ( 'none' === $method ) {
			// No verification: log the user in automatically and redirect to dashboard
			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id );
			
			$dashboard_url = $this->container->get( 'urls' )->get_dashboard_url();
			$redirect = add_query_arg( 'oswp_message', 'registration_success', $dashboard_url );
		} else {
			// Default fallback: redirect to login page with message
			$redirect = $this->get_redirect_url( 'registration_success' );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render registration form.
	 *
	 * @param array $flash Flash message array.
	 *
	 * @return string
	 */
	public function render_form( array $flash = [] ) {
		return $this->view->render(
			'auth/register-form',
			[
				'errors'              => $this->errors,
				'old'                 => $this->old_input,
				'flash'               => $flash,
				'settings'            => $this->settings,
				'fields'              => $this->get_registration_fields_with_password(),
				'view'                => $this->view,
				'login_url'           => $this->get_login_url(),
				'forgot_password_url' => $this->get_forgot_password_url(),
			]
		);
	}

	/**
	 * Sanitize incoming data.
	 *
	 * @param array $raw Raw $_POST.
	 *
	 * @return array
	 */
	protected function sanitize_input( array $raw ) {
		$fields = $this->settings->get( 'registration_fields', [] );
		$sanitized = [];

		foreach ( $fields as $field ) {
			$id   = $field['id'];
			$type = $field['type'];
			$val  = isset( $raw[ $id ] ) ? wp_unslash( $raw[ $id ] ) : '';

			switch ( $type ) {
				case 'email':
					$sanitized[ $id ] = sanitize_email( $val );
					break;
				case 'url':
					$sanitized[ $id ] = esc_url_raw( $val );
					break;
				case 'textarea':
					$sanitized[ $id ] = sanitize_textarea_field( $val );
					break;
				case 'wysiwyg':
					$sanitized[ $id ] = wp_kses_post( $val );
					break;
				case 'media':
					$sanitized[ $id ] = absint( $val );
					break;
				case 'number':
					$sanitized[ $id ] = is_numeric( $val ) ? floatval( $val ) : 0;
					break;
				case 'date':
					$sanitized[ $id ] = sanitize_text_field( $val );
					break;
				case 'password':
					$sanitized[ $id ] = $val; // Don't sanitize password
					break;
				default:
					$sanitized[ $id ] = sanitize_text_field( $val );
					break;
			}
		}

		return $sanitized;
	}

	/**
	 * Validate data.
	 *
	 * @param array $data Data.
	 */
	protected function validate( array $data ) {
		$fields = $this->settings->get( 'registration_fields', [] );

		foreach ( $fields as $field ) {
			$id   = $field['id'];
			$name = $field['label'];
			$val  = $data[ $id ] ?? '';

			if ( ! empty( $field['required'] ) && empty( $val ) ) {
				$this->errors->add( $id, sprintf( __( '%s is required.', 'oswp-posts' ), $name ) );
			}

			// Check character limits
			$char_limit_types = [ 'text', 'email', 'tel', 'url', 'password', 'textarea', 'wysiwyg' ];
			if ( in_array( $field['type'] ?? 'text', $char_limit_types, true ) ) {
				$val_len = mb_strlen( (string) $val );
				$min_len = isset( $field['min_limit'] ) ? absint( $field['min_limit'] ) : 0;
				$max_len = ! empty( $field['max_limit'] ) ? absint( $field['max_limit'] ) : 0;

				if ( $min_len > 0 && $val_len > 0 && $val_len < $min_len ) {
					$this->errors->add( $id, sprintf( __( '%s must be at least %d characters.', 'oswp-posts' ), $name, $min_len ) );
				}
				if ( $max_len > 0 && $val_len > $max_len ) {
					$this->errors->add( $id, sprintf( __( '%s cannot exceed %d characters.', 'oswp-posts' ), $name, $max_len ) );
				}
			}

			if ( 'email' === $id ) {
				if ( ! empty( $val ) && ! is_email( $val ) ) {
					$this->errors->add( $id, __( 'Please provide a valid email address.', 'oswp-posts' ) );
				} elseif ( ! empty( $val ) && email_exists( $val ) ) {
					$this->errors->add( $id, __( 'This email is already registered.', 'oswp-posts' ) );
				}
			}

			if ( 'password' === $id ) {
				if ( ! empty( $val ) && strlen( $val ) < 8 ) {
					$this->errors->add( $id, __( 'Password must be at least 8 characters.', 'oswp-posts' ) );
				}
			}
		}
	}

	/**
	 * Create WordPress user.
	 *
	 * @param array $data Data.
	 *
	 * @return int|WP_Error
	 */
	protected function create_user( array $data ) {
		$email    = $data['email'] ?? '';
		$password = $data['password'] ?? '';
		
		if ( ! $email || ! $password ) {
			return new WP_Error( 'missing_data', __( 'Required registration data missing.', 'oswp-posts' ) );
		}

		$username = $this->generate_username_from_email( $email );
		$role     = $this->settings->get( 'default_registration_role', 'subscriber' );
		
		$user_args = [
			'user_login' => $username,
			'user_pass'  => $password,
			'user_email' => $email,
			'first_name' => $data['first_name'] ?? '',
			'last_name'  => $data['last_name'] ?? '',
			'role'       => $role,
		];

		$user_id = wp_insert_user( $user_args );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// Apply default user status settings
		update_user_meta( $user_id, 'oswp_verified', (int) $this->settings->get( 'default_user_verified', false ) );
		update_user_meta( $user_id, 'oswp_account_active', (int) $this->settings->get( 'default_user_account_active', true ) );
		update_user_meta( $user_id, 'oswp_post_approved', (int) $this->settings->get( 'default_user_approved_to_post', false ) );

		if ( $this->settings->get( 'default_user_verified', false ) ) {
			update_user_meta( $user_id, 'oswp_verified_at', current_time( 'mysql' ) );
		}

		// Save custom fields
		$fields = $this->settings->get( 'registration_fields', [] );
		$core_fields = [ 'first_name', 'last_name', 'email', 'password', 'confirm_password' ];

		foreach ( $fields as $field ) {
			$id = $field['id'];
			if ( in_array( $id, $core_fields, true ) ) {
				continue;
			}

			if ( isset( $data[ $id ] ) ) {
				update_user_meta( $user_id, 'oswp_' . $id, $data[ $id ] );
			}
		}

		return $user_id;
	}

	/**
	 * Post-registration tasks.
	 *
	 * @param int   $user_id User ID.
	 * @param array $data    Submission data.
	 */
	protected function handle_after_registration( $user_id, array $data ) {
		/** @var Email_Service $email */
		$email        = $this->container->get( 'module.emails' );
		$verification = $this->container->get( 'module.auth_verify' );

		if ( ! $verification ) {
			return;
		}

		$method = $this->settings->get( 'email_verification_method', 'otp' );
		$token  = '';
		$link   = '';

		if ( 'none' !== $method ) {
			$token = $verification->generate_token( $user_id );

			// Reset OTP resend tracking for new verification
			delete_user_meta( $user_id, 'oswp_otp_resend_count' );
			delete_user_meta( $user_id, 'oswp_otp_last_resend' );

			if ( 'link' === $method ) {
				$link = $this->build_verification_link( $user_id, $token );
			}
		} else {
			$verification->mark_verified( $user_id );
		}

		if ( $email ) {
			$email->send( 'user_registration', $user_id, [] );
		}

		if ( $token && $email ) {
			if ( 'otp' === $method ) {
				$email->send(
					'verification_otp',
					$user_id,
					[
						'verification_code' => $token,
					]
				);
			} else {
				$email->send(
					'verification',
					$user_id,
					[
						'verification_link' => $link,
					]
				);
			}
		}

		$admin_email = $this->settings->get( 'notify_admin_email' );
		if ( $admin_email && $email ) {
			$email->send(
				'admin_notification',
				$admin_email,
				[
					'user_email' => $data['email'],
					'first_name' => $data['first_name'],
					'last_name'  => $data['last_name'],
				]
			);
		}
	}

	/**
	 * Create unique username from email.
	 *
	 * @param string $email Email.
	 *
	 * @return string
	 */
	protected function generate_username_from_email( $email ) {
		$base  = sanitize_user( current( explode( '@', $email ) ) );
		$count = 1;
		$username = $base;

		while ( username_exists( $username ) ) {
			$count++;
			$username = $base . $count;
		}

		return $username;
	}

	/**
	 * Build verification link.
	 *
	 * @param int    $user_id User ID.
	 * @param string $token   Token.
	 *
	 * @return string
	 */
	protected function build_verification_link( $user_id, $token ) {
		return $this->container->get( 'urls' )->get_login_url( [
			'oswp_verify' => $user_id,
			'oswp_token'  => $token,
		] );
	}

	/**
	 * Build redirect to verification page.
	 *
	 * @param string $message Message slug.
	 *
	 * @return string
	 */
	protected function get_verification_redirect_url( $message ) {
		return $this->container->get( 'urls' )->get_verification_url( [ 'oswp_message' => $message ] );
	}

	/**
	 * Attempt to find a page which contains a given shortcode and return its permalink.
	 *
	 * @param string $shortcode_tag Shortcode tag to search for (without brackets).
	 * @return string|null Permalink if found, null otherwise.
	 */
	/**
	 * Build redirect after registration success (default to login page).
	 *
	 * @param string $message Message slug.
	 *
	 * @return string
	 */
	protected function get_redirect_url( $message ) {
		$target = $this->container->get( 'urls' )->get_login_url();

		return add_query_arg( 'oswp_message', $message, $target );
	}

	/**
	 * Render registration form fields.
	 *
	 * @param array $fields Fields to render.
	 * @param array $old    Old form data.
	 */
	protected function render_registration_fields( $fields, $old = [] ) {
		foreach ( $fields as $field ) {
			$field_id    = $field['id'];
			$field_type  = $field['type'] ?? 'text';
			$label       = $field['label'] ?? '';
			$required    = ! empty( $field['required'] ) ? 'required' : '';
			$width       = $field['width'] ?? '100';
			$value       = isset( $old[ $field_id ] ) ? $old[ $field_id ] : '';
			
			// Map width to class
			$width_class = "oswp-form__group--" . $width;
			?>

			<?php if ( 'wysiwyg' === $field_type ) : ?>
				<div class="oswp-form__group <?php echo esc_attr( $width_class ); ?>">
					<span class="oswp-form__label"><?php echo esc_html( $label ); ?></span>
					<?php 
					wp_editor( $value, $field_id, [
						'textarea_name' => $field_id,
						'media_buttons' => false,
						'textarea_rows' => 10,
						'editor_css'    => '<style>.wp-editor-wrap{border:1px solid #e2e4e7;}</style>',
						'quicktags'    => false,
					] ); 
					?>
				</div>
			<?php elseif ( 'media' === $field_type ) : ?>
				<div class="oswp-form__group <?php echo esc_attr( $width_class ); ?>">
					<span class="oswp-form__label"><?php echo esc_html( $label ); ?></span>
					<div class="oswp-media-upload">
						<div class="oswp-media-upload__preview" id="preview-<?php echo esc_attr( $field_id ); ?>">
							<?php if ($value): ?>
								<img src="<?php echo esc_url(wp_get_attachment_url($value)); ?>" />
							<?php endif; ?>
						</div>
						<input type="hidden" name="<?php echo esc_attr( $field_id ); ?>" id="<?php echo esc_attr( $field_id ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php echo $required; ?> />
						<button type="button" class="button button-secondary oswp-media-upload__btn" data-input="#<?php echo esc_attr( $field_id ); ?>" data-preview="#preview-<?php echo esc_attr( $field_id ); ?>">
							<?php esc_html_e( 'Choose Media', 'oswp-posts' ); ?>
						</button>
					</div>
				</div>
			<?php else : ?>
				<label class="oswp-form__group <?php echo esc_attr( $width_class ); ?>">
					<span class="oswp-form__label"><?php echo esc_html( $label ); ?></span>
					
					<?php if ( 'textarea' === $field_type ) : ?>
						<?php $char_limit = ! empty( $field['char_limit'] ) ? absint( $field['char_limit'] ) : 5000; ?>
						<textarea name="<?php echo esc_attr( $field_id ); ?>" class="oswp-form__input" maxlength="<?php echo esc_attr( $char_limit ); ?>" <?php echo $required; ?>><?php echo esc_textarea( $value ); ?></textarea>
					<?php elseif ( 'select' === $field_type ) : ?>
						<select name="<?php echo esc_attr( $field_id ); ?>" class="oswp-form__select" <?php echo $required; ?>>
							<option value=""><?php echo sprintf( esc_html__( 'Select %s', 'oswp-posts' ), esc_html( $label ) ); ?></option>
							<?php foreach ($field['options'] ?? [] as $opt_val => $opt_label): ?>
								<option value="<?php echo esc_attr($opt_val); ?>" <?php selected($value, $opt_val); ?>><?php echo esc_html($opt_label); ?></option>
							<?php endforeach; ?>
						</select>
					<?php else : ?>
						<?php 
							$default_limits = [
								'text'     => 255,
								'email'    => 254,
								'tel'      => 20,
								'url'      => 2048,
								'password' => 100,
							];
							$char_limit = ! empty( $field['char_limit'] ) ? absint( $field['char_limit'] ) : ( $default_limits[ $field_type ] ?? 0 );
							$char_limit_attr = $char_limit > 0 ? 'maxlength="' . esc_attr( $char_limit ) . '"' : '';
						?>
						<input type="<?php echo esc_attr( $field_type ); ?>" name="<?php echo esc_attr( $field_id ); ?>" class="oswp-form__input" value="<?php echo esc_attr( $value ); ?>" <?php echo $required; ?> <?php echo $char_limit_attr; ?> <?php echo 'password' === $field_id ? 'minlength="8"' : ''; ?> />
					<?php endif; ?>

					<?php if ( 'password' === $field_id ) : ?>
						<div id="password-strength-meter" class="oswp-password-strength"></div>
					<?php endif; ?>
				</label>
			<?php endif; ?>
			<?php
		}
	}

/**
 * Get registration fields, ensuring password/confirm_password are present.
 *
 * @return array
 */
protected function get_registration_fields_with_password() {
$fields = $this->settings->get( 'registration_fields', [] );
$has_password = false;
$has_confirm = false;
$password_index = -1;

foreach ( $fields as $index => $field ) {
if ( 'password' === $field['id'] ) {
$has_password = true;
$password_index = $index;
}
if ( 'confirm_password' === $field['id'] ) {
$has_confirm = true;
}
}

// Add password field if missing
if ( ! $has_password ) {
$fields[] = [
'id'       => 'password',
'type'     => 'password',
'label'    => __( 'Password', 'oswp-posts' ),
'required' => true,
'width'    => '100',
];
$password_index = count( $fields ) - 1;
}

// Add confirm password field after password if missing
if ( ! $has_confirm && $password_index >= 0 ) {
$new_field = [
'id'       => 'confirm_password',
'type'     => 'password',
'label'    => __( 'Confirm Password', 'oswp-posts' ),
'required' => true,
'width'    => '100',
];
array_splice( $fields, $password_index + 1, 0, [ $new_field ] );
}

return $fields;
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
	 * Get forgot password URL.
	 *
	 * @return string
	 */
	protected function get_forgot_password_url() {
		return $this->container->get( 'urls' )->get_forgot_password_url();
	}
}
