<?php
/**
 * Plugin Name: OSWP News Portal
 * Description: Frontend news portal with registration, login, dashboard, and post submission features with email verification.
 * Version: 1.2.1
 * Author: Onlive Server Development Team
 * Text Domain: oswp-news-portal
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 6.0
 *
 * @package OSWP\Posts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OSWP_POSTS_PLUGIN_FILE', __FILE__ );

define( 'OSWP_POSTS_VERSION', '1.2.1' );

define( 'OSWP_POSTS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

define( 'OSWP_POSTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once OSWP_POSTS_PLUGIN_DIR . 'includes/Core/Autoloader.php';
require_once OSWP_POSTS_PLUGIN_DIR . 'includes/Core/Service_Container.php';

use OSWP\Posts\Core\Autoloader;
use OSWP\Posts\Plugin;

( new Autoloader( 'OSWP\\Posts', OSWP_POSTS_PLUGIN_DIR . 'includes' ) )->register();

Plugin::instance()->run();
