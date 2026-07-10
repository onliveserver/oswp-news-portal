<?php
/**
 * Reset password form template.
 *
 * @var WP_Error $errors
 * @var array    $flash
 * @var string   $step
 * @var string   $user_email
 * @var int      $user_id
 * @var string   $token
 * @var string   $login_url
 * @var string   $forgot_password_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$flash               = isset( $flash ) ? $flash : [];
$errors              = isset( $errors ) ? $errors : new WP_Error();
$step                = isset( $step ) ? $step : '';
$user_email          = isset( $user_email ) ? $user_email : '';
$user_id             = isset( $user_id ) ? $user_id : 0;
$token               = isset( $token ) ? $token : '';
$login_url           = isset( $login_url ) ? $login_url : wp_login_url();
$forgot_password_url = isset( $forgot_password_url ) ? $forgot_password_url : wp_login_url();
?>
<div class="oswp-form oswp-form--reset-password">
	<!-- Flash and Error Messages at the Top -->
	<?php include __DIR__ . '/../partials/notices.php'; ?>

	<?php if ( 'verify_otp' === $step ) : ?>
		<div class="oswp-form__header" style="text-align: center; margin-bottom: var(--spacing-xl);">
			<h2 style="margin: 0 0 var(--spacing-md) 0; font-size: var(--font-size-2xl); font-weight: var(--font-weight-bold); color: var(--gray-900);"><?php esc_html_e( 'Reset Your Password', 'oswp-posts' ); ?></h2>
			<p style="margin: var(--spacing-sm) 0; color: var(--gray-600);"><?php esc_html_e( 'Enter the verification code sent to your email address.', 'oswp-posts' ); ?></p>
			<p style="margin: var(--spacing-sm) 0; font-weight: var(--font-weight-semibold); color: var(--gray-800);"><?php echo esc_html( $user_email ); ?></p>
		</div>

		<form method="post" action="<?php echo esc_url( add_query_arg( [ 'step' => 'verify_otp', 'user_email' => $user_email ], home_url( $GLOBALS['wp']->request ) ) ); ?>" class="oswp-form__body" novalidate>
			<?php wp_nonce_field( 'oswp_reset_action', 'oswp_reset_nonce' ); ?>
			<input type="hidden" name="oswp_action" value="reset_password" />

			<div class="oswp-form__group" style="margin: var(--spacing-xl) 0;">
				<label class="oswp-form__label" style="text-align: center;"><?php esc_html_e( 'Verification Code', 'oswp-posts' ); ?></label>
				<div class="oswp-verification-code" style="display: flex; justify-content: center; gap: var(--spacing-md); margin-bottom: var(--spacing-md); flex-wrap: wrap;">
					<input type="text" name="code_1" class="oswp-code-input" maxlength="1" pattern="[0-9]" style="width: 50px; height: 50px; text-align: center; font-size: 24px; border: 2px solid var(--gray-200); border-radius: var(--radius-lg); background: var(--white); transition: all var(--transition-fast);" required />
					<input type="text" name="code_2" class="oswp-code-input" maxlength="1" pattern="[0-9]" style="width: 50px; height: 50px; text-align: center; font-size: 24px; border: 2px solid var(--gray-200); border-radius: var(--radius-lg); background: var(--white); transition: all var(--transition-fast);" required />
					<input type="text" name="code_3" class="oswp-code-input" maxlength="1" pattern="[0-9]" style="width: 50px; height: 50px; text-align: center; font-size: 24px; border: 2px solid var(--gray-200); border-radius: var(--radius-lg); background: var(--white); transition: all var(--transition-fast);" required />
					<input type="text" name="code_4" class="oswp-code-input" maxlength="1" pattern="[0-9]" style="width: 50px; height: 50px; text-align: center; font-size: 24px; border: 2px solid var(--gray-200); border-radius: var(--radius-lg); background: var(--white); transition: all var(--transition-fast);" required />
					<input type="text" name="code_5" class="oswp-code-input" maxlength="1" pattern="[0-9]" style="width: 50px; height: 50px; text-align: center; font-size: 24px; border: 2px solid var(--gray-200); border-radius: var(--radius-lg); background: var(--white); transition: all var(--transition-fast);" required />
					<input type="text" name="code_6" class="oswp-code-input" maxlength="1" pattern="[0-9]" style="width: 50px; height: 50px; text-align: center; font-size: 24px; border: 2px solid var(--gray-200); border-radius: var(--radius-lg); background: var(--white); transition: all var(--transition-fast);" required />
				</div>
				<p class="oswp-form__help" style="text-align: center;"><?php esc_html_e( 'Enter the 6-digit code from your email.', 'oswp-posts' ); ?></p>
			</div>

			<button type="submit" class="button button-primary oswp-form__submit"><?php esc_html_e( 'Verify Code', 'oswp-posts' ); ?></button>
		</form>

		<div class="oswp-form__footer">
			<p style="text-align: center; margin-bottom: var(--spacing-xs);">
				<?php esc_html_e( 'Didn\'t receive the code?', 'oswp-posts' ); ?>
				<a href="#"><?php esc_html_e( 'Resend Code', 'oswp-posts' ); ?></a>
			</p>
			<p style="text-align: center; margin-bottom: 0;">
				<a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Back to Login', 'oswp-posts' ); ?></a>
			</p>
		</div>

	<?php elseif ( 'set_password' === $step && ! empty( $user_id ) && ! empty( $token ) ) : ?>
		<div class="oswp-form__body">
			<form method="post" action="">
				<?php wp_nonce_field( 'oswp_reset_action', 'oswp_reset_nonce' ); ?>
				<input type="hidden" name="oswp_action" value="reset_password" />

				<div class="oswp-form__group">
					<label for="password" class="oswp-form__label"><?php esc_html_e( 'New Password', 'oswp-posts' ); ?></label>
					<input type="password" id="password" name="password" class="oswp-form__input" minlength="8" required />
					<p class="oswp-form__help"><?php esc_html_e( 'Password must be at least 8 characters long.', 'oswp-posts' ); ?></p>
				</div>

				<div class="oswp-form__group">
					<label for="confirm_password" class="oswp-form__label"><?php esc_html_e( 'Confirm Password', 'oswp-posts' ); ?></label>
					<input type="password" id="confirm_password" name="confirm_password" class="oswp-form__input" minlength="8" required />
				</div>

				<button type="submit" class="button button-primary oswp-form__submit"><?php esc_html_e( 'Update Password', 'oswp-posts' ); ?></button>
			</form>
		</div>

		<div class="oswp-form__footer">
			<p style="text-align: center; margin-bottom: 0;">
				<a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Back to Login', 'oswp-posts' ); ?></a>
			</p>
		</div>

	<?php else : ?>
		<div class="oswp-notice oswp-notice--error">
			<p><?php esc_html_e( 'Your reset link is invalid or has expired. Please request a new one.', 'oswp-posts' ); ?></p>
		</div>
		<div class="oswp-form__footer" style="text-align: center;">
			<p>
				<a href="<?php echo esc_url( $forgot_password_url ); ?>" class="button button-primary"><?php esc_html_e( 'Request New Reset Code', 'oswp-posts' ); ?></a>
			</p>
		</div>
	<?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const codeInputs = document.querySelectorAll('.oswp-code-input');
    
    codeInputs.forEach((input, index) => {
        input.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            
            if (this.value.length === 1 && index < codeInputs.length - 1) {
                codeInputs[index + 1].focus();
            }
        });
        
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && this.value.length === 0 && index > 0) {
                codeInputs[index - 1].focus();
            }
        });
        
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const paste = (e.clipboardData || window.clipboardData).getData('text');
            const pasteNumbers = paste.replace(/[^0-9]/g, '').slice(0, 6);
            
            pasteNumbers.split('').forEach((num, i) => {
                if (codeInputs[index + i]) {
                    codeInputs[index + i].value = num;
                }
            });
            
            const nextEmpty = Array.from(codeInputs).find((input, i) => i >= index && input.value === '');
            if (nextEmpty) {
                nextEmpty.focus();
            } else if (codeInputs[codeInputs.length - 1]) {
                codeInputs[codeInputs.length - 1].focus();
            }
        });
    });
});
</script>
