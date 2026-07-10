<?php
/**
 * Plugin installer / activator.
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Core;

use OSWP\Posts\Roles\Role_Manager;
use OSWP\Posts\Settings\Settings_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles activation tasks like creating roles and pages.
 */
class Installer {
	/**
	 * Settings repository.
	 *
	 * @var Settings_Repository
	 */
	protected $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings_Repository $settings Settings repository.
	 */
	public function __construct( Settings_Repository $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Run installation tasks.
	 */
	public function install() {
		Role_Manager::register_role();
		$this->bootstrap_options();

		if ( $this->settings->get( 'auto_create_pages' ) ) {
			$this->create_required_pages();
		}
	}

	/**
	 * Ensure options exist.
	 */
	protected function bootstrap_options() {
		$current  = $this->settings->all();
		$defaults = $this->settings->defaults();
		$this->settings->update( wp_parse_args( $current, $defaults ) );
	}

	/**
	 * Create required frontend pages when missing.
	 */
	protected function create_required_pages() {
		$pages = [
			'dashboard_page_id'       => [ 'title' => __( 'My Dashboard', 'oswp-posts' ), 'slug' => 'my-dashboard', 'route' => '/portal/dashboard' ],
			'login_page_id'           => [ 'title' => __( 'Login', 'oswp-posts' ), 'slug' => 'login', 'route' => '/portal/login' ],
			'registration_page_id'    => [ 'title' => __( 'Registration', 'oswp-posts' ), 'slug' => 'registration', 'route' => '/portal/register' ],
			'verification_page_id'    => [ 'title' => __( 'Account Verification', 'oswp-posts' ), 'slug' => 'account-verification', 'route' => '/portal/verify' ],
			'forgot_password_page_id' => [ 'title' => __( 'Forgot Password', 'oswp-posts' ), 'slug' => 'forgot-password', 'route' => '/portal/forgot-password' ],
			'reset_password_page_id'  => [ 'title' => __( 'Reset Password', 'oswp-posts' ), 'slug' => 'reset-password', 'route' => '/portal/reset-password' ],
			'logout_page_id'          => [ 'title' => __( 'Logout', 'oswp-posts' ), 'slug' => 'logout', 'route' => '/portal/login' ],
		];

		foreach ( $pages as $setting_key => $config ) {
			$this->ensure_page( $setting_key, $config['title'], $config['slug'], $config['route'] );
		}
	}

	/**
	 * Ensure a legacy compatibility page exists for a portal route.
	 *
	 * @param string $setting_key Setting key to update.
	 * @param string $title       Page title.
	 * @param string $slug        Page slug.
	 * @param string $route       Portal route.
	 */
	protected function ensure_page( $setting_key, $title, $slug, $route ) {
		$page_id = absint( $this->settings->get( $setting_key ) );

		if ( $page_id && get_post( $page_id ) ) {
			return;
		}

		$page = get_page_by_title( $title );
		if ( $page ) {
			$this->settings->set( $setting_key, $page->ID );
			return;
		}

		$target  = home_url( $route );
		$content = sprintf(
			'<p>%1$s</p><p><a href="%2$s">%3$s</a></p>',
			esc_html__( 'This legacy page now forwards to the React portal experience.', 'oswp-posts' ),
			esc_url( $target ),
			esc_html__( 'Continue to portal', 'oswp-posts' )
		);

		$page_id = wp_insert_post(
			[
				'post_title'   => $title,
				'post_name'    => sanitize_title( $slug ),
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $content,
			]
		);

		if ( ! is_wp_error( $page_id ) && $page_id ) {
			$this->settings->set( $setting_key, $page_id );
		}
	}
}
