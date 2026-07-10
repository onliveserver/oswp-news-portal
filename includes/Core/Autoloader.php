<?php
/**
 * Autoloader.
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simple PSR-4 style autoloader for plugin classes.
 */
class Autoloader {
	/**
	 * Base namespace.
	 *
	 * @var string
	 */
	protected $namespace;

	/**
	 * Base directory.
	 *
	 * @var string
	 */
	protected $base_dir;

	/**
	 * Constructor.
	 *
	 * @param string $namespace Base namespace.
	 * @param string $base_dir  Base directory.
	 */
	public function __construct( $namespace, $base_dir ) {
		$this->namespace = trim( $namespace, '\\' ) . '\\';
		$this->base_dir  = trailingslashit( $base_dir );
	}

	/**
	 * Register autoloader.
	 */
	public function register() {
		spl_autoload_register( [ $this, 'load' ] );
	}

	/**
	 * Load class file if matches namespace.
	 *
	 * @param string $class Class name.
	 */
	public function load( $class ) {
		if ( 0 !== strpos( $class, $this->namespace ) ) {
			return;
		}

		$relative = substr( $class, strlen( $this->namespace ) );
		$relative = str_replace( '\\', '/', $relative );
		$file     = $this->base_dir . $relative . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
