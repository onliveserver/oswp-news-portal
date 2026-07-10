<?php
/**
 * Forgot password form template.
 *
 * @var WP_Error $errors
 * @var array    $flash
 * @var string   $login_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$flash    = isset( $flash ) ? $flash : [];
$errors   = isset( $errors ) ? $errors : new WP_Error();
$login_url = isset( $login_url ) ? $login_url : wp_login_url();
?>
<div class="oswp-form oswp-form--forgot-password">
	<!-- Flash and Error Messages at the Top -->
	<?php include __DIR__ . '/../partials/notices.php'; ?>

	<div class="oswp-form__header" style="margin-bottom: var(--spacing-lg); text-align: center;">
		<h2 style="margin: 0 0 var(--spacing-md) 0; font-size: var(--font-size-2xl); font-weight: var(--font-weight-bold); color: var(--gray-900);"><?php esc_html_e( 'Reset Your Password', 'oswp-posts' ); ?></h2>
		<p style="margin: 0; color: var(--gray-600);"><?php esc_html_e( 'Enter your email address and we\'ll send you a link to reset your password.', 'oswp-posts' ); ?></p>
	</div>

	<form method="post" action="" class="oswp-form__body">
		<?php wp_nonce_field( 'oswp_forgot_action', 'oswp_forgot_nonce' ); ?>
		<input type="hidden" name="oswp_action" value="forgot_password" />

		<div class="oswp-form__group">
			<label for="user_login" class="oswp-form__label"><?php esc_html_e( 'Email Address', 'oswp-posts' ); ?></label>
			<input type="email" id="user_login" name="user_login" class="oswp-form__input" required />
		</div>

		<button type="submit" class="button button-primary oswp-form__submit"><?php esc_html_e( 'Send Reset Link', 'oswp-posts' ); ?></button>
	</form>

	<div class="oswp-form__footer">
		<p style="text-align: center; margin-bottom: 0;">
			<?php esc_html_e( 'Remember your password?', 'oswp-posts' ); ?>
			<a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Back to Login', 'oswp-posts' ); ?></a>
		</p>
	</div>
</div>
