<?php
/**
 * Simple service container.
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lightweight container to lazy-load services.
 */
class Service_Container {
	/**
	 * Registered services.
	 *
	 * @var array<string, callable|object>
	 */
	protected $services = [];

	/**
	 * Cached instances.
	 *
	 * @var array<string, object>
	 */
	protected $instances = [];

	/**
	 * Register a service factory.
	 *
	 * @param string          $id      Identifier.
	 * @param callable|object $service Factory or object.
	 */
	public function set( $id, $service ) {
		$this->services[ $id ] = $service;
	}

	/**
	 * Retrieve a service.
	 *
	 * @param string $id Identifier.
	 *
	 * @return mixed
	 */
	public function get( $id ) {
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		if ( ! isset( $this->services[ $id ] ) ) {
			return null;
		}

		$service = $this->services[ $id ];

		if ( is_callable( $service ) ) {
			$service = $service( $this );
		}

		$this->instances[ $id ] = $service;

		return $service;
	}
}
