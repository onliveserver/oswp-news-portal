<?php
/**
 * Shared notices partial.
 *
 * @var array    $flash
 * @var WP_Error $errors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! empty( $flash ) ) :
	$type = isset( $flash['type'] ) ? $flash['type'] : 'info';
	?>
	<div class="oswp-notice oswp-notice--<?php echo esc_attr( $type ); ?>">
		<p><?php echo esc_html( $flash['text'] ); ?></p>
	</div>
<?php endif; ?>

<?php if ( isset( $errors ) && $errors instanceof WP_Error && $errors->has_errors() ) : ?>
	<div class="oswp-notice oswp-notice--error">
		<ul>
			<?php foreach ( $errors->get_error_messages() as $message ) : ?>
				<li><?php echo esc_html( $message ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>
