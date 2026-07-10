<?php
/**
 * REST API bootstrap.
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Api;

use OSWP\Posts\Core\Service_Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest_Bootstrap {

	protected $container;

	public function __construct( Service_Container $container ) {
		$this->container = $container;
	}

	public function register_hooks() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		$controllers = [
			new Auth_Controller( $this->container ),
			new Profile_Controller( $this->container ),
			new Posts_Controller( $this->container ),
			new Settings_Controller( $this->container ),
			new Admin_Controller( $this->container ),
		];

		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}
	}
}
