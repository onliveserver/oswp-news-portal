<?php
/**
 * Main plugin bootstrap.
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts;

use OSWP\Posts\Core\Autoloader;
use OSWP\Posts\Core\Installer;
use OSWP\Posts\Core\Service_Container;
use OSWP\Posts\Core\Template_Loader;
use OSWP\Posts\Settings\Settings_Repository;
use OSWP\Posts\Emails\Email_Log;
use OSWP\Posts\Admin\Email_Logs_Page;
use OSWP\Posts\Updates\Updater_Bootstrap;
use OSWP\Posts\Updates\Version_Manager;
use OSWP\Posts\Updates\Update_Hooks;
use OSWP\Posts\Content\Keyword_Manager;
use OSWP\Posts\Api\Rest_Bootstrap;
use OSWP\Posts\Portal\Portal_Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin bootstrap class.
 */
class Plugin {
	const VERSION = '0.9.0';

	/**
	 * Option key.
	 */
	const OPTION_KEY = 'oswp_posts_settings';

	/**
	 * Instance.
	 *
	 * @var Plugin
	 */
	protected static $instance;

	/**
	 * Service container.
	 *
	 * @var Service_Container
	 */
	protected $container;

	/**
	 * Registered module instances.
	 *
	 * @var array
	 */
	protected $modules = [];

	/**
	 * Plugin constructor.
	 */
	protected function __construct() {
		$this->container = new Service_Container();
	}

	/**
	 * Singleton accessor.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Run plugin.
	 */
	public function run() {
		$this->register_hooks();
	}

	/**
	 * Register WP hooks.
	 */
	protected function register_hooks() {
		register_activation_hook( OSWP_POSTS_PLUGIN_FILE, [ __CLASS__, 'activate' ] );
		register_deactivation_hook( OSWP_POSTS_PLUGIN_FILE, [ __CLASS__, 'deactivate' ] );

		if ( did_action( 'plugins_loaded' ) ) {
			$this->boot();
		} else {
			add_action( 'plugins_loaded', [ $this, 'boot' ] );
		}
	}

	/**
	 * Boot plugin after plugins_loaded.
	 */
	public function boot() {
		$this->load_textdomain();
		$this->register_services();
		$this->boot_modules();
		$this->boot_api();
		$this->boot_portal();
		$this->setup_email_logs();
		$this->initialize_updater();
		
		// Initialize default keywords if needed
		Keyword_Manager::initialize_default_keywords();
		
		// Initialize SEO blocked keywords
		Keyword_Manager::initialize_seo_blocked_keywords();
		
        Keyword_Manager::register_hooks();
		$this->restrict_admin_access();
	}

	/**
	 * Load translations.
	 */
	protected function load_textdomain() {
		load_plugin_textdomain( 'oswp-posts', false, dirname( plugin_basename( OSWP_POSTS_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Register services with container.
	 */
	protected function register_services() {
		$this->container->set( 'settings', function () {
			return new Settings_Repository( self::OPTION_KEY );
		} );

		$this->container->set( 'view', function () {
			return new Template_Loader();
		} );

		// Centralized URL manager for plugin links
		$this->container->set( 'urls', function ( $c ) {
			return new Core\Url_Manager( $c );
		} );
	}

	/**
	 * Boot modules.
	 */
	protected function boot_modules() {
		foreach ( $this->get_module_classes() as $service_id => $class ) {
			$this->modules[ $service_id ] = new $class( $this->container );
			$this->container->set( 'module.' . $service_id, $this->modules[ $service_id ] );

			if ( method_exists( $this->modules[ $service_id ], 'register_hooks' ) ) {
				$this->modules[ $service_id ]->register_hooks();
			}
		}
	}

	/**
	 * Module class map.
	 *
	 * @return array<string, string>
	 */
	protected function get_module_classes() {
		return [
			'roles'       => Roles\Role_Manager::class,
			'emails'      => Emails\Email_Service::class,
			'posts'       => Posts\Post_Manager::class,
			'auth_verify' => Auth\Email_Verification::class,
			'dashboard'   => Dashboard\Dashboard_Manager::class,
			'settings'    => Admin\Settings_Page::class,
			'menus'       => Admin\Menu_Visibility::class,
			'post_carousel_block' => Blocks\Post_Carousel_Block::class,
		];
	}

	/**
	 * Plugin activation callback.
	 */
	public static function activate() {
		$settings  = new Settings_Repository( self::OPTION_KEY );
		$installer = new Installer( $settings );
		$installer->install();
		
		// Create email logs table (temporary testing system)
		Email_Log::create_table();

		// Initialize default keywords
		Keyword_Manager::initialize_default_keywords();
		
		flush_rewrite_rules();
	}

	/**
	 * Boot REST API.
	 */
	protected function boot_api() {
		$api = new Rest_Bootstrap( $this->container );
		$api->register_hooks();
	}

	/**
	 * Boot portal SPA page.
	 */
	protected function boot_portal() {
		$portal = new Portal_Page( $this->container );
		$portal->register_hooks();
	}

	/**
	 * Setup email logs page and AJAX handlers.
	 */
	protected function setup_email_logs() {
		if ( is_admin() ) {
			add_action( 'admin_menu', [ 'OSWP\Posts\Admin\Email_Logs_Page', 'add_menu' ] );
			add_action( 'admin_enqueue_scripts', [ 'OSWP\Posts\Admin\Email_Logs_Page', 'enqueue_scripts' ] );
			add_action( 'wp_ajax_oswp_get_email_log', [ 'OSWP\Posts\Admin\Email_Logs_Page', 'ajax_get_log' ] );
		}
	}

	/**
	 * Initialize plugin auto-updater.
	 */
	protected function initialize_updater() {
		// Initialize the auto-updater
		Updater_Bootstrap::init();

		// Setup update hooks
		$version_manager = new Version_Manager( 'oswp-news-portal' );
		$update_hooks    = new Update_Hooks( $version_manager, self::VERSION );
		$update_hooks->register();

		// Store version manager in container for access elsewhere
		$this->container->set( 'version_manager', $version_manager );
	}

	/**
	 * Restrict wp-admin access for plugin users.
	 */
	protected function restrict_admin_access() {
		// Only restrict access on admin pages.
		if ( ! is_admin() ) {
			return;
		}

		// Allow AJAX requests.
		if ( wp_doing_ajax() ) {
			return;
		}

		// Allow REST API requests (block editor relies on these).
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		// Allow WP-CLI.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		// Allow cron requests.
		if ( wp_doing_cron() ) {
			return;
		}

		// Check if user is logged in and has os_author role.
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( in_array( 'os_author', $user->roles, true ) ) {
				// Redirect to React portal dashboard.
				wp_safe_redirect( home_url( '/portal/dashboard' ) );
				exit;
			}
		}
	}

	/**
	 * Plugin deactivation callback.
	 */
	public static function deactivate() {
		// Remove email logs table (temporary testing system)
		Email_Log::remove_table();
		
		flush_rewrite_rules();
	}

	/**
	 * Get container.
	 *
	 * @return Service_Container
	 */
	public function container() {
		return $this->container;
	}
}
