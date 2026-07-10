<?php
/**
 * React-powered admin settings page.
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Admin;

use OSWP\Posts\Core\Service_Container;
use OSWP\Posts\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings_Page {
	/**
	 * @var Service_Container
	 */
	protected $container;

	/**
	 * @var mixed
	 */
	protected $settings;

	public function __construct( Service_Container $container ) {
		$this->container = $container;
		$this->settings  = $container->get( 'settings' );
	}

	public function get_settings() {
		return $this->settings;
	}

	public function register_hooks() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'update_option_' . Plugin::OPTION_KEY, [ $this, 'after_settings_save' ], 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( OSWP_POSTS_PLUGIN_FILE ), [ $this, 'add_plugin_action_links' ] );
	}

	public function register_settings() {
		register_setting(
			'oswp_posts_settings',
			Plugin::OPTION_KEY,
			[
				'sanitize_callback' => [ $this, 'sanitize' ],
			]
		);
	}

	public function enqueue_admin_assets( $hook ) {
		if ( false === strpos( $hook, 'oswp-posts' ) ) {
			return;
		}

		$js_path  = OSWP_POSTS_PLUGIN_DIR . 'assets/portal/js/portal.js';
		$css_path = OSWP_POSTS_PLUGIN_DIR . 'assets/portal/css/portal.css';

		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				'oswp-posts-admin-react',
				OSWP_POSTS_PLUGIN_URL . 'assets/portal/css/portal.css',
				[],
				filemtime( $css_path )
			);
		}

		if ( file_exists( $js_path ) ) {
			wp_enqueue_script(
				'oswp-posts-admin-react',
				OSWP_POSTS_PLUGIN_URL . 'assets/portal/js/portal.js',
				[],
				filemtime( $js_path ),
				true
			);
			wp_script_add_data( 'oswp-posts-admin-react', 'type', 'module' );
			wp_localize_script(
				'oswp-posts-admin-react',
				'oswpAdmin',
				[
					'apiBase'        => esc_url_raw( rest_url( 'oswp/v1' ) ),
					'nonce'          => wp_create_nonce( 'wp_rest' ),
					'adminUrl'       => admin_url( 'admin.php?page=oswp-posts' ),
					'initialSection' => $this->get_initial_react_section(),
					'siteName'       => get_bloginfo( 'name' ),
				]
			);
		}
	}

	public function register_menu() {
		add_menu_page(
			__( 'OSWP User Portal', 'oswp-posts' ),
			__( 'OSWP Portal', 'oswp-posts' ),
			'manage_options',
			'oswp-posts',
			[ $this, 'render_page' ],
			'dashicons-groups',
			26
		);

		add_submenu_page(
			'oswp-posts',
			__( 'Portal Dashboard', 'oswp-posts' ),
			__( 'Dashboard', 'oswp-posts' ),
			'manage_options',
			'oswp-posts',
			[ $this, 'render_page' ]
		);

		add_submenu_page(
			'oswp-posts',
			__( 'Portal Settings', 'oswp-posts' ),
			__( 'Settings', 'oswp-posts' ),
			'manage_options',
			'oswp-posts-settings',
			[ $this, 'render_page' ]
		);

		add_submenu_page(
			'oswp-posts',
			__( 'Help & Documentation', 'oswp-posts' ),
			__( 'Help & Documentation', 'oswp-posts' ),
			'manage_options',
			'oswp-posts-help',
			[ $this, 'render_page' ]
		);
	}

	protected function get_initial_react_section() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'oswp-posts'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! empty( $section ) ) {
			return $section;
		}

		if ( 'oswp-posts-help' === $page ) {
			return 'help';
		}

		if ( 'oswp-posts-settings' === $page ) {
			return 'settings';
		}

		$map = [
			'dashboard'         => 'dashboard',
			'pages'             => 'pages',
			'registration_form' => 'forms',
			'article_form'      => 'forms',
			'email_templates'   => 'emails',
			'posts'             => 'posts',
			'menu_visibility'   => 'menu_visibility',
			'keywords'          => 'keywords',
			'ai'                => 'ai',
			'general'           => 'settings',
		];

		return $map[ $tab ] ?? 'dashboard';
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$js_path = OSWP_POSTS_PLUGIN_DIR . 'assets/portal/js/portal.js';
		?>
		<div class="wrap">
			<div id="oswp-admin-app">
				<div class="oswp-loading-screen" style="min-height:70vh;">
					<div style="display:grid;grid-template-columns:240px 1fr;gap:24px;width:100%;max-width:1200px;">
						<div>
							<div style="height:42px;background:#e5e7eb;border-radius:12px;margin-bottom:12px;"></div>
							<div style="height:42px;background:#e5e7eb;border-radius:12px;margin-bottom:12px;"></div>
							<div style="height:42px;background:#e5e7eb;border-radius:12px;margin-bottom:12px;"></div>
						</div>
						<div style="height:260px;background:#f3f4f6;border-radius:18px;border:1px solid #e5e7eb;"></div>
					</div>
				</div>
			</div>
			<?php if ( ! file_exists( $js_path ) ) : ?>
				<p style="margin-top:20px;color:#666;">
					<?php esc_html_e( 'Admin React assets are not built yet. Run npm run build inside the portal directory.', 'oswp-posts' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	public function add_plugin_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=oswp-posts' ) ),
			esc_html__( 'Settings', 'oswp-posts' )
		);

		$help_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=oswp-posts-help' ) ),
			esc_html__( 'Help', 'oswp-posts' )
		);

		array_unshift( $links, $settings_link, $help_link );
		return $links;
	}

	public function sanitize( $input ) {
		$output = [];

		if ( ! is_array( $input ) ) {
			return [];
		}

		foreach ( $input as $key => $value ) {
			switch ( $key ) {
				case 'enable_email_verification':
				case 'post_auto_approve':
				case 'menu_visibility_enabled':
				case 'default_user_verified':
				case 'default_user_account_active':
				case 'default_user_approved_to_post':
					$output[ $key ] = ! empty( $value ) ? 1 : 0;
					break;
				case 'openai_api_key':
				case 'default_registration_role':
				case 'post_limit_message':
				case 'notify_admin_email':
					$output[ $key ] = sanitize_text_field( $value );
					break;
				case 'login_page_id':
				case 'registration_page_id':
				case 'forgot_password_page_id':
				case 'reset_password_page_id':
				case 'logout_page_id':
				case 'post_monthly_limit':
					$output[ $key ] = absint( $value );
					break;
				case 'post_form_description':
					$output[ $key ] = wp_kses_post( $value );
					break;
				case 'email_verification_method':
					$allowed = [ 'none', 'link', 'otp' ];
					$output[ $key ] = in_array( $value, $allowed, true ) ? $value : 'link';
					$output['enable_email_verification'] = ( 'none' !== $output[ $key ] ) ? 1 : 0;
					break;
				case 'post_status_default':
					$allowed = [ 'pending', 'draft', 'publish' ];
					$output[ $key ] = in_array( $value, $allowed, true ) ? $value : 'pending';
					break;
				case 'email_templates':
					if ( is_array( $value ) ) {
						$output[ $key ] = [];
						foreach ( $value as $template_key => $template ) {
							$output[ $key ][ $template_key ] = [
								'subject' => sanitize_text_field( $template['subject'] ?? '' ),
								'body'    => wp_kses_post( $template['body'] ?? '' ),
							];
						}
					}
					break;
				case 'registration_fields':
				case 'post_fields':
					if ( is_array( $value ) ) {
						$output[ $key ] = [];
						foreach ( $value as $field ) {
							if ( empty( $field['id'] ) ) {
								continue;
							}
							if ( 'tab' !== ( $field['type'] ?? '' ) && empty( $field['label'] ) ) {
								continue;
							}

							$field_data = [
								'id'         => sanitize_text_field( $field['id'] ),
								'label'      => sanitize_text_field( $field['label'] ?? '' ),
								'type'       => sanitize_text_field( $field['type'] ),
								'required'   => ! empty( $field['required'] ) ? 1 : 0,
								'is_builtin' => ! empty( $field['is_builtin'] ) ? 1 : 0,
								'meta_key'   => ! empty( $field['meta_key'] ) ? sanitize_text_field( $field['meta_key'] ) : '',
								'width'      => ! empty( $field['width'] ) ? sanitize_text_field( $field['width'] ) : '100',
							];

							$char_limit_types = [ 'text', 'email', 'tel', 'url', 'password', 'textarea', 'wysiwyg' ];
							if ( in_array( $field['type'], $char_limit_types, true ) ) {
								if ( isset( $field['min_limit'] ) ) {
									$field_data['min_limit'] = absint( $field['min_limit'] );
								}
								if ( isset( $field['max_limit'] ) && '' !== $field['max_limit'] ) {
									$field_data['max_limit'] = absint( $field['max_limit'] );
								}
							}

							if ( 'select' === $field['type'] && ! empty( $field['options_raw'] ) ) {
								$options = [];
								$lines   = explode( "\n", $field['options_raw'] );
								foreach ( $lines as $line ) {
									$line = trim( $line );
									if ( strpos( $line, ':' ) !== false ) {
										list( $val, $label ) = explode( ':', $line, 2 );
										$options[ trim( $val ) ] = trim( $label );
									} else {
										$options[ $line ] = $line;
									}
								}
								$field_data['options'] = $options;
							}

							$output[ $key ][] = $field_data;
						}
					}
					break;
			}
		}

		foreach ( [ 'enable_email_verification', 'post_auto_approve', 'menu_visibility_enabled', 'default_user_verified', 'default_user_account_active', 'default_user_approved_to_post' ] as $field ) {
			if ( ! isset( $output[ $field ] ) ) {
				$output[ $field ] = 0;
			}
		}

		return $output;
	}

	public function after_settings_save( $old_value, $new_value ) {
		if ( isset( $this->container ) && method_exists( $this->container, 'get' ) ) {
			$settings = $this->container->get( 'settings' );
			if ( $settings && method_exists( $settings, 'refresh' ) ) {
				$settings->refresh();
			}
		}
	}
}
