<?php
/**
 * Frontend registration form template.
 *
 * @var WP_Error $errors
 * @var array    $old
 * @var array    $flash
 * @var string   $login_url
 * @var string   $forgot_password_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$old                 = isset( $old ) ? $old : [];
$flash               = isset( $flash ) ? $flash : [];
$errors              = isset( $errors ) ? $errors : new WP_Error();
$settings            = isset( $settings ) ? $settings : null;
$login_url           = isset( $login_url ) ? $login_url : wp_login_url();
$forgot_password_url = isset( $forgot_password_url ) ? $forgot_password_url : wp_login_url();

// Get dynamic fields
// Group fields by tabs
$tabs = [];
$current_tab = [
	'id' => 'default',
	'label' => __('General', 'oswp-posts'),
	'fields' => []
];

foreach ( $fields as $field ) {
	if ( 'tab' === $field['type'] ) {
		// Save current tab if it has fields
		if ( ! empty( $current_tab['fields'] ) ) {
			$tabs[] = $current_tab;
		}
		// Start new tab
		$current_tab = [
			'id' => $field['id'],
			'label' => $field['label'] ?? __('Tab', 'oswp-posts'),
			'fields' => []
		];
	} else {
		$current_tab['fields'][] = $field;
	}
}

// Add the last tab if it has fields
if ( ! empty( $current_tab['fields'] ) ) {
	$tabs[] = $current_tab;
}

// If no tabs were created, create a default one
if ( empty( $tabs ) ) {
	$tabs[] = [
		'id' => 'default',
		'label' => __('General', 'oswp-posts'),
		'fields' => $fields
	];
}

$has_multiple_tabs = count( $tabs ) > 1;
?>
<div class="oswp-form oswp-form--register">
	<?php include __DIR__ . '/../partials/notices.php'; ?>
	<form method="post" action="" class="oswp-form__body">
		<?php wp_nonce_field( 'oswp_register_action', 'oswp_register_nonce' ); ?>
		<input type="hidden" name="oswp_action" value="register" />

		<?php if ( $has_multiple_tabs ) : ?>
			<div class="oswp-form-tabs">
				<div class="oswp-form-tabs__nav">
					<?php foreach ( $tabs as $index => $tab ) : ?>
						<button type="button" class="oswp-form-tabs__tab <?php echo 0 === $index ? 'oswp-form-tabs__tab--active' : ''; ?>" data-tab="<?php echo esc_attr( $tab['id'] ); ?>">
							<?php echo esc_html( $tab['label'] ); ?>
						</button>
					<?php endforeach; ?>
				</div>
				
				<?php foreach ( $tabs as $index => $tab ) : ?>
					<div class="oswp-form-tabs__content <?php echo 0 === $index ? 'oswp-form-tabs__content--active' : ''; ?>" data-tab="<?php echo esc_attr( $tab['id'] ); ?>">
						<div class="oswp-form__grid">
							<?php $view->render_registration_fields( $tab['fields'], $old ); ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="oswp-form__grid">
				<?php $view->render_registration_fields( $tabs[0]['fields'], $old ); ?>
			</div>
		<?php endif; ?>

		<button type="submit" class="button button-primary oswp-form__submit"><?php esc_html_e( 'Create Account', 'oswp-posts' ); ?></button>
	</form>

	<div class="oswp-form__footer">
		<p style="text-align: center; margin-bottom: var(--spacing-xs);">
			<?php esc_html_e( 'Already have an account?', 'oswp-posts' ); ?>
			<a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Log in here', 'oswp-posts' ); ?></a>
		</p>
		<p style="text-align: center; margin-bottom: 0;">
			<a href="<?php echo esc_url( $forgot_password_url ); ?>"><?php esc_html_e( 'Forgot your password?', 'oswp-posts' ); ?></a>
		</p>
	</div>
</div>
