<?php
/**
 * Login form template.
 *
 * @var WP_Error $errors
 * @var array    $flash
 * @var string   $last_login
 * @var bool     $is_logged_in
 * @var string   $dashboard_url
 * @var string   $register_url
 * @var string   $forgot_password_url
 * @var string   $redirect_to
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$flash        = isset( $flash ) ? $flash : [];
$errors       = isset( $errors ) ? $errors : new WP_Error();
$last_login   = isset( $last_login ) ? $last_login : '';
$is_logged_in = isset( $is_logged_in ) ? $is_logged_in : false;
$dashboard_url = isset( $dashboard_url ) ? $dashboard_url : home_url( '/dashboard' );
$redirect_to  = isset( $redirect_to ) ? $redirect_to : '';
$register_url = isset( $register_url ) ? $register_url : home_url( '/register' );
$forgot_password_url = isset( $forgot_password_url ) ? $forgot_password_url : home_url( '/forgot-password' );
?>
<div class="oswp-form oswp-form--login">
	<!-- Flash and Error Messages at the Top -->
	<?php include __DIR__ . '/../partials/notices.php'; ?>

	<?php if ( $is_logged_in ) : ?>
		<div class="oswp-notice oswp-notice--info">
			<p><?php esc_html_e( 'You are already logged in.', 'oswp-posts' ); ?></p>
		</div>
		<p style="text-align: center; margin-top: var(--spacing-lg);">
			<a href="<?php echo esc_url( $dashboard_url ); ?>" class="button button-primary"><?php esc_html_e( 'Go to Dashboard', 'oswp-posts' ); ?></a>
		</p>
	<?php else : ?>
	<form method="post" action="" class="oswp-form__body">
			<?php wp_nonce_field( 'oswp_login_action', 'oswp_login_nonce' ); ?>
			<input type="hidden" name="oswp_action" value="login" />
			<?php if ( $redirect_to ) : ?>
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
			<?php endif; ?>

			<div class="oswp-form__group">
				<label for="user_login" class="oswp-form__label"><?php esc_html_e( 'Email or Username', 'oswp-posts' ); ?></label>
				<input type="text" id="user_login" name="user_login" class="oswp-form__input" value="<?php echo esc_attr( $last_login ); ?>" required />
			</div>

			<div class="oswp-form__group">
				<label for="user_password" class="oswp-form__label"><?php esc_html_e( 'Password', 'oswp-posts' ); ?></label>
				<input type="password" id="user_password" name="user_password" class="oswp-form__input" required />
			</div>

			<label class="oswp-form__checkbox">
				<input type="checkbox" name="rememberme" />
				<span class="oswp-form__checkbox-text"><?php esc_html_e( 'Remember me', 'oswp-posts' ); ?></span>
			</label>

			<button type="submit" class="button button-primary oswp-form__submit"><?php esc_html_e( 'Log In', 'oswp-posts' ); ?></button>
		</form>

		<div class="oswp-form__footer">
			<p>
				<a href="<?php echo esc_url( $forgot_password_url ); ?>"><?php esc_html_e( 'Forgot your password?', 'oswp-posts' ); ?></a>
			</p>
			<p>
				<?php esc_html_e( 'Don\'t have an account?', 'oswp-posts' ); ?>
				<a href="<?php echo esc_url( $register_url ); ?>"><?php esc_html_e( 'Register here', 'oswp-posts' ); ?></a>
			</p>
		</div>
	<?php endif; ?>
</div>
