<?php
/**
 * Posts REST controller.
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Api;

use OSWP\Posts\Content\Html_Sanitizer;
use OSWP\Posts\Content\Keyword_Manager;
use OSWP\Posts\Core\Service_Container;
use OSWP\Posts\Settings\Settings_Repository;
use OSWP\Posts\Auth\Email_Verification;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Posts_Controller {

	const NAMESPACE = 'oswp/v1';

	protected $container;
	protected $settings;

	public function __construct( Service_Container $container ) {
		$this->container = $container;
		$this->settings  = $container->get( 'settings' );
	}

	public function register_routes() {
		register_rest_route( self::NAMESPACE, '/posts', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'list_posts' ],
				'permission_callback' => 'is_user_logged_in',
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_post' ],
				'permission_callback' => 'is_user_logged_in',
			],
		] );

		register_rest_route( self::NAMESPACE, '/posts/(?P<id>\d+)', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_post' ],
				'permission_callback' => 'is_user_logged_in',
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_post' ],
				'permission_callback' => 'is_user_logged_in',
			],
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'delete_post' ],
				'permission_callback' => 'is_user_logged_in',
			],
		] );
	}

	/**
	 * List user posts.
	 */
	public function list_posts( WP_REST_Request $request ) {
		$user     = wp_get_current_user();
		$page     = absint( $request->get_param( 'page' ) ) ?: 1;
		$per_page = absint( $request->get_param( 'per_page' ) ) ?: 10;
		$per_page = min( $per_page, 50 );

		$args = [
			'author'         => $user->ID,
			'post_type'      => 'post',
			'post_status'    => [ 'publish', 'pending', 'draft' ],
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		$query = new \WP_Query( $args );
		$posts = [];

		foreach ( $query->posts as $p ) {
			$thumb_id = get_post_thumbnail_id( $p->ID );
			$posts[] = [
				'id'            => $p->ID,
				'title'         => $p->post_title,
				'status'        => $p->post_status,
				'date'          => get_the_date( 'M j, Y', $p ),
				'thumbnail_url' => $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '',
			];
		}

		$response = [
			'posts' => $posts,
			'total' => $query->found_posts,
			'pages' => $query->max_num_pages,
		];

		// Include stats if requested
		if ( $request->get_param( 'stats' ) ) {
			$all_posts = get_posts( [
				'author'         => $user->ID,
				'post_type'      => 'post',
				'post_status'    => [ 'publish', 'pending', 'draft' ],
				'posts_per_page' => -1,
				'fields'         => 'ids',
			] );

			$published = 0;
			$pending   = 0;
			$draft_count = 0;

			foreach ( $all_posts as $pid ) {
				$status = get_post_status( $pid );
				if ( 'publish' === $status ) {
					$published++;
				} elseif ( 'pending' === $status ) {
					$pending++;
				} else {
					$draft_count++;
				}
			}

			$monthly_limit = absint( $this->settings->get( 'post_monthly_limit', 5 ) );
			$used          = $this->get_user_posts_this_month( $user->ID );

			$response['stats'] = [
				'total'     => count( $all_posts ),
				'published' => $published,
				'pending'   => $pending,
				'draft'     => $draft_count,
				'limit'     => $monthly_limit,
				'used'      => $used,
			];
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Get single post.
	 */
	public function get_post( WP_REST_Request $request ) {
		$post_id = absint( $request['id'] );
		$user    = wp_get_current_user();
		$post    = get_post( $post_id );

		if ( ! $post || (int) $post->post_author !== $user->ID ) {
			return new WP_Error( 'not_found', __( 'Post not found.', 'oswp-posts' ), [ 'status' => 404 ] );
		}

		$fields = $this->settings->get( 'post_fields', [] );
		$data   = [
			'id'            => $post->ID,
			'post_title'    => $post->post_title,
			'post_content'  => $post->post_content,
			'post_status'   => $post->post_status,
			'post_category' => '',
			'post_tags'     => '',
			'thumbnail_url' => '',
			'meta'          => [],
		];

		$cats = wp_get_post_categories( $post->ID );
		if ( ! empty( $cats ) ) {
			$data['post_category'] = (string) $cats[0];
		}

		$tags = wp_get_post_tags( $post->ID, [ 'fields' => 'names' ] );
		$data['post_tags'] = implode( ', ', $tags );

		$thumb_id = get_post_thumbnail_id( $post->ID );
		if ( $thumb_id ) {
			$data['thumbnail_url'] = wp_get_attachment_image_url( $thumb_id, 'medium' );
		}

		foreach ( $fields as $field ) {
			if ( 'tab' === $field['type'] || ( $field['is_builtin'] ?? false ) ) {
				continue;
			}
			$meta_key = $field['meta_key'] ?? $field['id'];
			$data['meta'][ $field['id'] ] = get_post_meta( $post->ID, $meta_key, true );
		}

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Create post.
	 */
	public function create_post( WP_REST_Request $request ) {
		$user   = wp_get_current_user();
		$fields = $this->settings->get( 'post_fields', [] );

		// Check permissions
		$error = $this->check_post_permissions( $user );
		if ( $error ) {
			return $error;
		}

		// Gather and validate data
		$data = $this->extract_post_data( $request, $fields );
		$validation = $this->validate_post_data( $data, $fields );
		if ( $validation ) {
			return $validation;
		}

		$post_status = $this->settings->get( 'post_auto_approve', true )
			? 'publish'
			: (string) $this->settings->get( 'post_status_default', 'pending' );

		$post_arr = [
			'post_title'   => $data['post_title'],
			'post_content' => $data['post_content'] ?? '',
			'post_status'  => $post_status,
			'post_author'  => $user->ID,
			'post_type'    => 'post',
		];

		if ( ! empty( $data['post_category'] ) ) {
			$post_arr['post_category'] = [ absint( $data['post_category'] ) ];
		}

		$post_id = wp_insert_post( $post_arr, true );

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error( 'create_failed', $post_id->get_error_message(), [ 'status' => 500 ] );
		}

		// Tags
		if ( ! empty( $data['post_tags'] ) ) {
			wp_set_post_tags( $post_id, $data['post_tags'] );
		}

		// Handle file upload
		$this->handle_thumbnail_upload( $request, $post_id );

		// Save custom meta
		$this->save_post_meta( $post_id, $data, $fields );

		return new WP_REST_Response( [
			'id'      => $post_id,
			'message' => __( 'Post created.', 'oswp-posts' ),
		], 201 );
	}

	/**
	 * Update post.
	 */
	public function update_post( WP_REST_Request $request ) {
		$post_id = absint( $request['id'] );
		$user    = wp_get_current_user();
		$post    = get_post( $post_id );

		if ( ! $post || (int) $post->post_author !== $user->ID ) {
			return new WP_Error( 'not_found', __( 'Post not found.', 'oswp-posts' ), [ 'status' => 404 ] );
		}

		if ( ! in_array( $post->post_status, [ 'pending', 'draft' ], true ) ) {
			return new WP_Error( 'not_editable', __( 'Only pending or draft posts can be edited.', 'oswp-posts' ), [ 'status' => 403 ] );
		}

		$fields = $this->settings->get( 'post_fields', [] );
		$data   = $this->extract_post_data( $request, $fields );
		$validation = $this->validate_post_data( $data, $fields );
		if ( $validation ) {
			return $validation;
		}

		$post_arr = [ 'ID' => $post_id ];
		if ( isset( $data['post_title'] ) ) {
			$post_arr['post_title'] = $data['post_title'];
		}
		if ( isset( $data['post_content'] ) ) {
			$post_arr['post_content'] = $data['post_content'];
		}
		if ( ! empty( $data['post_category'] ) ) {
			$post_arr['post_category'] = [ absint( $data['post_category'] ) ];
		}

		$result = wp_update_post( $post_arr, true );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'update_failed', $result->get_error_message(), [ 'status' => 500 ] );
		}

		if ( ! empty( $data['post_tags'] ) ) {
			wp_set_post_tags( $post_id, $data['post_tags'] );
		}

		$this->handle_thumbnail_upload( $request, $post_id );
		$this->save_post_meta( $post_id, $data, $fields );

		return new WP_REST_Response( [ 'message' => __( 'Post updated.', 'oswp-posts' ) ], 200 );
	}

	/**
	 * Delete post.
	 */
	public function delete_post( WP_REST_Request $request ) {
		$post_id = absint( $request['id'] );
		$user    = wp_get_current_user();
		$post    = get_post( $post_id );

		if ( ! $post || (int) $post->post_author !== $user->ID ) {
			return new WP_Error( 'not_found', __( 'Post not found.', 'oswp-posts' ), [ 'status' => 404 ] );
		}

		wp_delete_post( $post_id, true );

		return new WP_REST_Response( [ 'message' => __( 'Post deleted.', 'oswp-posts' ) ], 200 );
	}

	// ---- Helpers ----

	protected function check_post_permissions( $user ) {
		$verification = $this->container->get( 'module.auth_verify' );
		$method       = $this->settings->get( 'email_verification_method', 'otp' );

		if ( $verification && 'none' !== $method ) {
			if ( $this->settings->get( 'verification_required', true ) && ! $verification->is_verified( $user->ID ) ) {
				return new WP_Error( 'not_verified', __( 'Please verify your email first.', 'oswp-posts' ), [ 'status' => 403 ] );
			}

			if ( ! $verification->is_approved_to_post( $user->ID ) ) {
				return new WP_Error( 'not_approved', __( 'You are not approved to submit posts.', 'oswp-posts' ), [ 'status' => 403 ] );
			}
		}

		// Monthly limit
		$limit = absint( $this->settings->get( 'post_monthly_limit', 5 ) );
		if ( $limit > 0 ) {
			$used = $this->get_user_posts_this_month( $user->ID );
			if ( $used >= $limit ) {
				$msg = $this->settings->get( 'post_limit_message', __( 'You have reached your post limit for this period.', 'oswp-posts' ) );
				return new WP_Error( 'limit_reached', $msg, [ 'status' => 403 ] );
			}
		}

		return null;
	}

	protected function extract_post_data( WP_REST_Request $request, array $fields ) {
		$data = [];
		foreach ( $fields as $field ) {
			if ( 'tab' === $field['type'] || 'media' === $field['type'] ) {
				continue;
			}

			$val = $request->get_param( $field['id'] );
			if ( null === $val ) {
				continue;
			}

			switch ( $field['type'] ) {
				case 'wysiwyg':
					$data[ $field['id'] ] = Html_Sanitizer::sanitize_fragment( $val );
					break;
				case 'textarea':
					$data[ $field['id'] ] = sanitize_textarea_field( $val );
					break;
				default:
					$data[ $field['id'] ] = sanitize_text_field( $val );
					break;
			}
		}
		return $data;
	}

	protected function validate_post_data( array $data, array $fields ) {
		$errors = [];

		foreach ( $fields as $field ) {
			if ( 'tab' === $field['type'] || 'media' === $field['type'] ) {
				continue;
			}

			$field_id = $field['id'];
			$value    = $data[ $field_id ] ?? '';
			$label    = $field['label'] ?? $field_id;

			if ( ! empty( $field['required'] ) && '' === trim( (string) $value ) ) {
				$errors[ $field_id ] = sprintf( __( '%s is required.', 'oswp-posts' ), $label );
				continue;
			}

			if ( '' === (string) $value ) {
				continue;
			}

			$length = mb_strlen( wp_strip_all_tags( (string) $value ) );
			$min    = isset( $field['min_limit'] ) ? absint( $field['min_limit'] ) : 0;
			$max    = isset( $field['max_limit'] ) ? absint( $field['max_limit'] ) : 0;

			if ( $min > 0 && $length < $min ) {
				$errors[ $field_id ] = sprintf( __( '%1$s must be at least %2$d characters.', 'oswp-posts' ), $label, $min );
				continue;
			}

			if ( $max > 0 && $length > $max ) {
				$errors[ $field_id ] = sprintf( __( '%1$s must not exceed %2$d characters.', 'oswp-posts' ), $label, $max );
			}

                        // WYSIWYG hyperlink rules
                        if ( 'wysiwyg' === $field['type'] && '' !== trim( $value ) ) {
                                $anchor_count = 0;
                                if ( preg_match_all( '/<a\b[^>]*>/i', $value, $anchor_matches ) ) {
                                        $anchor_count = count( $anchor_matches[0] );
                                }

                                if ( $anchor_count > 1 ) {
                                        $errors[ $field_id ] = __( 'Only one hyperlink is allowed in full content.', 'oswp-posts' );
                                        continue;
                                }

                                if ( preg_match( '/<p\b[^>]*>([\s\S]*?)<\/p>/i', $value, $first_paragraph ) && preg_match( '/<a\b[^>]*>/i', $first_paragraph[1] ) ) {
                                        $errors[ $field_id ] = __( 'The first paragraph cannot contain a hyperlink.', 'oswp-posts' );
                                        continue;
                                }

                                $dom = new \DOMDocument();
                                libxml_use_internal_errors( true );
                                $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $value );
                                libxml_clear_errors();
                                $xpath = new \DOMXPath( $dom );

                                $anchors = $xpath->query( '//a' );
                                foreach ( $anchors as $anchor ) {
                                        $parent = $anchor->parentNode;
                                        $inside_p = false;
                                        while ( $parent ) {
                                                if ( 'p' === strtolower( $parent->nodeName ) ) {
                                                        $inside_p = true;
                                                        break;
                                                }
                                                $parent = $parent->parentNode;
                                        }

                                        if ( ! $inside_p ) {
                                                $errors[ $field_id ] = __( 'Hyperlinks must be inside a paragraph element.', 'oswp-posts' );
                                                continue 2;
                                        }
                                }
                        }

		}

		$title = (string) ( $data['post_title'] ?? '' );
		if ( '' !== $title ) {
			$title_min = max( 1, absint( $this->settings->get( 'seo_title_min_length', 50 ) ) );
			$title_len = mb_strlen( wp_strip_all_tags( $title ) );
			if ( $title_len < $title_min && empty( $errors['post_title'] ) ) {
				$errors['post_title'] = sprintf( __( 'Post Title must be at least %d characters.', 'oswp-posts' ), $title_min );
			}
		}

		$meta_desc = (string) ( $data['_yoast_wpseo_metadesc'] ?? '' );
		if ( '' !== $meta_desc ) {
			$meta_min = max( 1, absint( $this->settings->get( 'seo_meta_desc_min_length', 150 ) ) );
			$meta_len = mb_strlen( wp_strip_all_tags( $meta_desc ) );
			if ( $meta_len < $meta_min && empty( $errors['_yoast_wpseo_metadesc'] ) ) {
				$errors['_yoast_wpseo_metadesc'] = sprintf( __( 'Meta Description must be at least %d characters.', 'oswp-posts' ), $meta_min );
			}
		}

		$tags = $this->normalize_tags( $data['post_tags'] ?? '' );
		$max_tags = max( 1, absint( $this->settings->get( 'max_tags_per_post', 5 ) ) );
		if ( count( $tags ) > $max_tags ) {
			$errors['post_tags'] = sprintf( __( 'You can add a maximum of %d tags.', 'oswp-posts' ), $max_tags );
		}

		$blocked = $this->get_blocked_keyword_errors( $data );
		$errors  = array_merge( $errors, $blocked );

		if ( ! empty( $errors ) ) {
			return new WP_Error(
				'validation',
				__( 'Please fix the highlighted fields and try again.', 'oswp-posts' ),
				[
					'status'  => 422,
					'details' => $errors,
				]
			);
		}

		return null;
	}

	protected function handle_thumbnail_upload( WP_REST_Request $request, $post_id ) {
		$files = $request->get_file_params();
		if ( empty( $files['post_thumbnail'] ) ) {
			return;
		}

		$file = $files['post_thumbnail'];

		// Validate mime type
		$allowed = $this->settings->get( 'allowed_mime_types', [ 'image/jpeg', 'image/png', 'image/gif' ] );
		if ( ! in_array( $file['type'], $allowed, true ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload( 'post_thumbnail', $post_id );
		if ( ! is_wp_error( $attachment_id ) ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
	}

	protected function save_post_meta( $post_id, array $data, array $fields ) {
		$focus_keyword_missing = ! isset( $data['_yoast_wpseo_focuskw'] ) || '' === trim( (string) $data['_yoast_wpseo_focuskw'] );

		if ( $this->settings->get( 'auto_focus_keyword_from_first_tag', true ) && $focus_keyword_missing ) {
			$tags = $this->normalize_tags( $data['post_tags'] ?? '' );
			if ( ! empty( $tags ) ) {
				$data['_yoast_wpseo_focuskw'] = reset( $tags );
				update_post_meta( $post_id, '_yoast_wpseo_focuskw', reset( $tags ) );
			}
		}

		foreach ( $fields as $field ) {
			if ( 'tab' === $field['type'] || ( $field['is_builtin'] ?? false ) ) {
				continue;
			}
			$meta_key = $field['meta_key'] ?? $field['id'];
			if ( isset( $data[ $field['id'] ] ) ) {
				update_post_meta( $post_id, $meta_key, $data[ $field['id'] ] );
			}
		}
	}

	protected function get_user_posts_this_month( $user_id ) {
		$start = gmdate( 'Y-m-01 00:00:00' );
		$end   = gmdate( 'Y-m-t 23:59:59' );

		$query = new \WP_Query( [
			'author'         => $user_id,
			'post_type'      => 'post',
			'post_status'    => [ 'publish', 'pending', 'draft' ],
			'date_query'     => [ [
				'after'     => $start,
				'before'    => $end,
				'inclusive' => true,
			] ],
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		return $query->found_posts;
	}

	protected function normalize_tags( $tags_value ) {
		if ( is_array( $tags_value ) ) {
			$tags = $tags_value;
		} else {
			$tags = explode( ',', (string) $tags_value );
		}

		$tags = array_map( 'trim', $tags );
		$tags = array_values( array_filter( array_unique( $tags ), static function ( $tag ) {
			return '' !== $tag;
		} ) );

		return $tags;
	}

	protected function get_blocked_keyword_errors( array $data ) {
		$errors = [];
		$map    = [
			'post_title'            => __( 'Post Title', 'oswp-posts' ),
			'post_content'          => __( 'Post Content', 'oswp-posts' ),
			'_yoast_wpseo_metadesc' => __( 'Meta Description', 'oswp-posts' ),
			'_yoast_wpseo_focuskw'  => __( 'Focus Keyword', 'oswp-posts' ),
		];

		foreach ( $map as $field_id => $label ) {
			$value = isset( $data[ $field_id ] ) ? wp_strip_all_tags( (string) $data[ $field_id ] ) : '';
			if ( '' === $value ) {
				continue;
			}

			$found = Keyword_Manager::find_blocked_keywords( $value );
			if ( ! empty( $found ) ) {
				$errors[ $field_id ] = sprintf(
					__( '%1$s contains blocked keywords: %2$s.', 'oswp-posts' ),
					$label,
					implode( ', ', array_slice( array_map( 'sanitize_text_field', $found ), 0, 5 ) )
				);
			}
		}

		return $errors;
	}
}
