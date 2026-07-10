<?php
/**
 * Role management.
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Roles;

use OSWP\Posts\Core\Service_Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles custom roles and capabilities.
 */
class Role_Manager {
	/**
	 * Container.
	 *
	 * @var Service_Container
	 */
	protected $container;

	/**
	 * Constructor.
	 *
	 * @param Service_Container $container Container.
	 */
	public function __construct( Service_Container $container ) {
		$this->container = $container;
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', [ __CLASS__, 'register_role' ] );
		add_action( 'init', [ __CLASS__, 'update_role_capabilities' ] );
	}

	/**
	 * Register custom role.
	 */
	public static function register_role() {
		add_role( 'os_author', __( 'OS Author', 'oswp-posts' ), [
			'read'          => true,
			'edit_posts'    => true,
			'delete_posts'  => true,
			'upload_files'  => true,
			'publish_posts' => false,
		] );
	}

	/**
	 * Update existing role capabilities.
	 * Removes media library capabilities from non-admin roles.
	 */
	public static function update_role_capabilities() {
		$role = get_role( 'os_author' );
		if ( ! $role ) {
			return;
		}

		// Remove media library capabilities - only admins should access media library
		$remove_caps = [
			'edit_attachments',
			'delete_attachments',
			'edit_published_posts',
		];

		foreach ( $remove_caps as $cap ) {
			if ( $role->has_cap( $cap ) ) {
				$role->remove_cap( $cap );
			}
		}
	}
}
