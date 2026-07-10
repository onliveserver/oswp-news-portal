<?php
/**
 * Verification form template.
 *
 * @var WP_Error $errors
 * @var array    $flash
 * @var string   $login_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$flash     = isset( $flash ) ? $flash : [];
$errors    = isset( $errors ) ? $errors : new WP_Error();
$login_url = isset( $login_url ) ? $login_url : wp_login_url();
?>
<div class="oswp-form oswp-form--verification">
	<!-- Flash and Error Messages at the Top -->
	<?php include __DIR__ . '/../partials/notices.php'; ?>

	<div class="oswp-form__header" style="text-align: center; margin-bottom: var(--spacing-xl);">
		<h2 style="margin: 0 0 var(--spacing-md) 0; font-size: var(--font-size-2xl); font-weight: var(--font-weight-bold); color: var(--gray-900);"><?php esc_html_e( 'Verify Your Account', 'oswp-posts' ); ?></h2>
		<p style="margin: var(--spacing-sm) 0; color: var(--gray-600);"><?php esc_html_e( 'Enter the verification code sent to your email address.', 'oswp-posts' ); ?></p>
		<p style="margin: var(--spacing-sm) 0; font-weight: var(--font-weight-semibold); color: var(--gray-800);"><?php echo esc_html( $user_email ?? '' ); ?></p>
	</div>

	<form method="post" action="" class="oswp-form__body" novalidate>
		<?php wp_nonce_field( 'oswp_verify_action', 'oswp_verify_nonce' ); ?>
		<input type="hidden" name="oswp_action" value="verify" />
		<input type="hidden" name="user_email" value="<?php echo esc_attr( $user_email ?? '' ); ?>" />

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

		<button type="submit" class="button button-primary oswp-form__submit"><?php esc_html_e( 'Verify Account', 'oswp-posts' ); ?></button>
	</form>

	<div class="oswp-form__footer">
		<?php if ( ! empty( $user_email ) ) : ?>
			<?php
			$resend_url = add_query_arg(
				[
					'oswp_action' => 'resend_otp',
					'oswp_resend_nonce' => wp_create_nonce( 'oswp_resend_action' ),
					'user_email' => rawurlencode( $user_email ),
				],
				''
			);
			?>
			<div style="text-align: center; margin-bottom: var(--spacing-md);">
				<a href="<?php echo esc_url( $resend_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Resend Code', 'oswp-posts' ); ?></a>
			</div>
		<?php else : ?>
			<p style="color: var(--gray-600); text-align: center;">
				<?php esc_html_e( 'Unable to resend code. Please return to the registration page.', 'oswp-posts' ); ?>
			</p>
		<?php endif; ?>

		<p style="text-align: center; margin-top: var(--spacing-md);">
			<a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Back to Login', 'oswp-posts' ); ?></a>
		</p>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const codeInputs = document.querySelectorAll('.oswp-code-input');
    
    codeInputs.forEach((input, index) => {
        input.addEventListener('input', function(e) {
            // Only allow numbers
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Auto-focus next input
            if (this.value.length === 1 && index < codeInputs.length - 1) {
                codeInputs[index + 1].focus();
            }
        });
        
        input.addEventListener('keydown', function(e) {
            // Handle backspace
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
            
            // Focus the next empty input or the last input
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