<?php
/**
 * Settings REST controller (public).
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Api;

use OSWP\Posts\Content\Keyword_Manager;
use OSWP\Posts\Core\Service_Container;
use OSWP\Posts\Settings\Settings_Repository;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings_Controller {

	const NAMESPACE = 'oswp/v1';

	protected $container;
	protected $settings;

	public function __construct( Service_Container $container ) {
		$this->container = $container;
		$this->settings  = $container->get( 'settings' );
	}

	public function register_routes() {
		register_rest_route( self::NAMESPACE, '/settings/public', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_public' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NAMESPACE, '/categories', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_categories' ],
			'permission_callback' => '__return_true',
		] );
	}

	/**
	 * Return public-safe settings for form rendering.
	 */
	public function get_public( WP_REST_Request $request ) {
		$reg_fields  = $this->settings->get( 'registration_fields', [] );
		$post_fields = $this->settings->get( 'post_fields', [] );

		// Strip sensitive info — only send what the frontend needs
		$safe_reg = array_map( function ( $f ) {
			$field = [
				'id'       => $f['id'],
				'label'    => $f['label'],
				'type'     => $f['type'],
				'required' => ! empty( $f['required'] ),
				'width'    => $f['width'] ?? '100',
				'description' => $f['description'] ?? '',
			];

			if ( 'select' === ( $f['type'] ?? '' ) ) {
				$field['options'] = isset( $f['options'] ) && is_array( $f['options'] ) ? $f['options'] : [];
			}

			return $field;
		}, $reg_fields );

		$safe_post = array_map( function ( $f ) {
			$field = [
				'id'        => $f['id'],
				'label'     => $f['label'],
				'type'      => $f['type'],
				'required'  => ! empty( $f['required'] ),
				'width'     => $f['width'] ?? '100',
				'description' => $f['description'] ?? '',
				'min_limit' => $f['min_limit'] ?? null,
				'max_limit' => $f['max_limit'] ?? null,
			];

			if ( 'select' === ( $f['type'] ?? '' ) ) {
				$field['options'] = isset( $f['options'] ) && is_array( $f['options'] ) ? $f['options'] : [];
			}

			return $field;
		}, $post_fields );

		return new WP_REST_Response( [
			'registration_fields'       => $safe_reg,
			'post_fields'               => $safe_post,
			'post_rules'                => [
				'seo_title_min_length'     => (int) $this->settings->get( 'seo_title_min_length', 50 ),
				'seo_meta_desc_min_length' => (int) $this->settings->get( 'seo_meta_desc_min_length', 150 ),
				'max_tags_per_post'        => (int) $this->settings->get( 'max_tags_per_post', 5 ),
				'auto_focus_keyword'       => (bool) $this->settings->get( 'auto_focus_keyword_from_first_tag', true ),
				'content_min_words'        => max( 1, absint( $this->settings->get( 'post_content_min_words', 800 ) ) ),
				'content_max_words'        => max( 1, absint( $this->settings->get( 'post_content_max_words', 2000 ) ) ),
				'blocked_keywords'         => Keyword_Manager::get_blocked_keywords(),
			],
			'email_verification_method' => $this->settings->get( 'email_verification_method', 'otp' ),
			'post_form_description'     => $this->settings->get( 'post_form_description', '' ),
		], 200 );
	}

	/**
	 * Get categories list.
	 */
	public function get_categories( WP_REST_Request $request ) {
		$terms = get_terms( [
			'taxonomy'   => 'category',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $terms ) ) {
			return new WP_REST_Response( [], 200 );
		}

		$result = [];
		foreach ( $terms as $term ) {
			$result[] = [
				'id'   => $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
			];
		}

		return new WP_REST_Response( $result, 200 );
	}
}
