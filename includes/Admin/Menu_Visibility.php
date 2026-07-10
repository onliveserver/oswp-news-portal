<?php
/**
 * Menu visibility placeholder.
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Admin;

use OSWP\Posts\Core\Service_Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Menu_Visibility {
	protected $container;
	protected $settings;

	public function __construct( Service_Container $container ) {
		$this->container = $container;
		$this->settings  = $container->get( 'settings' );
	}

	public function register_hooks() {
		add_action( 'wp_nav_menu_item_custom_fields', [ $this, 'add_menu_visibility_fields' ], 10, 4 );
		add_action( 'wp_update_nav_menu_item', [ $this, 'save_menu_visibility_fields' ], 10, 3 );
		add_filter( 'wp_nav_menu_objects', [ $this, 'filter_menu_items' ], 20, 2 );
	}

	public function add_menu_visibility_fields( $item_id, $item, $depth, $args ) {
		if ( ! $this->settings->get( 'menu_visibility_enabled' ) ) {
			return;
		}

		$visibility = get_post_meta( $item_id, '_oswp_menu_visibility', true );
		if ( ! $visibility ) {
			$visibility = $this->settings->get( 'menu_visibility_default', 'logged_out' );
		}

		$specific_roles = get_post_meta( $item_id, '_oswp_menu_visibility_roles', true ) ?: [];
		$show_for_logged_in = get_post_meta( $item_id, '_oswp_menu_visibility_show_logged_in', true );
		$show_for_logged_out = get_post_meta( $item_id, '_oswp_menu_visibility_show_logged_out', true );
		
		// Get all available roles
		$wp_roles = wp_roles();
		$available_roles = isset( $wp_roles->roles ) ? array_keys( $wp_roles->roles ) : [];
		?>
		<p class="field-oswp-visibility description description-wide oswp-admin-field oswp-admin-field--menu-visibility">
			<label for="edit-menu-item-oswp-visibility-<?php echo esc_attr( $item_id ); ?>">
				<strong class="oswp-admin-label oswp-admin-label--menu-visibility"><?php esc_html_e( 'Menu Visibility', 'oswp-posts' ); ?></strong><br />
				<select name="oswp_menu_visibility[<?php echo esc_attr( $item_id ); ?>]" id="edit-menu-item-oswp-visibility-<?php echo esc_attr( $item_id ); ?>" class="oswp-admin-select oswp-admin-select--menu-visibility" style="width: 100%; margin-bottom: 10px;" onchange="document.getElementById('oswp-roles-<?php echo esc_attr( $item_id ); ?>').style.display = this.value === 'roles' ? 'block' : 'none';">
					<option value="everyone" class="oswp-admin-option oswp-admin-option--everyone" <?php selected( $visibility, 'everyone' ); ?>><?php esc_html_e( 'Everyone', 'oswp-posts' ); ?></option>
					<option value="logged_in" class="oswp-admin-option oswp-admin-option--logged-in" <?php selected( $visibility, 'logged_in' ); ?>><?php esc_html_e( 'Logged In Users', 'oswp-posts' ); ?></option>
					<option value="logged_out" class="oswp-admin-option oswp-admin-option--logged-out" <?php selected( $visibility, 'logged_out' ); ?>><?php esc_html_e( 'Logged Out Users (Not logged in)', 'oswp-posts' ); ?></option>
					<option value="roles" class="oswp-admin-option oswp-admin-option--roles" <?php selected( $visibility, 'roles' ); ?>><?php esc_html_e( 'Specific Roles', 'oswp-posts' ); ?></option>
					<option value="hidden" class="oswp-admin-option oswp-admin-option--hidden" <?php selected( $visibility, 'hidden' ); ?>><?php esc_html_e( 'Hidden (never show)', 'oswp-posts' ); ?></option>
				</select>
			</label>
		</p>
		
		<div id="oswp-roles-<?php echo esc_attr( $item_id ); ?>" style="display: <?php echo 'roles' === $visibility ? 'block' : 'none'; ?>; padding: 10px; border: 1px solid #ddd; background: #f9f9f9; margin-top: 10px;">
			<label>
				<strong><?php esc_html_e( 'Select which roles can see this menu item:', 'oswp-posts' ); ?></strong>
			</label>
			<div style="margin-top: 10px;">
				<?php foreach ( $available_roles as $role ) : ?>
					<label style="display: block; margin-bottom: 8px;">
						<input type="checkbox" name="oswp_menu_visibility_roles[<?php echo esc_attr( $item_id ); ?>][]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, $specific_roles, true ) ); ?> />
						<?php echo esc_html( ucfirst( str_replace( '_', ' ', $role ) ) ); ?>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	public function save_menu_visibility_fields( $menu_id, $menu_item_db_id, $args ) {
		if ( isset( $_POST['oswp_menu_visibility'][ $menu_item_db_id ] ) ) {
			$visibility = sanitize_text_field( $_POST['oswp_menu_visibility'][ $menu_item_db_id ] );
			update_post_meta( $menu_item_db_id, '_oswp_menu_visibility', $visibility );
		}
		
		// Save specific roles for the menu item
		if ( isset( $_POST['oswp_menu_visibility_roles'][ $menu_item_db_id ] ) ) {
			$roles = array_map( 'sanitize_text_field', (array) $_POST['oswp_menu_visibility_roles'][ $menu_item_db_id ] );
			update_post_meta( $menu_item_db_id, '_oswp_menu_visibility_roles', $roles );
		} else {
			delete_post_meta( $menu_item_db_id, '_oswp_menu_visibility_roles' );
		}
	}

	public function filter_menu_items( $items, $args ) {
		if ( ! $this->settings->get( 'menu_visibility_enabled' ) ) {
			return $items;
		}

		if ( is_admin() ) {
			return $items;
		}

		$is_logged_in = is_user_logged_in();
		$current_user = wp_get_current_user();
		$user_roles = $current_user->roles ?? [];

		$default_visibility = $this->settings->get( 'menu_visibility_default', 'logged_out' );

		foreach ( $items as $key => $item ) {
			$visibility = get_post_meta( $item->ID, '_oswp_menu_visibility', true );
			if ( ! $visibility ) {
				$visibility = $default_visibility;
			}

			if ( 'everyone' === $visibility ) {
				continue;
			}

			$should_hide = false;

			// "Hidden" means always hide this menu item.
			if ( 'hidden' === $visibility ) {
				$should_hide = true;
			}

			if ( 'logged_in' === $visibility && ! $is_logged_in ) {
				$should_hide = true;
			} elseif ( 'logged_out' === $visibility && $is_logged_in ) {
				$should_hide = true;
			} elseif ( 'roles' === $visibility ) {
				// Check if user has any of the required roles
				$required_roles = get_post_meta( $item->ID, '_oswp_menu_visibility_roles', true ) ?: [];
				
				if ( ! empty( $required_roles ) ) {
					$has_required_role = false;
					
					foreach ( $user_roles as $user_role ) {
						if ( in_array( $user_role, $required_roles, true ) ) {
							$has_required_role = true;
							break;
						}
					}
					
					if ( ! $has_required_role ) {
						$should_hide = true;
					}
				} else {
					// No roles selected - hide for everyone
					$should_hide = true;
				}
			}

			if ( $should_hide ) {
				unset( $items[ $key ] );
			}
		}

		return $items;
	}
}
