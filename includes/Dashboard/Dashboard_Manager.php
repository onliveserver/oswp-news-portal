<?php
/**
 * Dashboard manager - handles user dashboard sections.
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Dashboard;

use OSWP\Posts\Core\Service_Container;
use OSWP\Posts\Settings\Settings_Repository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages user dashboard with multiple sections.
 */
class Dashboard_Manager {
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
	 * Current user ID.
	 *
	 * @var int
	 */
	protected $user_id;

	/**
	 * Collected errors.
	 *
	 * @var WP_Error
	 */
	protected $errors;

	/**
	 * Constructor.
	 *
	 * @param Service_Container $container Container.
	 */
	public function __construct( Service_Container $container ) {
		$this->container = $container;
		$this->settings  = $container->get( 'settings' );
		$this->view      = $container->get( 'view' );
		$this->user_id   = get_current_user_id();
		$this->errors    = new WP_Error();
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', [ $this, 'maybe_handle_profile_update' ] );
		add_action( 'init', [ $this, 'maybe_handle_password_change' ] );
		add_filter( 'show_admin_bar', [ $this, 'maybe_hide_admin_bar' ] );
	} 

	/**
	 * Set a flash message for the current user session.
	 *
	 * @param string $message_key The message key.
	 */
	protected function set_flash_message( $message_key ) {
		$user_id = $this->user_id;
		$transient_key = 'oswp_flash_' . $user_id;
		
		// Get existing messages or initialize empty array
		$messages = get_transient( $transient_key );
		if ( ! is_array( $messages ) ) {
			$messages = [];
		}
		
		// Add the new message if it doesn't already exist
		if ( ! in_array( $message_key, $messages, true ) ) {
			$messages[] = $message_key;
		}
		
		// Store for 1 hour (session-like behavior)
		set_transient( $transient_key, $messages, HOUR_IN_SECONDS );
	}

	/**
	 * Get and clear flash messages for the current user session.
	 *
	 * @return array Array of message keys.
	 */
	protected function get_flash_messages() {
		$user_id = $this->user_id;
		$transient_key = 'oswp_flash_' . $user_id;
		
		$messages = get_transient( $transient_key );
		if ( ! is_array( $messages ) ) {
			$messages = [];
		}
		
		// Clear the messages after retrieving
		delete_transient( $transient_key );
		
		return $messages;
	}

	/**
	 * Get active section.
	 *
	 * @return string
	 */
	protected function get_active_section() {
		return isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Render dashboard.
	 *
	 * @return string
	 */
	public function render_dashboard() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to access the dashboard.', 'oswp-posts' ) . '</p>';
		}

		// Check if user is verified (if verification is enabled)
		$verification = $this->container->get( 'module.auth_verify' );
		if ( $verification && $this->settings->get( 'enable_email_verification' ) ) {
			if ( ! $verification->is_verified( $this->user_id ) ) {
				return '<p style="color: #d32f2f;">' . esc_html__( 'Please verify your email address to access the dashboard.', 'oswp-posts' ) . '</p>';
			}
		}

		$active_section = $this->get_active_section();
		$user = wp_get_current_user();

		ob_start();
		?>
		<div class="oswp-dashboard">
			<div class="oswp-dashboard__container">
				<!-- Sidebar Navigation -->
				<aside class="oswp-dashboard__sidebar">
					<!-- User Info Section -->
					<div class="oswp-dashboard__user-info">
						<div class="oswp-dashboard__user-details">
							<div class="oswp-dashboard__user-avatar">
								<?php echo get_avatar( $user->ID, 64, '', '', array( 'class' => 'oswp-dashboard__user-avatar-img' ) ); ?>
								<div class="oswp-dashboard__user-status">
									<span class="oswp-dashboard__user-status-dot"></span>
								</div>
							</div>
							<div class="oswp-dashboard__user-meta">
								<h3 class="oswp-dashboard__user-name"><?php echo esc_html( $user->first_name ?: $user->display_name ); ?></h3>
								<p class="oswp-dashboard__user-email"><?php echo esc_html( $user->user_email ); ?></p>
								<div class="oswp-dashboard__user-role">
									<span class="oswp-dashboard__user-role-badge"><?php esc_html_e( 'Author', 'oswp-posts' ); ?></span>
								</div>
							</div>
						</div>
						<div class="oswp-dashboard__user-stats">
							<div class="oswp-dashboard__user-stat">
								<span class="oswp-dashboard__user-stat-number"><?php echo esc_html( count_user_posts( $this->user_id, 'post' ) ); ?></span>
								<span class="oswp-dashboard__user-stat-label"><?php esc_html_e( 'Posts', 'oswp-posts' ); ?></span>
							</div>
							<div class="oswp-dashboard__user-stat">
								<span class="oswp-dashboard__user-stat-number">
									<?php
									$current_month = gmdate( 'Y-m' );
									$args = [
										'author'         => $this->user_id,
										'posts_per_page' => -1,
										'fields'         => 'ids',
									];
									$query = new \WP_Query( $args );
									$all_posts = $query->posts;
									wp_reset_postdata();
									$count = 0;
									foreach ( $all_posts as $post_id ) {
										$post_date = gmdate( 'Y-m', strtotime( get_post( $post_id )->post_date ) );
										if ( $post_date === $current_month ) {
											$count++;
										}
									}
									echo esc_html( $count );
									?>
								</span>
								<span class="oswp-dashboard__user-stat-label"><?php esc_html_e( 'This Month', 'oswp-posts' ); ?></span>
							</div>
						</div>
					</div>

