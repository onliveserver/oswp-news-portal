<?php
/**
 * Admin REST controller.
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Api;

use OSWP\Posts\Content\Keyword_Manager;
use OSWP\Posts\Core\Service_Container;
use OSWP\Posts\Plugin;
use OSWP\Posts\Portal\Portal_Page;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Controller {

	const NAMESPACE = 'oswp/v1';

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

	public function register_routes() {
		register_rest_route( self::NAMESPACE, '/admin/overview', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_overview' ],
			'permission_callback' => [ $this, 'can_manage' ],
		] );

		register_rest_route( self::NAMESPACE, '/admin/settings', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => [ $this, 'can_manage' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_settings' ],
				'permission_callback' => [ $this, 'can_manage' ],
			],
		] );
	}

	public function can_manage() {
		return current_user_can( 'manage_options' );
	}

	public function get_overview( WP_REST_Request $request ) {
		$user_count = count_users();
		$post_count = wp_count_posts( 'post' );
		$settings   = $this->settings->all();
		$workspace  = $this->get_admin_workspace_payload();

		return new WP_REST_Response( [
			'stats'            => [
				'total_users'      => (int) ( $user_count['total_users'] ?? 0 ),
				'published_posts'  => (int) ( $post_count->publish ?? 0 ),
				'pending_posts'    => (int) ( $post_count->pending ?? 0 ),
				'draft_posts'      => (int) ( $post_count->draft ?? 0 ),
				'registration_fields' => count( $settings['registration_fields'] ?? [] ),
				'post_fields'      => count( $settings['post_fields'] ?? [] ),
			],
			'sections'         => $this->get_sections(),
			'pages'            => $this->get_page_assignments(),
			'portal'           => [
				'base' => home_url( '/' . Portal_Page::SLUG . '/' ),
				'routes' => [
					'login' => home_url( '/' . Portal_Page::SLUG . '/login' ),
					'register' => home_url( '/' . Portal_Page::SLUG . '/register' ),
					'dashboard' => home_url( '/' . Portal_Page::SLUG . '/dashboard' ),
					'new_post' => home_url( '/' . Portal_Page::SLUG . '/posts/new' ),
				],
			],
			'settings'         => $workspace['settings'],
			'forms'            => $workspace['forms'],
			'posts'            => $workspace['posts'],
			'emails'           => $workspace['emails'],
			'keywords'         => $workspace['keywords'],
		], 200 );
	}

	public function get_settings( WP_REST_Request $request ) {
		$section  = sanitize_key( (string) $request->get_param( 'section' ) );
		$payloads = $this->get_admin_workspace_payload();

		if ( ! empty( $section ) && isset( $payloads[ $section ] ) ) {
			return new WP_REST_Response( [ $section => $payloads[ $section ] ], 200 );
		}

		return new WP_REST_Response( $payloads, 200 );
	}

	public function update_settings( WP_REST_Request $request ) {
		$payload = $request->get_json_params();

		if ( ! is_array( $payload ) ) {
			$payload = $request->get_params();
		}

		$section = sanitize_key( $payload['section'] ?? '' );
		$values  = isset( $payload['values'] ) && is_array( $payload['values'] ) ? $payload['values'] : [];
		$current = $this->settings->all();
		$updated = [];

		switch ( $section ) {
			case 'settings':
				$updated = $this->sanitize_general_settings( $values, $current );
				break;

			case 'forms':
				$updated = $this->sanitize_form_settings( $values, $current );
				break;

			case 'posts':
				$updated = $this->sanitize_post_settings( $values, $current );
				break;

			case 'emails':
				$updated = $this->sanitize_email_settings( $values, $current );
				break;

			case 'keywords':
				$this->save_keywords_settings( $values );
				return new WP_REST_Response( [
					'message'  => __( 'Keywords saved.', 'oswp-posts' ),
					'keywords' => $this->get_keywords_payload(),
				], 200 );

			default:
				return new WP_Error( 'unsupported_section', __( 'Unsupported admin settings section.', 'oswp-posts' ), [ 'status' => 400 ] );
		}

		update_option( Plugin::OPTION_KEY, array_merge( $current, $updated ) );
		$this->settings->refresh();
		$payloads = $this->get_admin_workspace_payload();

		return new WP_REST_Response( [
			'message' => __( 'Settings saved.', 'oswp-posts' ),
			$section  => $payloads[ $section ] ?? [],
		], 200 );
	}

	protected function get_sections() {
		return [
			[ 'id' => 'dashboard', 'label' => __( 'Dashboard', 'oswp-posts' ), 'description' => __( 'Overview, counts, and migration status.', 'oswp-posts' ), 'status' => 'live' ],
			[ 'id' => 'settings', 'label' => __( 'Settings', 'oswp-posts' ), 'description' => __( 'Verification, defaults, and registration behavior.', 'oswp-posts' ), 'status' => 'live' ],
			[ 'id' => 'menu_visibility', 'label' => __( 'Menu Visibility', 'oswp-posts' ), 'description' => __( 'Control which menu items show for logged-in or logged-out visitors.', 'oswp-posts' ), 'status' => 'live' ],
			[ 'id' => 'pages', 'label' => __( 'Portal Routes', 'oswp-posts' ), 'description' => __( 'Portal-first routes replacing shortcode pages.', 'oswp-posts' ), 'status' => 'live' ],
			[ 'id' => 'forms', 'label' => __( 'Forms', 'oswp-posts' ), 'description' => __( 'Registration and article field builders.', 'oswp-posts' ), 'status' => 'live' ],
			[ 'id' => 'emails', 'label' => __( 'Emails', 'oswp-posts' ), 'description' => __( 'Transactional templates and placeholders.', 'oswp-posts' ), 'status' => 'live' ],
			[ 'id' => 'posts', 'label' => __( 'Posts', 'oswp-posts' ), 'description' => __( 'Approval, limits, uploads, and SEO rules.', 'oswp-posts' ), 'status' => 'live' ],
			[ 'id' => 'keywords', 'label' => __( 'Keywords', 'oswp-posts' ), 'description' => __( 'Blocked keyword moderation list.', 'oswp-posts' ), 'status' => 'live' ],
			[ 'id' => 'help', 'label' => __( 'Help', 'oswp-posts' ), 'description' => __( 'React-based migration notes and next steps.', 'oswp-posts' ), 'status' => 'live' ],
		];
	}

	protected function get_admin_workspace_payload() {
		return [
			'pages'    => $this->get_page_assignments(),
			'settings' => $this->get_general_settings_payload(),
			'forms'    => $this->get_forms_payload(),
			'posts'    => $this->get_posts_settings_payload(),
			'emails'   => $this->get_emails_payload(),
			'keywords' => $this->get_keywords_payload(),
		];
	}

	protected function get_general_settings_payload() {
		return [
			'email_verification_method'      => (string) $this->settings->get( 'email_verification_method', 'otp' ),
			'enable_email_verification'      => (bool) $this->settings->get( 'enable_email_verification', true ),
			'verification_required'          => (bool) $this->settings->get( 'verification_required', true ),
			'otp_max_resends'                => (int) $this->settings->get( 'otp_max_resends', 3 ),
			'otp_resend_cooldown'            => (int) $this->settings->get( 'otp_resend_cooldown', 2 ),
			'otp_expiry_hours'               => (int) $this->settings->get( 'otp_expiry_hours', 24 ),
			'default_registration_role'      => (string) $this->settings->get( 'default_registration_role', 'subscriber' ),
			'default_user_verified'          => (bool) $this->settings->get( 'default_user_verified', false ),
			'default_user_account_active'    => (bool) $this->settings->get( 'default_user_account_active', true ),
			'default_user_approved_to_post'  => (bool) $this->settings->get( 'default_user_approved_to_post', false ),
			'notify_admin_email'             => (string) $this->settings->get( 'notify_admin_email', get_option( 'admin_email', '' ) ),
			'menu_visibility_enabled'        => (bool) $this->settings->get( 'menu_visibility_enabled', true ),
			'menu_visibility_default'        => (string) $this->settings->get( 'menu_visibility_default', 'logged_out' ),
			'dashboard_sections'             => array_values( (array) $this->settings->get( 'dashboard_sections', [] ) ),
			'available_roles'                => $this->get_available_roles(),
			'available_dashboard_sections'   => [
				[ 'value' => 'overview', 'label' => __( 'Overview', 'oswp-posts' ) ],
				[ 'value' => 'profile', 'label' => __( 'Profile', 'oswp-posts' ) ],
				[ 'value' => 'posts', 'label' => __( 'Posts', 'oswp-posts' ) ],
				[ 'value' => 'new_post', 'label' => __( 'New post', 'oswp-posts' ) ],
				[ 'value' => 'password', 'label' => __( 'Password', 'oswp-posts' ) ],
			],
		];
	}

	protected function get_forms_payload() {
		return [
			'registration_fields' => array_values( (array) $this->settings->get( 'registration_fields', [] ) ),
			'post_fields'         => array_values( (array) $this->settings->get( 'post_fields', [] ) ),
			'post_form_description' => (string) $this->settings->get( 'post_form_description', '' ),
			'field_types'         => [
				[ 'value' => 'tab', 'label' => __( 'Section tab', 'oswp-posts' ) ],
				[ 'value' => 'text', 'label' => __( 'Text', 'oswp-posts' ) ],
				[ 'value' => 'email', 'label' => __( 'Email', 'oswp-posts' ) ],
				[ 'value' => 'password', 'label' => __( 'Password', 'oswp-posts' ) ],
				[ 'value' => 'tel', 'label' => __( 'Phone', 'oswp-posts' ) ],
				[ 'value' => 'url', 'label' => __( 'URL', 'oswp-posts' ) ],
				[ 'value' => 'textarea', 'label' => __( 'Textarea', 'oswp-posts' ) ],
				[ 'value' => 'wysiwyg', 'label' => __( 'WYSIWYG', 'oswp-posts' ) ],
				[ 'value' => 'select', 'label' => __( 'Select', 'oswp-posts' ) ],
				[ 'value' => 'number', 'label' => __( 'Number', 'oswp-posts' ) ],
				[ 'value' => 'date', 'label' => __( 'Date', 'oswp-posts' ) ],
				[ 'value' => 'category', 'label' => __( 'Category', 'oswp-posts' ) ],
				[ 'value' => 'media', 'label' => __( 'Media upload', 'oswp-posts' ) ],
			],
			'widths'              => [ '25', '33', '50', '66', '75', '100' ],
		];
	}

	protected function get_posts_settings_payload() {
		return [
			'post_monthly_limit'       => (int) $this->settings->get( 'post_monthly_limit', 5 ),
			'post_limit_message'       => (string) $this->settings->get( 'post_limit_message', __( 'You have reached your post limit for this period.', 'oswp-posts' ) ),
			'post_auto_approve'        => (bool) $this->settings->get( 'post_auto_approve', true ),
			'post_status_default'      => (string) $this->settings->get( 'post_status_default', 'pending' ),
			'allowed_mime_types'       => array_values( (array) $this->settings->get( 'allowed_mime_types', [ 'image/jpeg', 'image/png', 'image/gif' ] ) ),
			'seo_title_min_length'     => (int) $this->settings->get( 'seo_title_min_length', 50 ),
			'seo_meta_desc_min_length' => (int) $this->settings->get( 'seo_meta_desc_min_length', 150 ),
			'max_tags_per_post'        => (int) $this->settings->get( 'max_tags_per_post', 5 ),
			'auto_focus_keyword_from_first_tag' => (bool) $this->settings->get( 'auto_focus_keyword_from_first_tag', true ),
			'allowed_post_statuses'    => [ 'pending', 'draft', 'publish' ],
		];
	}

	protected function get_emails_payload() {
		return [
			'email_templates' => (array) $this->settings->get( 'email_templates', [] ),
			'placeholders'    => [
				'{site_name}',
				'{site_url}',
				'{first_name}',
				'{last_name}',
				'{display_name}',
				'{user_email}',
				'{email}',
				'{date}',
				'{login_url}',
				'{dashboard_url}',
				'{verification_link}',
				'{verification_code}',
				'{reset_link}',
				'{reset_code}',
				'{remaining_posts}',
				'{admin_email}',
			],
		];
	}

	protected function get_keywords_payload() {
		$keywords_data = Keyword_Manager::get_keywords_data();
		ksort( $keywords_data, SORT_NATURAL | SORT_FLAG_CASE );

		$items = [];
		foreach ( $keywords_data as $keyword => $meta ) {
			$user_id = absint( $meta['added_by'] ?? 0 );
			$user    = $user_id ? get_userdata( $user_id ) : null;
			$items[] = [
				'keyword'   => (string) $keyword,
				'added'     => ! empty( $meta['added'] ) ? gmdate( 'Y-m-d H:i:s', (int) $meta['added'] ) : '',
				'added_by'  => $user ? $user->display_name : '',
			];
		}

		return [
			'items' => $items,
			'total' => count( $items ),
		];
	}

	protected function sanitize_general_settings( array $values, array $current ) {
		$allowed_methods = [ 'none', 'link', 'otp' ];
		$allowed_dashboards = [ 'overview', 'profile', 'posts', 'new_post', 'password' ];
		$method = sanitize_text_field( $values['email_verification_method'] ?? $current['email_verification_method'] ?? 'otp' );
		$method = in_array( $method, $allowed_methods, true ) ? $method : 'otp';

		$dashboard_sections = isset( $values['dashboard_sections'] ) && is_array( $values['dashboard_sections'] )
			? array_values( array_unique( array_values( array_intersect( $allowed_dashboards, array_map( 'sanitize_text_field', $values['dashboard_sections'] ) ) ) ) )
			: array_values( (array) ( $current['dashboard_sections'] ?? [] ) );

		$allowed_menu_visibility_defaults = [ 'everyone', 'logged_in', 'logged_out', 'hidden' ];
		$menu_visibility_default = sanitize_text_field( $values['menu_visibility_default'] ?? $current['menu_visibility_default'] ?? 'logged_out' );
		if ( ! in_array( $menu_visibility_default, $allowed_menu_visibility_defaults, true ) ) {
			$menu_visibility_default = 'logged_out';
		}

		return [
			'email_verification_method'     => $method,
			'enable_email_verification'     => 'none' === $method ? 0 : 1,
			'verification_required'         => ! empty( $values['verification_required'] ) ? 1 : 0,
			'otp_max_resends'               => max( 0, absint( $values['otp_max_resends'] ?? $current['otp_max_resends'] ?? 3 ) ),
			'otp_resend_cooldown'           => max( 0, absint( $values['otp_resend_cooldown'] ?? $current['otp_resend_cooldown'] ?? 2 ) ),
			'otp_expiry_hours'              => max( 1, absint( $values['otp_expiry_hours'] ?? $current['otp_expiry_hours'] ?? 24 ) ),
			'default_registration_role'     => sanitize_text_field( $values['default_registration_role'] ?? $current['default_registration_role'] ?? 'subscriber' ),
			'default_user_verified'         => ! empty( $values['default_user_verified'] ) ? 1 : 0,
			'default_user_account_active'   => ! empty( $values['default_user_account_active'] ) ? 1 : 0,
			'default_user_approved_to_post' => ! empty( $values['default_user_approved_to_post'] ) ? 1 : 0,
			'notify_admin_email'            => sanitize_email( $values['notify_admin_email'] ?? $current['notify_admin_email'] ?? get_option( 'admin_email', '' ) ),
			'menu_visibility_enabled'       => ! empty( $values['menu_visibility_enabled'] ) ? 1 : 0,
			'menu_visibility_default'       => $menu_visibility_default,
			'dashboard_sections'            => $dashboard_sections,
		];
	}

	protected function sanitize_form_settings( array $values, array $current ) {
		return [
			'registration_fields'   => $this->sanitize_field_group( $values['registration_fields'] ?? $current['registration_fields'] ?? [] ),
			'post_fields'           => $this->sanitize_field_group( $values['post_fields'] ?? $current['post_fields'] ?? [] ),
			'post_form_description' => wp_kses_post( $values['post_form_description'] ?? $current['post_form_description'] ?? '' ),
		];
	}

	protected function sanitize_post_settings( array $values, array $current ) {
		$allowed_statuses = [ 'pending', 'draft', 'publish' ];
		$post_status      = sanitize_text_field( $values['post_status_default'] ?? $current['post_status_default'] ?? 'pending' );
		$post_status      = in_array( $post_status, $allowed_statuses, true ) ? $post_status : 'pending';
		$mime_types       = $this->normalize_list_input( $values['allowed_mime_types'] ?? ( $current['allowed_mime_types'] ?? [] ) );

		return [
			'post_monthly_limit'       => absint( $values['post_monthly_limit'] ?? $current['post_monthly_limit'] ?? 5 ),
			'post_limit_message'       => sanitize_text_field( $values['post_limit_message'] ?? $current['post_limit_message'] ?? '' ),
			'post_auto_approve'        => ! empty( $values['post_auto_approve'] ) ? 1 : 0,
			'post_status_default'      => $post_status,
			'allowed_mime_types'       => ! empty( $mime_types ) ? $mime_types : [ 'image/jpeg', 'image/png', 'image/gif' ],
			'seo_title_min_length'     => max( 10, absint( $values['seo_title_min_length'] ?? $current['seo_title_min_length'] ?? 50 ) ),
			'seo_meta_desc_min_length' => max( 50, absint( $values['seo_meta_desc_min_length'] ?? $current['seo_meta_desc_min_length'] ?? 150 ) ),
			'max_tags_per_post'        => max( 1, absint( $values['max_tags_per_post'] ?? $current['max_tags_per_post'] ?? 5 ) ),
			'auto_focus_keyword_from_first_tag' => ! empty( $values['auto_focus_keyword_from_first_tag'] ) ? 1 : 0,
		];
	}

	protected function sanitize_email_settings( array $values, array $current ) {
		$templates = isset( $values['email_templates'] ) && is_array( $values['email_templates'] )
			? $values['email_templates']
			: ( $current['email_templates'] ?? [] );

		$sanitized = [];
		foreach ( $templates as $template_key => $template ) {
			$template_key = sanitize_key( $template_key );
			if ( empty( $template_key ) ) {
				continue;
			}

			$sanitized[ $template_key ] = [
				'enabled' => ! empty( $template['enabled'] ) ? 1 : 0,
				'subject' => sanitize_text_field( $template['subject'] ?? '' ),
				'body'    => wp_kses_post( $template['body'] ?? '' ),
			];
		}

		return [
			'email_templates' => $sanitized,
		];
	}

	protected function save_keywords_settings( array $values ) {
		$keywords = $this->normalize_list_input( $values['keywords'] ?? $values['items'] ?? [] );
		Keyword_Manager::bulk_update_keywords( $keywords );
	}

	protected function sanitize_field_group( $fields ) {
		if ( ! is_array( $fields ) ) {
			return [];
		}

		$allowed_types  = [ 'tab', 'text', 'email', 'password', 'tel', 'url', 'textarea', 'wysiwyg', 'select', 'number', 'date', 'category', 'media' ];
		$allowed_widths = [ '25', '33', '50', '66', '75', '100' ];
		$result         = [];

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$type  = sanitize_text_field( $field['type'] ?? 'text' );
			$type  = in_array( $type, $allowed_types, true ) ? $type : 'text';
			$label = sanitize_text_field( $field['label'] ?? '' );
			$id    = sanitize_title( $field['id'] ?? $label );

			if ( empty( $id ) ) {
				continue;
			}

			if ( 'tab' !== $type && '' === $label ) {
				continue;
			}

			$item = [
				'id'         => $id,
				'label'      => $label,
				'type'       => $type,
				'required'   => ! empty( $field['required'] ) ? 1 : 0,
				'is_builtin' => ! empty( $field['is_builtin'] ) ? 1 : 0,
				'width'      => in_array( (string) ( $field['width'] ?? '100' ), $allowed_widths, true ) ? (string) $field['width'] : '100',
			];

			if ( isset( $field['description'] ) ) {
				$item['description'] = sanitize_textarea_field( $field['description'] );
			}

			$meta_key = sanitize_text_field( $field['meta_key'] ?? '' );
			if ( '' !== $meta_key ) {
				$item['meta_key'] = $meta_key;
			}

			if ( isset( $field['min_limit'] ) && '' !== $field['min_limit'] ) {
				$item['min_limit'] = absint( $field['min_limit'] );
			}

			if ( isset( $field['max_limit'] ) && '' !== $field['max_limit'] ) {
				$item['max_limit'] = absint( $field['max_limit'] );
			}

			if ( 'select' === $type ) {
				$options = [];
				$raw     = $field['options_raw'] ?? $field['options'] ?? [];
				if ( is_string( $raw ) ) {
					$lines = preg_split( '/\r\n|\r|\n/', $raw );
					foreach ( $lines as $line ) {
						$line = trim( (string) $line );
						if ( '' === $line ) {
							continue;
						}

						if ( false !== strpos( $line, ':' ) ) {
							list( $option_value, $option_label ) = array_map( 'trim', explode( ':', $line, 2 ) );
						} else {
							$option_value = $line;
							$option_label = $line;
						}

						if ( '' !== $option_value ) {
							$options[ sanitize_text_field( $option_value ) ] = sanitize_text_field( $option_label );
						}
					}
				} elseif ( is_array( $raw ) ) {
					foreach ( $raw as $option_value => $option_label ) {
						if ( is_array( $option_label ) ) {
							$option_value = $option_label['value'] ?? $option_value;
							$option_label = $option_label['label'] ?? $option_value;
						}

						$option_value = sanitize_text_field( (string) $option_value );
						$option_label = sanitize_text_field( (string) $option_label );
						if ( '' !== $option_value ) {
							$options[ $option_value ] = $option_label;
						}
					}
				}

				$item['options'] = $options;
			}

			$result[] = $item;
		}

		return array_values( $result );
	}

	protected function normalize_list_input( $value ) {
		$items = [];

		if ( is_string( $value ) ) {
			$items = preg_split( '/[\r\n,]+/', $value );
		} elseif ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( is_array( $item ) && isset( $item['keyword'] ) ) {
					$items[] = $item['keyword'];
				} else {
					$items[] = $item;
				}
			}
		}

		$items = array_map( 'sanitize_text_field', array_map( 'trim', $items ) );
		$items = array_values( array_unique( array_filter( $items, static function ( $item ) {
			return '' !== $item;
		} ) ) );

		return $items;
	}

	protected function get_available_roles() {
		$roles  = [];
		$wp_roles = wp_roles();

		foreach ( (array) $wp_roles->roles as $role_key => $role ) {
			$roles[] = [
				'value' => $role_key,
				'label' => translate_user_role( $role['name'] ),
			];
		}

		return $roles;
	}

	protected function get_page_assignments() {
		$fields = [
			'dashboard_page_id'       => [ 'label' => __( 'Dashboard', 'oswp-posts' ), 'route' => home_url( '/' . Portal_Page::SLUG . '/dashboard' ) ],
			'login_page_id'           => [ 'label' => __( 'Login', 'oswp-posts' ), 'route' => home_url( '/' . Portal_Page::SLUG . '/login' ) ],
			'registration_page_id'    => [ 'label' => __( 'Registration', 'oswp-posts' ), 'route' => home_url( '/' . Portal_Page::SLUG . '/register' ) ],
			'verification_page_id'    => [ 'label' => __( 'Verification', 'oswp-posts' ), 'route' => home_url( '/' . Portal_Page::SLUG . '/verify' ) ],
			'forgot_password_page_id' => [ 'label' => __( 'Forgot password', 'oswp-posts' ), 'route' => home_url( '/' . Portal_Page::SLUG . '/forgot-password' ) ],
			'reset_password_page_id'  => [ 'label' => __( 'Reset password', 'oswp-posts' ), 'route' => home_url( '/' . Portal_Page::SLUG . '/reset-password' ) ],
			'logout_page_id'          => [ 'label' => __( 'Logout', 'oswp-posts' ), 'route' => home_url( '/' . Portal_Page::SLUG . '/login' ) ],
		];

		$result = [];
		foreach ( $fields as $key => $meta ) {
			$page_id = absint( $this->settings->get( $key, 0 ) );
			$page    = $page_id ? get_post( $page_id ) : null;
			$result[] = [
				'key'          => $key,
				'label'        => $meta['label'],
				'page_id'      => $page_id,
				'page_title'   => $page ? get_the_title( $page_id ) : '',
				'page_edit_url'=> $page ? get_edit_post_link( $page_id, 'raw' ) : '',
				'legacy_url'   => $page ? get_permalink( $page_id ) : '',
				'portal_url'   => $meta['route'],
			];
		}

		return $result;
	}
}
