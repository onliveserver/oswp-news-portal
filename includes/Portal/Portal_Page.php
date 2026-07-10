<?php
/**
 * Portal page handler — serves the React SPA.
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Portal;

use OSWP\Posts\Core\Service_Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Portal_Page {

	protected $container;

	/**
	 * The slug for the portal page.
	 */
	const SLUG = 'portal';

	public function __construct( Service_Container $container ) {
		$this->container = $container;
	}

	public function register_hooks() {
		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'render' ] );
	}

	/**
	 * Add rewrite rule so /portal/* lands in WP.
	 *
	 * This ensures direct navigation to nested routes (e.g. /portal/login)
	 * works even if rewrite rules have not been flushed.
	 */
	public function add_rewrite_rules() {
		add_rewrite_rule(
			'^' . self::SLUG . '(/.*)?$',
			'index.php?oswp_portal=1',
			'top'
		);

		// If our portal rewrite rule isn't present in the stored rules, flush once.
		$rules = (array) get_option( 'rewrite_rules', [] );
		$found = false;
		foreach ( array_keys( $rules ) as $rule ) {
			if ( 0 === strpos( $rule, '^' . self::SLUG ) && false !== strpos( $rules[ $rule ], 'oswp_portal=1' ) ) {
				$found = true;
				break;
			}
		}

		if ( ! $found ) {
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Register query var.
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'oswp_portal';
		return $vars;
	}

	/**
	 * Render SPA shell when portal is requested.
	 */
	public function render() {
		if ( ! get_query_var( 'oswp_portal' ) ) {
			return;
		}

		// Disable caching
		nocache_headers();

		$plugin_url = OSWP_POSTS_PLUGIN_URL;
		$site_name  = get_bloginfo( 'name' );
		$api_base   = esc_url_raw( rest_url( 'oswp/v1' ) );
		$nonce      = wp_create_nonce( 'wp_rest' );

		// Build base path from site URL
		$site_path = wp_parse_url( home_url(), PHP_URL_PATH );
		$base_path = rtrim( $site_path, '/' ) . '/' . self::SLUG;

		// Check if built assets exist
		$js_path  = OSWP_POSTS_PLUGIN_DIR . 'assets/portal/js/portal.js';
		$css_path = OSWP_POSTS_PLUGIN_DIR . 'assets/portal/css/portal.css';
		$has_build = file_exists( $js_path );

		$js_url  = $plugin_url . 'assets/portal/js/portal.js';
		$css_url = $plugin_url . 'assets/portal/css/portal.css';

		// Output minimal HTML shell
		?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $site_name ); ?> — Portal</title>
	<?php if ( file_exists( $css_path ) ) : ?>
	<link rel="stylesheet" href="<?php echo esc_url( $css_url ); ?>?v=<?php echo esc_attr( filemtime( $css_path ) ); ?>">
	<?php endif; ?>
	<script>
		window.oswpPortal = <?php echo wp_json_encode( [
			'apiBase'  => $api_base,
			'nonce'    => $nonce,
			'basePath' => $base_path,
			'siteName' => $site_name,
		] ); ?>;
	</script>
	<?php get_header(); ?>
</head>
<body class="oswp-portal-body">
	<div id="oswp-portal"></div>
	<?php if ( $has_build ) : ?>
	<script type="module" src="<?php echo esc_url( $js_url ); ?>?v=<?php echo esc_attr( filemtime( $js_path ) ); ?>"></script>
	<?php else : ?>
	<p style="text-align:center;padding:60px;color:#666;">Portal assets not built. Run <code>npm run build</code> inside the <code>portal/</code> directory.</p>
	<?php endif; ?>
	<?php get_footer(); ?>
</body>
</html>
		<?php
		exit;
	}
}