					<nav class="oswp-dashboard__menu">
						<?php foreach ( $this->get_menu_items() as $id => $item ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'section', $id, remove_query_arg( 'action' ) ) ); ?>"
							   class="oswp-dashboard__menu-item <?php echo ( $id === $active_section ) ? 'oswp-dashboard__menu-item--active' : ''; ?>">
								<span class="oswp-dashboard__menu-icon">
									<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>"></span>
								</span>
								<span class="oswp-dashboard__menu-text"><?php echo esc_html( $item['label'] ); ?></span>
								<?php if ( $id === $active_section ) : ?>
									<span class="oswp-dashboard__menu-indicator"></span>
								<?php endif; ?>
							</a>
						<?php endforeach; ?>
						<div class="oswp-dashboard__menu-divider"></div>
						<a href="<?php echo esc_url( wp_logout_url( $this->get_login_url() ) ); ?>" class="oswp-dashboard__menu-item oswp-dashboard__menu-item--logout">
							<span class="oswp-dashboard__menu-icon">
								<span class="dashicons dashicons-logout"></span>
							</span>
							<span class="oswp-dashboard__menu-text"><?php esc_html_e( 'Logout', 'oswp-posts' ); ?></span>
						</a>
					</nav>
				</aside>

				<!-- Main Content -->
				<main class="oswp-dashboard__main">
					<?php
						if ( $this->errors->has_errors() ) {
							echo '<div class="oswp-notice oswp-notice--error">';
							foreach ( $this->errors->get_error_messages() as $message ) {
								echo '<p>' . esc_html( $message ) . '</p>';
							}
							echo '</div>';
						}

						// Collect flash messages once (don't clear before section handling)
						$all_flash_messages = $this->get_flash_messages();
						if ( ! empty( $all_flash_messages ) ) {
							$message_map = [
								'post_published' => __( 'Post published successfully!', 'oswp-posts' ),
								'post_updated' => __( 'Post updated successfully!', 'oswp-posts' ),
								'post_deleted' => __( 'Post deleted successfully!', 'oswp-posts' ),
								'cannot_edit_approved' => __( 'Approved posts cannot be edited.', 'oswp-posts' ),
							];
							
							foreach ( $all_flash_messages as $message_key ) {
								if ( isset( $message_map[ $message_key ] ) ) {
									echo '<div class="oswp-notice oswp-notice--success"><p>' . esc_html( $message_map[ $message_key ] ) . '</p></div>';
								}
							}
						}

						switch ( $active_section ) {
							case 'profile':
								echo $this->render_profile_section(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								break;
							case 'password':
								// Legacy: display error via query param (?error=invalid_password)
								$legacy_error = isset( $_GET['error'] ) ? sanitize_key( wp_unslash( $_GET['error'] ) ) : '';
								if ( $legacy_error ) {
									$legacy_map = [
										'invalid_password' => __( 'Current password is incorrect.', 'oswp-posts' ),
										'weak_password'    => __( 'New password is too weak. Please choose a stronger password.', 'oswp-posts' ),
										'mismatch'         => __( 'New passwords do not match.', 'oswp-posts' ),
									];
									if ( isset( $legacy_map[ $legacy_error ] ) ) {
										echo '<div class="oswp-notice oswp-notice--error"><p>' . esc_html( $legacy_map[ $legacy_error ] ) . '</p></div>';
									}
								}
								// Display flash messages for password section
								if ( ! empty( $all_flash_messages ) ) {
									$message_map = [
										'password_changed' => __( 'Password changed successfully!', 'oswp-posts' ),
										'password_error_invalid' => __( 'Current password is incorrect.', 'oswp-posts' ),
										'password_error_weak' => __( 'New password is too weak. Please choose a stronger password.', 'oswp-posts' ),
										'password_error_mismatch' => __( 'New passwords do not match.', 'oswp-posts' ),
									];
									
									foreach ( $all_flash_messages as $message_key ) {
										$class = strpos( $message_key, '_error_' ) !== false ? 'oswp-notice--error' : 'oswp-notice--success';
										if ( isset( $message_map[ $message_key ] ) ) {
											echo '<div class="oswp-notice ' . esc_attr( $class ) . '"><p>' . esc_html( $message_map[ $message_key ] ) . '</p></div>';
										}
									}
								}
								
								echo '<section class="oswp-dashboard__section">
									<h2>' . esc_html__( 'Change Password', 'oswp-posts' ) . '</h2>
									
									<form method="post" class="oswp-form oswp-form--password">';
										wp_nonce_field( 'oswp_password_change', 'oswp_password_nonce' );
										echo '<div class="oswp-form__group">
											<label for="current_password" class="oswp-form__label">' . esc_html__( 'Current Password', 'oswp-posts' ) . '</label>
											<input type="password" id="current_password" name="current_password" class="oswp-form__input" required>
										</div>

										<div class="oswp-form__group">
											<label for="new_password" class="oswp-form__label">' . esc_html__( 'New Password', 'oswp-posts' ) . '</label>
											<input type="password" id="new_password" name="new_password" class="oswp-form__input" required>
											<p class="oswp-form__help">' . esc_html__( 'Password must be at least 8 characters long.', 'oswp-posts' ) . '</p>
										</div>

										<div class="oswp-form__group">
											<label for="confirm_password" class="oswp-form__label">' . esc_html__( 'Confirm New Password', 'oswp-posts' ) . '</label>
											<input type="password" id="confirm_password" name="confirm_password" class="oswp-form__input" required>
										</div>

										<button type="submit" class="oswp-btn oswp-btn--primary">' . esc_html__( 'Change Password', 'oswp-posts' ) . '</button>
									</form>
								</section>';
								break;
							case 'posts':
								// Check if creating new post or editing existing post
								$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
								if ( 'new-post' === $action || 'edit-post' === $action ) {
									echo $this->render_post_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								} else {
									echo $this->render_posts_section(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								}
								break;
							default:
								echo $this->render_overview_section(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
					?>
				</main>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get sidebar menu items.
	 *
	 * @return array
	 */
	protected function get_menu_items() {
		return [
			'overview' => [
				'label' => __( 'Dashboard', 'oswp-posts' ),
				'icon'  => 'dashicons-dashboard',
			],
			'posts'    => [
				'label' => __( 'My Posts', 'oswp-posts' ),
				'icon'  => 'dashicons-admin-post',
			],
			'profile'  => [
				'label' => __( 'My Profile', 'oswp-posts' ),
				'icon'  => 'dashicons-admin-users',
			],
			'password' => [
				'label' => __( 'Change Password', 'oswp-posts' ),
				'icon'  => 'dashicons-lock',
			],
		];
	}

	/**
	 * Render overview section.
	 *
	 * @return string
	 */
	protected function render_overview_section() {
		$user = wp_get_current_user();
		$user_id = $this->user_id;

		// Get user stats
		$post_count = count_user_posts( $user_id, 'post' );
		$monthly_limit = $this->settings->get( 'post_monthly_limit', 0 );
		$posts_this_month = $this->get_user_posts_this_month( $user_id );

		ob_start();
		?>
		<div class="oswp-dashboard__welcome">
			<div class="oswp-dashboard__welcome-content">
				<h1 class="oswp-dashboard__welcome-title">
					<?php printf( esc_html__( 'Welcome back, %s!', 'oswp-posts' ), esc_html( $user->first_name ?: $user->display_name ) ); ?>
				</h1>
				<p class="oswp-dashboard__welcome-subtitle"><?php esc_html_e( 'Here\'s what\'s happening with your content today.', 'oswp-posts' ); ?></p>
			</div>
			<div class="oswp-dashboard__welcome-actions">
				<a href="<?php echo esc_url( add_query_arg( [ 'section' => 'posts', 'action' => 'new-post' ], remove_query_arg( 'action' ) ) ); ?>" class="oswp-btn oswp-btn--primary oswp-btn--lg">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e( 'Create New Post', 'oswp-posts' ); ?>
				</a>
			</div>
		</div>

		<section class="oswp-dashboard__section oswp-dashboard__section--stats">
			<h2 class="oswp-dashboard__section-title">
				<span class="dashicons dashicons-chart-bar"></span>
				<?php esc_html_e( 'Your Statistics', 'oswp-posts' ); ?>
			</h2>

			<div class="oswp-stats-grid">
				<div class="oswp-stat-card oswp-stat-card--total">
					<div class="oswp-stat-card__icon">
						<span class="dashicons dashicons-admin-post"></span>
					</div>
					<div class="oswp-stat-card__content">
						<div class="oswp-stat-card__number"><?php echo esc_html( $post_count ); ?></div>
						<div class="oswp-stat-card__label"><?php esc_html_e( 'Total Posts', 'oswp-posts' ); ?></div>
					</div>
					<div class="oswp-stat-card__trend">
						<span class="dashicons dashicons-arrow-up-alt"></span>
					</div>
				</div>

				<?php if ( $monthly_limit ) : ?>
					<div class="oswp-stat-card oswp-stat-card--monthly">
						<div class="oswp-stat-card__icon">
							<span class="dashicons dashicons-calendar-alt"></span>
						</div>
						<div class="oswp-stat-card__content">
							<div class="oswp-stat-card__number"><?php echo esc_html( $posts_this_month ); ?> <span class="oswp-stat-card__divider">/</span> <?php echo esc_html( $monthly_limit ); ?></div>
							<div class="oswp-stat-card__label"><?php esc_html_e( 'This Month', 'oswp-posts' ); ?></div>
						</div>
						<div class="oswp-stat-card__progress">
							<div class="oswp-stat-card__progress-bar" style="width: <?php echo esc_attr( min( 100, ( $posts_this_month / $monthly_limit ) * 100 ) ); ?>%"></div>
						</div>
					</div>
				<?php endif; ?>

				<div class="oswp-stat-card oswp-stat-card--published">
					<div class="oswp-stat-card__icon">
						<span class="dashicons dashicons-yes-alt"></span>
					</div>
					<div class="oswp-stat-card__content">
						<div class="oswp-stat-card__number">
							<?php
							$published_posts = count_user_posts( $user_id, 'post', true );
							echo esc_html( $published_posts );
							?>
						</div>
						<div class="oswp-stat-card__label"><?php esc_html_e( 'Published', 'oswp-posts' ); ?></div>
					</div>
				</div>

				<div class="oswp-stat-card oswp-stat-card--drafts">
					<div class="oswp-stat-card__icon">
						<span class="dashicons dashicons-edit"></span>
					</div>
					<div class="oswp-stat-card__content">
						<div class="oswp-stat-card__number">
							<?php
							$draft_posts = count_user_posts( $user_id, 'post' ) - $published_posts;
							echo esc_html( $draft_posts );
							?>
						</div>
						<div class="oswp-stat-card__label"><?php esc_html_e( 'Drafts', 'oswp-posts' ); ?></div>
					</div>
				</div>
			</div>
		</section>

		<section class="oswp-dashboard__section oswp-dashboard__section--actions">
			<h2 class="oswp-dashboard__section-title">
				<span class="dashicons dashicons-admin-tools"></span>
				<?php esc_html_e( 'Quick Actions', 'oswp-posts' ); ?>
			</h2>

			<div class="oswp-actions-grid">
				<a href="<?php echo esc_url( add_query_arg( 'section', 'profile', remove_query_arg( 'action' ) ) ); ?>" class="oswp-action-card">
					<div class="oswp-action-card__icon">
						<span class="dashicons dashicons-admin-users"></span>
					</div>
					<div class="oswp-action-card__content">
						<h3 class="oswp-action-card__title"><?php esc_html_e( 'Edit Profile', 'oswp-posts' ); ?></h3>
						<p class="oswp-action-card__description"><?php esc_html_e( 'Update your personal information and preferences.', 'oswp-posts' ); ?></p>
					</div>
					<div class="oswp-action-card__arrow">
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</div>
				</a>

				<a href="<?php echo esc_url( add_query_arg( 'section', 'password', remove_query_arg( 'action' ) ) ); ?>" class="oswp-action-card">
					<div class="oswp-action-card__icon">
						<span class="dashicons dashicons-lock"></span>
					</div>
					<div class="oswp-action-card__content">
						<h3 class="oswp-action-card__title"><?php esc_html_e( 'Change Password', 'oswp-posts' ); ?></h3>
						<p class="oswp-action-card__description"><?php esc_html_e( 'Keep your account secure with a strong password.', 'oswp-posts' ); ?></p>
					</div>
					<div class="oswp-action-card__arrow">
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</div>
				</a>

				<a href="<?php echo esc_url( add_query_arg( 'section', 'posts', remove_query_arg( 'action' ) ) ); ?>" class="oswp-action-card">
					<div class="oswp-action-card__icon">
						<span class="dashicons dashicons-admin-post"></span>
					</div>
					<div class="oswp-action-card__content">
						<h3 class="oswp-action-card__title"><?php esc_html_e( 'Manage Posts', 'oswp-posts' ); ?></h3>
						<p class="oswp-action-card__description"><?php esc_html_e( 'View, edit, and organize all your published content.', 'oswp-posts' ); ?></p>
					</div>
					<div class="oswp-action-card__arrow">
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</div>
				</a>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render profile section.
	 *
	 * @return string
	 */
	protected function render_profile_section() {
		$user = wp_get_current_user();
		$user_id = $this->user_id;
		$fields = $this->settings->get( 'registration_fields', [] );

		ob_start();
		?>
		<section class="oswp-dashboard__section">
			<?php
			// Profile flash messages are handled in the main dashboard switch to avoid transient consumption before rendering
			?>
			
			<h2><?php esc_html_e( 'My Profile', 'oswp-posts' ); ?></h2>
			
			<form method="post" class="oswp-form oswp-form--profile">
				<?php wp_nonce_field( 'oswp_profile_update', 'oswp_profile_nonce' ); ?>
				
				<div class="oswp-form__grid">
					<?php foreach ( $fields as $field ) : ?>
						<?php
						$id          = $field['id'];
						$type        = $field['type'] ?? 'text';
						$width       = $field['width'] ?? '100';
						$width_class = "oswp-form__group--" . $width;
						
						if ( 'password' === $type ) {
							continue;
						}

						$val = '';
						if ( in_array( $id, [ 'first_name', 'last_name', 'email' ], true ) ) {
							$val = 'email' === $id ? $user->user_email : $user->$id;
						} else {
							$val = get_user_meta( $user_id, 'oswp_' . $id, true );
						}

						$required = ! empty( $field['required'] ) ? 'required' : '';
						?>

						<?php if ( 'wysiwyg' === $type ) : ?>
							<div class="oswp-form__group <?php echo esc_attr( $width_class ); ?>">
								<span class="oswp-form__label"><?php echo esc_html( $field['label'] ); ?></span>
								<?php 
								wp_editor( $val, $id, [
									'textarea_name' => $id,
									'media_buttons' => true,
									'textarea_rows' => 10,
									'editor_css'    => '<style>.wp-editor-wrap{border:1px solid #e2e4e7;}</style>',
								] ); 
								?>
							</div>
						<?php elseif ( 'media' === $type ) : ?>
							<div class="oswp-form__group <?php echo esc_attr( $width_class ); ?>">
								<span class="oswp-form__label"><?php echo esc_html( $field['label'] ); ?></span>
								<div class="oswp-media-upload">
									<div class="oswp-media-upload__preview" id="preview-<?php echo esc_attr( $id ); ?>">
										<?php if ($val): ?>
											<img src="<?php echo esc_url(wp_get_attachment_url($val)); ?>" />
										<?php endif; ?>
									</div>
									<input type="hidden" name="<?php echo esc_attr( $id ); ?>" id="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $val ); ?>" <?php echo $required; ?> />
									<button type="button" class="button button-secondary oswp-media-upload__btn" data-input="#<?php echo esc_attr( $id ); ?>" data-preview="#preview-<?php echo esc_attr( $id ); ?>">
										<?php esc_html_e( 'Choose Media', 'oswp-posts' ); ?>
									</button>
								</div>
							</div>
						<?php elseif ( 'select' === $type ) : ?>
							<div class="oswp-form__group <?php echo esc_attr( $width_class ); ?>">
								<label for="<?php echo esc_attr( $id ); ?>" class="oswp-form__label"><?php echo esc_html( $field['label'] ); ?></label>
								<select name="<?php echo esc_attr($id); ?>" id="<?php echo esc_attr($id); ?>" class="oswp-form__select" <?php echo $required; ?>>
									<option value=""><?php echo sprintf( esc_html__( 'Select %s', 'oswp-posts' ), esc_html( $field['label'] ) ); ?></option>
									<?php foreach ($field['options'] ?? [] as $opt_val => $opt_label): ?>
										<option value="<?php echo esc_attr($opt_val); ?>" <?php selected($val, $opt_val); ?>><?php echo esc_html($opt_label); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						<?php elseif ( 'select' === $type ) : ?>
							<div class="oswp-form__group <?php echo esc_attr( $width_class ); ?>">
								<label for="<?php echo esc_attr( $id ); ?>" class="oswp-form__label"><?php echo esc_html( $field['label'] ); ?></label>
								<select name="<?php echo esc_attr($id); ?>" id="<?php echo esc_attr($id); ?>" class="oswp-form__select" <?php echo $required; ?>>
									<option value=""><?php echo sprintf( esc_html__( 'Select %s', 'oswp-posts' ), esc_html( $field['label'] ) ); ?></option>
									<?php foreach ($field['options'] ?? [] as $opt_val => $opt_label): ?>
										<option value="<?php echo esc_attr($opt_val); ?>" <?php selected($val, $opt_val); ?>><?php echo esc_html($opt_label); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						<?php else : ?>
							<div class="oswp-form__group <?php echo esc_attr( $width_class ); ?>">
								<label for="<?php echo esc_attr( $id ); ?>" class="oswp-form__label"><?php echo esc_html( $field['label'] ); ?></label>
								<?php if ( 'textarea' === $type ) : ?>
									<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" class="oswp-form__input" <?php echo $required; ?>><?php echo esc_textarea( $val ); ?></textarea>
								<?php else : ?>
									<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" class="oswp-form__input" value="<?php echo esc_attr( $val ); ?>" <?php echo $required; ?>>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>

				<button type="submit" class="oswp-btn oswp-btn--primary"><?php esc_html_e( 'Save Changes', 'oswp-posts' ); ?></button>
			</form>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render new post form.
	 *
	 * @return string
	 */
	protected function render_post_form() {
		$post_manager = $this->container->get( 'module.posts' );
		if ( ! $post_manager ) {
			return '<p>' . esc_html__( 'Post manager is not available.', 'oswp-posts' ) . '</p>';
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'new-post'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		
		// For new posts, ignore any post_id parameter
		if ( 'new-post' === $action ) {
			$post_id = 0;
		}
		
		// Verify the post belongs to the current user
		if ( $post_id && get_post_field( 'post_author', $post_id ) != $this->user_id ) {
			return '<p>' . esc_html__( 'You do not have permission to edit this post.', 'oswp-posts' ) . '</p>';
		}

		// Prevent editing of published/approved posts
		if ( $post_id && 'publish' === get_post_status( $post_id ) ) {
			return '<p>' . esc_html__( 'This post has been approved and cannot be edited.', 'oswp-posts' ) . '</p>';
		}

		$back_url = remove_query_arg( [ 'action', 'post_id' ] );
		$title = 'edit-post' === $action ? __( 'Edit Post', 'oswp-posts' ) : __( 'Create New Post', 'oswp-posts' );

		ob_start();
		?>
		<section class="oswp-dashboard__section">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
				<h2 style="margin: 0;"><?php echo esc_html( $title ); ?></h2>
				<a href="<?php echo esc_url( $back_url ); ?>" class="oswp-btn oswp-btn--secondary oswp-btn--small">
					<?php esc_html_e( '← Back to Posts', 'oswp-posts' ); ?>
				</a>
			</div>
			<?php echo $post_manager->render_post_form( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render posts section.
	 *
	 * @return string
	 */
	protected function render_posts_section() {
		$user_id = $this->user_id;
		$monthly_limit = $this->settings->get( 'post_monthly_limit', 0 );
		$posts_this_month = $this->get_user_posts_this_month( $user_id );
		$can_post = ! $monthly_limit || $posts_this_month < $monthly_limit;

		// Check if user is approved to post
		$verification = $this->container->get( 'module.auth_verify' );
		$is_approved = $verification && $verification->is_approved_to_post( $user_id );
		$can_post = $can_post && $is_approved;

		// Get filter parameters
		$status_filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search_query = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Determine current page: prefer explicit query param, fall back to rewrite vars (paged/page)
		if ( isset( $_GET['paged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$paged = absint( wp_unslash( $_GET['paged'] ) );
		} else {
			// Use rewrite vars so URLs like /page/3/ work
			$paged = max( 1, absint( get_query_var( 'paged' ) ?: get_query_var( 'page' ) ?: 1 ) );
		}

		// Prevent WordPress canonical redirects for dashboard paged posts view (avoid /page/3/ -> redirect)
		if ( ( get_query_var( 'paged' ) || get_query_var( 'page' ) ) && isset( $_GET['section'] ) && 'posts' === ( isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '' ) ) {
			add_filter( 'redirect_canonical', '__return_false' );
		} // end if

		// Build query args
		$args = [
			'author'         => $user_id,
			'posts_per_page' => 12,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		// Add status filter
		if ( 'all' !== $status_filter ) {
			$args['post_status'] = $status_filter;
		} else {
			$args['post_status'] = [ 'publish', 'pending', 'draft' ];
		}

		// Add search
		if ( ! empty( $search_query ) ) {
			$args['s'] = $search_query;
		}

		$posts_query = new \WP_Query( $args );

		// Get status counts
		$status_counts = [
			'all' => $this->get_user_posts_count( $user_id, [ 'publish', 'pending', 'draft' ] ),
			'publish' => $this->get_user_posts_count( $user_id, 'publish' ),
			'pending' => $this->get_user_posts_count( $user_id, 'pending' ),
			'draft' => $this->get_user_posts_count( $user_id, 'draft' ),
		];

		// Get status counts
		$status_counts = [
			'all' => $this->get_user_posts_count( $user_id, [ 'publish', 'pending', 'draft' ] ),
			'publish' => $this->get_user_posts_count( $user_id, 'publish' ),
			'pending' => $this->get_user_posts_count( $user_id, 'pending' ),
			'draft' => $this->get_user_posts_count( $user_id, 'draft' ),
		];

		ob_start();
		?>
		<section class="oswp-dashboard__section oswp-dashboard__section--posts">
			<div class="oswp-posts-header">
				<div class="oswp-posts-header__title">
					<h2 class="oswp-dashboard__section-title">
						<span class="dashicons dashicons-admin-post"></span>
						<?php esc_html_e( 'My Posts', 'oswp-posts' ); ?>
					</h2>
					<span class="oswp-posts-count"><?php printf( esc_html__( '%d posts', 'oswp-posts' ), $status_counts['all'] ); ?></span>
				</div>

				<?php if ( $can_post ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'action', 'new-post', remove_query_arg( [ 'action', 'post_id' ] ) ) ); ?>" class="oswp-btn oswp-btn--primary">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e( 'New Post', 'oswp-posts' ); ?>
					</a>
				<?php endif; ?>
			</div>

			<?php if ( ! $can_post ) : ?>
				<div class="oswp-notice oswp-notice--warning">
					<p><?php esc_html_e( 'You have reached your monthly post limit or are not approved to submit posts.', 'oswp-posts' ); ?></p>
				</div>
			<?php endif; ?>

			<!-- Filters and Search -->
			<div class="oswp-posts-filters">
				<div class="oswp-posts-filter-tabs">
					<?php
					$status_labels = [
						'all' => __( 'All Posts', 'oswp-posts' ),
						'publish' => __( 'Published', 'oswp-posts' ),
						'pending' => __( 'Pending', 'oswp-posts' ),
						'draft' => __( 'Drafts', 'oswp-posts' ),
					];

					foreach ( $status_labels as $status => $label ) :
						$active_class = $status === $status_filter ? 'oswp-posts-filter-tab--active' : '';
						// Clean URL: remove pagination and other irrelevant args, keep only section
						$clean_url = remove_query_arg( [ 'paged', 'action', 'post_id', 'oswp_message' ] );
						$current_url = add_query_arg( 'status', $status, $clean_url );
						if ( ! empty( $search_query ) ) {
							$current_url = add_query_arg( 'search', $search_query, $current_url );
						}
						?>
						<a href="<?php echo esc_url( $current_url ); ?>" class="oswp-posts-filter-tab <?php echo esc_attr( $active_class ); ?>">
							<?php echo esc_html( $label ); ?>
							<span class="oswp-posts-filter-count"><?php echo esc_html( $status_counts[ $status ] ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>

				<div class="oswp-posts-search">
					<form method="get" action="<?php echo esc_url( add_query_arg( 'section', 'posts', remove_query_arg( [ 'search', 'paged', 'section' ] ) ) ); ?>" class="oswp-posts-search-form">
						<?php if ( 'all' !== $status_filter ) : ?>
							<input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>" />
						<?php endif; ?>
						<div class="oswp-posts-search-input">
							<span class="dashicons dashicons-search"></span>
							<input type="text" name="search" placeholder="<?php esc_attr_e( 'Search posts...', 'oswp-posts' ); ?>" value="<?php echo esc_attr( $search_query ); ?>" />
							<?php if ( ! empty( $search_query ) ) : ?>
								<a href="<?php echo esc_url( remove_query_arg( [ 'search', 'paged' ] ) ); ?>" class="oswp-posts-search-clear">
									<span class="dashicons dashicons-no-alt"></span>
								</a>
							<?php endif; ?>
						</div>
						<button type="submit" class="oswp-btn oswp-btn--secondary oswp-btn--sm">
							<?php esc_html_e( 'Search', 'oswp-posts' ); ?>
						</button>
					</form>
				</div>
			</div>

			<?php if ( $posts_query->have_posts() ) : ?>
				<div class="oswp-posts-grid">
					<?php while ( $posts_query->have_posts() ) : $posts_query->the_post(); ?>
						<div class="oswp-post-card">
							<div class="oswp-post-card__header">
								<?php
								$thumbnail_id = get_post_thumbnail_id();
								if ( $thumbnail_id ) :
									?>
									<div class="oswp-post-card__thumbnail">
										<img src="<?php echo esc_url( wp_get_attachment_image_url( $thumbnail_id, 'medium' ) ); ?>" alt="<?php the_title_attribute(); ?>" />
									</div>
								<?php else : ?>
									<div class="oswp-post-card__thumbnail oswp-post-card__thumbnail--placeholder">
										<span class="dashicons dashicons-format-image"></span>
									</div>
								<?php endif; ?>

								<div class="oswp-post-card__status">
									<span class="oswp-post-card__status-badge oswp-post-card__status-badge--<?php echo esc_attr( get_post_status() ); ?>">
										<?php
										$status_labels = [
											'publish' => __( 'Published', 'oswp-posts' ),
											'pending' => __( 'Pending Review', 'oswp-posts' ),
											'draft' => __( 'Draft', 'oswp-posts' ),
										];
										echo esc_html( $status_labels[ get_post_status() ] ?? ucfirst( get_post_status() ) );
										?>
									</span>
								</div>
							</div>

							<div class="oswp-post-card__content">
								<h3 class="oswp-post-card__title">
									<a href="<?php the_permalink(); ?>" target="_blank"><?php the_title(); ?></a>
								</h3>

								<div class="oswp-post-card__excerpt">
									<?php
									$excerpt = get_the_excerpt();
									if ( empty( $excerpt ) ) {
										$excerpt = wp_trim_words( get_the_content(), 20 );
									}
									echo esc_html( wp_trim_words( $excerpt, 15 ) );
									?>
								</div>

								<div class="oswp-post-card__meta">
									<span class="oswp-post-card__date">
										<span class="dashicons dashicons-calendar-alt"></span>
										<?php echo esc_html( get_the_date() ); ?>
									</span>

									<?php if ( 'publish' === get_post_status() ) : ?>
										<span class="oswp-post-card__views">
											<span class="dashicons dashicons-visibility"></span>
											<?php
											$views = get_post_meta( get_the_ID(), 'oswp_post_views', true ) ?: 0;
											echo esc_html( number_format_i18n( $views ) );
											?>
										</span>
									<?php endif; ?>
								</div>
							</div>

							<div class="oswp-post-card__actions">
								<div class="oswp-post-card__actions-primary">
									<?php if ( 'publish' === get_post_status() ) : ?>
										<a href="<?php the_permalink(); ?>" target="_blank" class="oswp-btn oswp-btn--secondary oswp-btn--sm">
											<span class="dashicons dashicons-external"></span>
											<?php esc_html_e( 'View', 'oswp-posts' ); ?>
										</a>
									<?php elseif ( 'publish' !== get_post_status() ) : ?>
										<a href="<?php echo esc_url( add_query_arg( [ 'action' => 'edit-post', 'post_id' => get_the_ID() ], remove_query_arg( [ 'action', 'post_id' ] ) ) ); ?>" class="oswp-btn oswp-btn--primary oswp-btn--sm">
											<span class="dashicons dashicons-edit"></span>
											<?php esc_html_e( 'Edit', 'oswp-posts' ); ?>
										</a>
									<?php else : ?>
										<span class="oswp-post-card__approved-notice">
											<span class="dashicons dashicons-yes-alt"></span>
											<?php esc_html_e( 'Approved', 'oswp-posts' ); ?>
										</span>
									<?php endif; ?>
								</div>

								<div class="oswp-post-card__actions-secondary">
									<a href="<?php echo esc_url( get_delete_post_link() ); ?>" class="oswp-post-card__action-link oswp-post-card__action-link--delete" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this post?', 'oswp-posts' ); ?>');">
										<span class="dashicons dashicons-trash"></span>
									</a>
								</div>
							</div>
						</div>
					<?php endwhile; ?>
				</div>

				<!-- Pagination -->
				<?php if ( $posts_query->max_num_pages > 1 ) : ?>
					<div class="oswp-posts-pagination">
						<?php
						// Build pagination links manually to avoid WordPress rewrite conflicts
						$dashboard_url = $this->get_dashboard_url();
						$base_args = [ 'section' => 'posts' ];

						if ( 'all' !== $status_filter ) {
							$base_args['status'] = $status_filter;
						}
						if ( ! empty( $search_query ) ) {
							$base_args['search'] = $search_query;
						}

						$base_url = add_query_arg( $base_args, $dashboard_url );

						// Previous link
						if ( $paged > 1 ) {
							$prev_url = add_query_arg( 'paged', $paged - 1, $base_url );
							echo '<a href="' . esc_url( $prev_url ) . '" class="oswp-pagination-link oswp-pagination-prev">';
							echo '<span class="dashicons dashicons-arrow-left-alt2"></span> ' . esc_html__( 'Previous', 'oswp-posts' );
							echo '</a>';
						}

						// Page numbers
						$start_page = max( 1, $paged - 2 );
						$end_page = min( $posts_query->max_num_pages, $paged + 2 );

						// Show first page if not in range
						if ( $start_page > 1 ) {
							$first_url = add_query_arg( 'paged', 1, $base_url );
							echo '<a href="' . esc_url( $first_url ) . '" class="oswp-pagination-link">1</a>';
							if ( $start_page > 2 ) {
								echo '<span class="oswp-pagination-dots">...</span>';
							}
						}

						// Page number links
						for ( $i = $start_page; $i <= $end_page; $i++ ) {
							$page_url = add_query_arg( 'paged', $i, $base_url );
							$active_class = ( $i === $paged ) ? ' oswp-pagination-link--active' : '';
							echo '<a href="' . esc_url( $page_url ) . '" class="oswp-pagination-link' . esc_attr( $active_class ) . '">' . esc_html( $i ) . '</a>';
						}

						// Show last page if not in range
						if ( $end_page < $posts_query->max_num_pages ) {
							if ( $end_page < $posts_query->max_num_pages - 1 ) {
								echo '<span class="oswp-pagination-dots">...</span>';
							}
							$last_url = add_query_arg( 'paged', $posts_query->max_num_pages, $base_url );
							echo '<a href="' . esc_url( $last_url ) . '" class="oswp-pagination-link">' . esc_html( $posts_query->max_num_pages ) . '</a>';
						}

						// Next link
						if ( $paged < $posts_query->max_num_pages ) {
							$next_url = add_query_arg( 'paged', $paged + 1, $base_url );
							echo '<a href="' . esc_url( $next_url ) . '" class="oswp-pagination-link oswp-pagination-next">';
							echo esc_html__( 'Next', 'oswp-posts' ) . ' <span class="dashicons dashicons-arrow-right-alt2"></span>';
							echo '</a>';
						}
						?>
					</div>
				<?php endif; ?>

				<?php wp_reset_postdata(); ?>
			<?php else : ?>
				<div class="oswp-empty-state">
					<div class="oswp-empty-state__icon">
						<span class="dashicons dashicons-admin-post"></span>
					</div>
					<h3 class="oswp-empty-state__title">
						<?php echo ! empty( $search_query ) ? esc_html__( 'No posts found', 'oswp-posts' ) : esc_html__( 'No posts yet', 'oswp-posts' ); ?>
					</h3>
					<p class="oswp-empty-state__text">
						<?php
						if ( ! empty( $search_query ) ) {
							esc_html_e( 'Try adjusting your search terms or filters.', 'oswp-posts' );
						} elseif ( $can_post ) {
							esc_html_e( 'Create your first post to get started.', 'oswp-posts' );
						} else {
							esc_html_e( 'You are not currently approved to create posts.', 'oswp-posts' );
						}
						?>
					</p>
					<?php if ( $can_post && empty( $search_query ) ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'action', 'new-post', remove_query_arg( [ 'action', 'post_id' ] ) ) ); ?>" class="oswp-btn oswp-btn--primary">
							<span class="dashicons dashicons-plus-alt"></span>
							<?php esc_html_e( 'Create Your First Post', 'oswp-posts' ); ?>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handle profile update.
	 */
	public function maybe_handle_profile_update() {
		if ( empty( $_POST['oswp_profile_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['oswp_profile_nonce'] ) ), 'oswp_profile_update' ) ) {
			return;
		}

		$user_id = $this->user_id;
		$user    = get_userdata( $user_id );
		$fields  = $this->settings->get( 'registration_fields', [] );

		$update_args = [ 'ID' => $user_id ];
		$meta_updates = [];

		foreach ( $fields as $field ) {
			$id   = $field['id'];
			$type = $field['type'];
			
			if ( 'password' === $type ) {
				continue;
			}

			$val = isset( $_POST[ $id ] ) ? wp_unslash( $_POST[ $id ] ) : '';

			if ( ! empty( $field['required'] ) && empty( $val ) ) {
				$this->errors->add( $id, sprintf( __( '%s is required.', 'oswp-posts' ), $field['label'] ) );
			}

			if ( in_array( $id, [ 'first_name', 'last_name' ], true ) ) {
				$update_args[ $id ] = sanitize_text_field( $val );
			} elseif ( 'email' === $id ) {
				$email = sanitize_email( $val );
				if ( ! is_email( $email ) ) {
					$this->errors->add( $id, __( 'Please provide a valid email address.', 'oswp-posts' ) );
				} elseif ( $email !== ( $user ? $user->user_email : '' ) && email_exists( $email ) ) {
					$this->errors->add( $id, __( 'This email is already registered.', 'oswp-posts' ) );
				} else {
					$update_args['user_email'] = $email;
				}
			} else {
				// Meta fields
				if ( 'url' === $type ) {
					$meta_updates[ 'oswp_' . $id ] = esc_url_raw( $val );
				} elseif ( 'textarea' === $type ) {
					$meta_updates[ 'oswp_' . $id ] = sanitize_textarea_field( $val );
				} elseif ( 'url' === $type ) {
					$meta_updates[ 'oswp_' . $id ] = esc_url_raw( $val );
				} elseif ( 'number' === $type ) {
					$meta_updates[ 'oswp_' . $id ] = is_numeric( $val ) ? floatval( $val ) : 0;
				} elseif ( 'date' === $type ) {
					$meta_updates[ 'oswp_' . $id ] = sanitize_text_field( $val );
				} elseif ( 'wysiwyg' === $type ) {
					$meta_updates[ 'oswp_' . $id ] = wp_kses_post( $val );
				} elseif ( 'media' === $type ) {
					$meta_updates[ 'oswp_' . $id ] = absint( $val );
				} else {
					$meta_updates[ 'oswp_' . $id ] = sanitize_text_field( $val );
				}
			}
		}

		if ( $this->errors->has_errors() ) {
			return;
		}

		wp_update_user( $update_args );

		foreach ( $meta_updates as $key => $value ) {
			update_user_meta( $user_id, $key, $value );
		}

		$this->set_flash_message( 'profile_updated' );
		wp_safe_redirect( add_query_arg( 'section', 'profile' ) );
		exit;
	}

	/**
	 * Handle password change.
	 */
	public function maybe_handle_password_change() {
		if ( empty( $_POST['oswp_password_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['oswp_password_nonce'] ) ), 'oswp_password_change' ) ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$current_password = wp_unslash( $_POST['current_password'] ?? '' );
		$new_password = wp_unslash( $_POST['new_password'] ?? '' );
		$confirm_password = wp_unslash( $_POST['confirm_password'] ?? '' );

		$user = wp_get_current_user();
		if ( ! wp_check_password( $current_password, $user->user_pass, $user_id ) ) {
			$this->set_flash_message( 'password_error_invalid' );
			wp_safe_redirect( add_query_arg( 'section', 'password' ) );
			exit;
		}

		if ( strlen( $new_password ) < 8 ) {
			$this->set_flash_message( 'password_error_weak' );
			wp_safe_redirect( add_query_arg( 'section', 'password' ) );
			exit;
		}

		if ( $new_password !== $confirm_password ) {
			$this->set_flash_message( 'password_error_mismatch' );
			wp_safe_redirect( add_query_arg( 'section', 'password' ) );
			exit;
		}

		wp_set_user_password( $user_id, $new_password );

		$this->set_flash_message( 'password_changed' );
		wp_safe_redirect( add_query_arg( 'section', 'password' ) );
		exit;
	}

	/**
	 * Get user posts count by status.
	 *
	 * @param int $user_id User ID.
	 * @param string|array $status Post status(es).
	 * @return int
	 */
	protected function get_user_posts_count( $user_id, $status ) {
		$args = [
			'author'         => $user_id,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post_status'    => $status,
		];

		$query = new \WP_Query( $args );
		return $query->found_posts;
	}

	/**
	 * Get user posts created this month.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return int
	 */
	protected function get_user_posts_this_month( $user_id ) {
		$current_month = gmdate( 'Y-m' );
		$args = [
			'author'         => $user_id,
			'posts_per_page' => -1,
			'fields'         => 'ids',
		];

		$query = new \WP_Query( $args );
		$all_posts = $query->posts;
		wp_reset_postdata();

		$count = 0;
		foreach ( $all_posts as $post_id ) {
			$post_date = gmdate( 'Y-m', strtotime( get_post( $post_id )->post_date ) );
			if ( $post_date === $current_month ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Get dashboard URL helper.
	 *
	 * @return string
	 */
	/**
	 * Conditionally hide the WP admin bar for users with the 'os_author' role.
	 *
	 * @param bool $show Whether to show the admin bar.
	 * @return bool
	 */
	public function maybe_hide_admin_bar( $show ) {
		if ( ! is_user_logged_in() ) {
			return $show;
		}
		$user = wp_get_current_user();
		// Hide for 'os_author' role
		if ( in_array( 'os_author', (array) $user->roles, true ) ) {
			return false;
		}
		return $show;
	}

	protected function get_dashboard_url() {
		return $this->container->get( 'urls' )->get_dashboard_url();
	}

	protected function get_login_url() {
		return $this->container->get( 'urls' )->get_login_url();
	}

}
