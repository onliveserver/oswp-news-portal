<?php
/**
 * Handles user post submissions and management.
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Posts;

use OSWP\Posts\Content\Html_Sanitizer;
use OSWP\Posts\Core\Service_Container;
use OSWP\Posts\Settings\Settings_Repository;
use OSWP\Posts\Content\Keyword_Manager;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages post submissions from frontend users.
 */
class Post_Manager {
	/**
	 * Container.
	 *
	 * @var Service_Container
	 */
	protected $container;

	/**
	 * Settings repository.
	 *
	 * @var Settings_Repository
	 */
	protected $settings;

	/**
	 * View loader.
	 *
	 * @var \OSWP\Posts\Core\Template_Loader
	 */
	protected $view;

	/**
	 * Collected errors.
	 *
	 * @var WP_Error
	 */
	protected $errors;

	/**
	 * Constructor.
	 *
	 * @param Service_Container $container Container.
	 */
	public function __construct( Service_Container $container ) {
		$this->container = $container;
		$this->settings  = $container->get( 'settings' );
		$this->view      = $container->get( 'view' );
		$this->errors    = new WP_Error();
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', [ $this, 'maybe_handle_submission' ] );
	}

	/**
	 * Process post submission.
	 */
	public function maybe_handle_submission() {
		if ( empty( $_POST['oswp_action'] ) || 'submit_post' !== sanitize_key( wp_unslash( $_POST['oswp_action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		check_admin_referer( 'oswp_post_action', 'oswp_post_nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_safe_redirect( add_query_arg( 'oswp_message', 'not_logged_in' ) );
			exit;
		}

		// Check if editing existing post
		$editing = false;
		$existing_post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $existing_post_id ) {
			$existing_post = get_post( $existing_post_id );
			if ( $existing_post && $existing_post->post_author == $user_id ) {
				// Prevent editing of published/approved posts
				if ( 'publish' === $existing_post->post_status ) {
					wp_safe_redirect( add_query_arg( 'oswp_message', 'cannot_edit_approved' ) );
					exit;
				}
				$editing = true;
			} else {
				wp_safe_redirect( add_query_arg( 'oswp_message', 'permission_denied' ) );
				exit;
			}
		}

		// Check if user is verified
		$verification = $this->container->get( 'module.auth_verify' );
		if ( $verification && 'none' !== $this->settings->get( 'email_verification_method', 'link' ) ) {
			if ( ! $verification->is_verified( $user_id ) ) {
				wp_safe_redirect( add_query_arg( 'oswp_message', 'not_verified' ) );
				exit;
			}
		}

		// Check user approval status
		if ( $verification && ! $verification->is_approved_to_post( $user_id ) ) {
			wp_safe_redirect( add_query_arg( 'oswp_message', 'not_approved' ) );
			exit;
		}

		// Check monthly limit (only for new posts)
		if ( ! $editing ) {
			$monthly_limit = $this->settings->get( 'post_monthly_limit', 0 );
			if ( $monthly_limit ) {
				$posts_this_month = $this->get_user_posts_this_month( $user_id );
				if ( $posts_this_month >= $monthly_limit ) {
					wp_safe_redirect( add_query_arg( 'oswp_message', 'limit_exceeded' ) );
					exit;
				}
			}
		}

		// Sanitize and validate input
		$data = $this->sanitize_post_data( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		
		// Handle file uploads for non-admin users (direct file inputs)
		$this->process_file_uploads( $data );
		
		$this->validate_post_data( $data );

		if ( $this->errors->has_errors() ) {
			return;
		}

		// Create or update post
		if ( $editing ) {
			$post_id = $this->update_post( $existing_post_id, $data, $user_id );
			$message = 'post_updated';
		} else {
			$post_id = $this->create_post( $data, $user_id );
			$message = 'post_published';
		}

		if ( is_wp_error( $post_id ) ) {
			$this->errors = $post_id;
			return;
		}

		wp_safe_redirect( add_query_arg( [ 'section' => 'posts', 'oswp_message' => $message ], remove_query_arg( [ 'action', 'post_id' ] ) ) );
		exit;
	}

	/**
	 * Render post form.
	 *
	 * @param int $post_id Post ID (0 for new post).
	 * @return string
	 */
	public function render_post_form( $post_id = 0 ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return '<p>' . esc_html__( 'Please log in to submit a post.', 'oswp-posts' ) . '</p>';
		}

		// Check if editing existing post
		$editing = false;
		$existing_post = null;
		if ( $post_id ) {
			$existing_post = get_post( $post_id );
			if ( $existing_post && $existing_post->post_author == $user_id ) {
				$editing = true;
			} else {
				return '<p>' . esc_html__( 'You do not have permission to edit this post.', 'oswp-posts' ) . '</p>';
			}
		}

		// Check if user is verified
		$verification = $this->container->get( 'module.auth_verify' );
		if ( $verification && 'none' !== $this->settings->get( 'email_verification_method', 'link' ) ) {
			if ( ! $verification->is_verified( $user_id ) ) {
				return '<p style="color: #d32f2f;">' . esc_html__( 'Please verify your email address to submit posts.', 'oswp-posts' ) . '</p>';
			}
		}

		// Check user approval
		if ( $verification && ! $verification->is_approved_to_post( $user_id ) ) {
			return '<p style="color: #d32f2f;">' . esc_html__( 'You are not approved to submit posts yet. Please contact the administrator.', 'oswp-posts' ) . '</p>';
		}

		// Check monthly limit (only for new posts)
		$monthly_limit     = 0;
		$posts_this_month  = 0;
		if ( ! $editing ) {
			$monthly_limit = $this->settings->get( 'post_monthly_limit', 0 );
			$posts_this_month = $this->get_user_posts_this_month( $user_id );

			if ( $monthly_limit && $posts_this_month >= $monthly_limit ) {
				$message = $this->settings->get( 'post_limit_message', '' );
				if ( ! $message ) {
					$message = sprintf( __( 'You have reached your monthly limit of %d posts.', 'oswp-posts' ), $monthly_limit );
				}
				return '<p style="color: #d32f2f;">' . esc_html( $message ) . '</p>';
			}
		}

		ob_start();

		// Flatten all fields (strip tab separators) for single-column layout
		$fields = $this->settings->get( 'post_fields', [] );
		$flat_fields = [];
		foreach ( $fields as $field ) {
			if ( 'tab' !== $field['type'] ) {
				$flat_fields[] = $field;
			}
		}

		// Post form short description from settings
		$form_description = $this->settings->get( 'post_form_description', '' );
		?>
		<div class="oswp-post-form">
			<?php if ( $this->errors->has_errors() ) : ?>
				<?php 
				// Group errors by category for better organization
				$grouped_errors = $this->group_validation_errors( $this->errors );
				$this->render_validation_notice( $grouped_errors );
				?>
			<?php endif; ?>

			<?php if ( ! empty( $form_description ) ) : ?>
				<div class="oswp-post-form__description">
					<?php echo wp_kses_post( $form_description ); ?>
				</div>
			<?php endif; ?>

		<form method="post" class="oswp-form oswp-form--post" id="oswp-post-form" enctype="multipart/form-data">
				<?php wp_nonce_field( 'oswp_post_action', 'oswp_post_nonce' ); ?>
				<input type="hidden" name="oswp_action" value="submit_post">
				<?php if ( $editing ) : ?>
					<input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">
				<?php endif; ?>

				<div class="oswp-form__fields">
					<?php $this->render_form_fields( $flat_fields, $post_id, $editing, $existing_post ); ?>
				</div>

				<?php if ( ! $editing && $monthly_limit ) : ?>
					<p class="oswp-form__help">
						<?php printf( esc_html__( 'Posts submitted this month: %d / %d', 'oswp-posts' ), esc_html( $posts_this_month ), esc_html( $monthly_limit ) ); ?>
					</p>
				<?php endif; ?>

				<button type="submit" class="button button-primary oswp-form__submit"><?php echo $editing ? esc_html__( 'Update Post', 'oswp-posts' ) : esc_html__( 'Submit Post', 'oswp-posts' ); ?></button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render form fields.
	 *
	 * @param array    $fields        Fields to render.
	 * @param int      $post_id       Post ID if editing.
	 * @param bool     $editing       Whether editing existing post.
	 * @param \WP_Post $existing_post Existing post object.
	 */
	protected function render_form_fields( $fields, $post_id = 0, $editing = false, $existing_post = null ) {
		foreach ( $fields as $field ) {
			$id          = $field['id'];
			// hide excerpt and seo title from frontend form
			if ( 'post_excerpt' === $id || '_yoast_wpseo_title' === $id ) {
				continue;
			}
			$label       = $field['label'] ?? '';
			$type        = $field['type'] ?? 'text';
			$required    = ! empty( $field['required'] ) ? 'required' : '';
			$req_mark    = ! empty( $field['required'] ) ? ' <span class="oswp-form__required">*</span>' : '';
			$width       = $field['width'] ?? '100';
			$width_class = "oswp-form__group--" . $width;
			
			// Get value from existing post or POST data
			$value = '';
			if ( $editing && $existing_post ) {
				if ( 'post_title' === $id ) {
					$value = $existing_post->post_title;
				} elseif ( 'post_content' === $id ) {
					$value = $existing_post->post_content;
				} elseif ( 'post_category' === $id ) {
					$categories = wp_get_post_categories( $post_id, [ 'fields' => 'ids' ] );
					$value = ! empty( $categories ) ? $categories[0] : '';
				} elseif ( 'post_tags' === $id ) {
					$tags = wp_get_post_tags( $post_id, [ 'fields' => 'names' ] );
					$value = implode( ', ', $tags );
				} elseif ( 'post_thumbnail' === $id ) {
					$value = get_post_thumbnail_id( $post_id );
				} else {
					$meta_key = ! empty( $field['meta_key'] ) ? $field['meta_key'] : $id;
					$value = get_post_meta( $post_id, $meta_key, true );
				}
			} elseif ( isset( $_POST[ $id ] ) ) {
				$value = wp_unslash( $_POST[ $id ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
			?>
			
			<?php if ( 'wysiwyg' === $type || 'post_content' === $id ) : ?>
				<div class="oswp-form__group <?php echo esc_attr( $width_class ); ?>">
					<span class="oswp-form__label"><?php echo wp_kses( $label . $req_mark, [ 'span' => [ 'class' => [] ] ] ); ?></span>
					<?php 
					wp_editor( $value, $id, [
						'textarea_name' => $id,
						'media_buttons' => false,
						'textarea_rows' => ( 'post_content' === $id ) ? 18 : 10,
						'editor_css'    => '<style>.wp-editor-wrap{border:1px solid #e2e4e7;border-radius:6px;overflow:hidden;}</style>',
						'quicktags'     => true,
						'tinymce'       => [
							'toolbar1' => 'bold,italic,underline,bullist,numlist,link,unlink,blockquote,alignleft,aligncenter,alignright,undo,redo',
							'toolbar2' => '',
						],
					] ); 
					?>
					<?php if ( 'post_content' === $id ) : ?>
						<div class="oswp-form__word-count" id="oswp-word-count">
							<span class="oswp-word-count__text"><?php esc_html_e( 'Words:', 'oswp-posts' ); ?> <strong id="oswp-word-count-number">0</strong></span>
							<span class="oswp-word-count__range"><?php printf( esc_html__( '(min: %d, max: %d)', 'oswp-posts' ), 800, 2000 ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			<?php elseif ( 'media' === $type || 'post_thumbnail' === $id ) : ?>
				<div class="oswp-form__group <?php echo esc_attr( $width_class ); ?>">
					<span class="oswp-form__label"><?php echo wp_kses( $label . $req_mark, [ 'span' => [ 'class' => [] ] ] ); ?></span>
					
					<?php if ( current_user_can( 'manage_options' ) ) : ?>
						<!-- Admin: Media Library Dropzone -->
						<div class="oswp-media-upload" id="oswp-media-<?php echo esc_attr( $id ); ?>">
							<div class="oswp-media-upload__dropzone <?php echo $value ? 'oswp-media-upload__dropzone--has-image' : ''; ?>" id="dropzone-<?php echo esc_attr( $id ); ?>">
								<div class="oswp-media-upload__preview" id="preview-<?php echo esc_attr( $id ); ?>">
									<?php if ( $value ) : ?>
										<img src="<?php echo esc_url( wp_get_attachment_image_url( $value, 'medium' ) ); ?>" alt="" />
									<?php endif; ?>
								</div>
								<div class="oswp-media-upload__placeholder" <?php echo $value ? 'style="display:none;"' : ''; ?>>
									<span class="dashicons dashicons-format-image"></span>
									<p><?php esc_html_e( 'Click to upload featured image', 'oswp-posts' ); ?></p>
									<span class="oswp-media-upload__hint"><?php esc_html_e( 'Recommended: 1200 x 630px', 'oswp-posts' ); ?></span>
								</div>
							</div>
							<input type="hidden" name="<?php echo esc_attr( $id ); ?>" id="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php echo esc_attr( $required ); ?> />
							<div class="oswp-media-upload__actions">
								<button type="button" class="button button-secondary oswp-media-upload__btn">
									<?php esc_html_e( 'Choose Image', 'oswp-posts' ); ?>
								</button>
								<button type="button" class="button oswp-media-upload__remove" <?php echo ! $value ? 'style="display:none;"' : ''; ?>>
									<?php esc_html_e( 'Remove', 'oswp-posts' ); ?>
								</button>
							</div>
						</div>
					<?php else : ?>
						<!-- Non-Admin: Direct File Upload -->
						<div class="oswp-form__file-upload">
							<input 
								type="file" 
								name="<?php echo esc_attr( $id ); ?>_file" 
								id="<?php echo esc_attr( $id ); ?>_file" 
								accept="image/*" 
								class="oswp-form__file-input"
								<?php echo esc_attr( $required ); ?>
							/>
							<input type="hidden" name="<?php echo esc_attr( $id ); ?>" id="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>" />
							<p class="oswp-form__hint"><?php esc_html_e( 'Maximum file size: 5MB. Recommended: 1200 x 630px', 'oswp-posts' ); ?></p>
							<?php if ( $value ) : ?>
								<div class="oswp-form__file-preview">
									<img src="<?php echo esc_url( wp_get_attachment_image_url( $value, 'thumbnail' ) ); ?>" alt="" />
									<p><?php esc_html_e( 'Current image', 'oswp-posts' ); ?></p>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php elseif ( 'post_category' === $id ) : ?>
				<div class="oswp-form__group <?php echo esc_attr( $width_class ); ?>">
					<label for="<?php echo esc_attr( $id ); ?>" class="oswp-form__label"><?php echo wp_kses( $label . $req_mark, [ 'span' => [ 'class' => [] ] ] ); ?></label>
					<?php
					wp_dropdown_categories( [
						'show_option_none' => __( '— Select Category —', 'oswp-posts' ),
						'id'               => $id,
						'name'             => $id,
						'class'            => 'oswp-form__input oswp-form__select',
						'echo'             => true,
						'selected'         => $value,
					] );
					?>
				</div>
			<?php else : ?>
				<div class="oswp-form__group <?php echo esc_attr( $width_class ); ?>">
					<label for="<?php echo esc_attr( $id ); ?>" class="oswp-form__label"><?php echo wp_kses( $label . $req_mark, [ 'span' => [ 'class' => [] ] ] ); ?></label>
					
<?php if ( 'textarea' === $type || 'post_excerpt' === $id ) : ?>
<?php
$min_len = isset( $field['min_limit'] ) ? absint( $field['min_limit'] ) : 0;
$max_len = ! empty( $field['max_limit'] ) ? absint( $field['max_limit'] ) : 5000;
$attrs = $required;
if ( $min_len > 0 ) $attrs .= ' minlength="' . $min_len . '"';
if ( $max_len > 0 ) $attrs .= ' maxlength="' . $max_len . '"';
?>
<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" class="oswp-form__textarea" rows="3" <?php echo $attrs; ?>><?php echo esc_textarea( $value ); ?></textarea>
					
					<?php elseif ( 'select' === $type ) : ?>
						<select name="<?php echo esc_attr( $id ); ?>" id="<?php echo esc_attr( $id ); ?>" class="oswp-form__select" <?php echo esc_attr( $required ); ?>>
<?php else : ?>
<?php 
$default_max_map = [
'text'  => 255,
'email' => 254,
'tel'   => 20,
'url'   => 2048,
];
$min_len = isset( $field['min_limit'] ) ? absint( $field['min_limit'] ) : 0;
$max_len = ! empty( $field['max_limit'] ) ? absint( $field['max_limit'] ) : ( $default_max_map[ $type ] ?? 0 );
$attrs = $required;
if ( $min_len > 0 ) $attrs .= ' minlength="' . $min_len . '"';
if ( $max_len > 0 ) $attrs .= ' maxlength="' . $max_len . '"';
?>
<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" class="oswp-form__input" value="<?php echo esc_attr( $value ); ?>" <?php echo $attrs; ?> />
						<?php if ( 'post_title' === $id ) : ?>
							<p class="oswp-form__hint"><?php esc_html_e( 'Write a clear, descriptive title (10–80 characters).', 'oswp-posts' ); ?></p>
						<?php elseif ( 'post_tags' === $id ) : ?>
							<p class="oswp-form__hint"><?php esc_html_e( 'Separate tags with commas.', 'oswp-posts' ); ?></p>
						<?php elseif ( '_yoast_wpseo_title' === $id ) : ?>
							<p class="oswp-form__hint"><?php esc_html_e( 'Recommended: 50–60 characters for best SEO performance.', 'oswp-posts' ); ?></p>
						<?php elseif ( '_yoast_wpseo_metadesc' === $id ) : ?>
							<p class="oswp-form__hint"><?php esc_html_e( 'Recommended: 120–160 characters for search engine snippets.', 'oswp-posts' ); ?></p>
						<?php elseif ( '_yoast_wpseo_focuskw' === $id ) : ?>
							<p class="oswp-form__hint"><?php esc_html_e( 'Enter the primary keyword or phrase you want this article to rank for.', 'oswp-posts' ); ?></p>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php
		}
	}

	/**
	 * Sanitize post data.
	 *
	 * @param array $raw Raw data.
	 *
	 * @return array
	 */
	protected function sanitize_post_data( $raw ) {
		$fields = $this->settings->get( 'post_fields', [] );
		$sanitized = [];

		foreach ( $fields as $field ) {
			$id   = $field['id'];
			$type = $field['type'];
			$val  = isset( $raw[ $id ] ) ? wp_unslash( $raw[ $id ] ) : '';

			// Skip fields that are no longer part of the public form
			if ( 'post_excerpt' === $id || '_yoast_wpseo_title' === $id ) {
				continue;
			}

			if ( 'post_content' === $id || 'wysiwyg' === $type ) {
				$sanitized[ $id ] = Html_Sanitizer::sanitize_fragment( $val );
			} elseif ( 'post_category' === $id || 'post_thumbnail' === $id || 'media' === $type ) {
				$sanitized[ $id ] = absint( $val );
			} elseif ( 'textarea' === $type ) {
				$sanitized[ $id ] = sanitize_textarea_field( $val );
			} elseif ( 'url' === $type ) {
				$sanitized[ $id ] = esc_url_raw( $val );
			} elseif ( 'number' === $type ) {
				$sanitized[ $id ] = is_numeric( $val ) ? floatval( $val ) : 0;
			} elseif ( 'date' === $type ) {
				$sanitized[ $id ] = sanitize_text_field( $val );
			} else {
				$sanitized[ $id ] = sanitize_text_field( $val );
			}
		}

		// Always remove excerpt if it sneaks in
		if ( isset( $sanitized['post_excerpt'] ) ) {
			unset( $sanitized['post_excerpt'] );
		}

		// Fill in SEO fields from content when not provided
		$content = isset( $sanitized['post_content'] ) ? wp_strip_all_tags( $sanitized['post_content'] ) : '';
		if ( $content ) {
			if ( empty( $sanitized['_yoast_wpseo_title'] ) ) {
				$sanitized['_yoast_wpseo_title'] = $this->generate_seo_title_from_content( $content );
			}
			if ( empty( $sanitized['_yoast_wpseo_metadesc'] ) ) {
				$sanitized['_yoast_wpseo_metadesc'] = $this->generate_meta_desc_from_content( $content );
			}
		}

		return $sanitized;
	}

	/**
	 * Process file uploads for featured image (non-admin users).
	 * Converts uploaded files to attachment IDs.
	 *
	 * @param array $data Data array to update with attachment IDs.
	 */
	protected function process_file_uploads( &$data ) {
		// Only process for non-admin users
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check for featured image file upload
		if ( ! empty( $_FILES['post_thumbnail_file'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$file = $_FILES['post_thumbnail_file']; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			
			// Skip processing if no file was uploaded (common when updating existing posts)
			if ( $file['error'] === UPLOAD_ERR_NO_FILE ) {
				return;
			}
			
			// Check for upload errors
			if ( $file['error'] !== UPLOAD_ERR_OK ) {
				$error_messages = [
					UPLOAD_ERR_INI_SIZE => __( 'The uploaded file exceeds the upload_max_filesize directive.', 'oswp-posts' ),
					UPLOAD_ERR_FORM_SIZE => __( 'The uploaded file exceeds the MAX_FILE_SIZE directive.', 'oswp-posts' ),
					UPLOAD_ERR_PARTIAL => __( 'The uploaded file was only partially uploaded.', 'oswp-posts' ),
					UPLOAD_ERR_NO_TMP_DIR => __( 'Missing a temporary folder.', 'oswp-posts' ),
					UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk.', 'oswp-posts' ),
					UPLOAD_ERR_EXTENSION => __( 'A PHP extension stopped the file upload.', 'oswp-posts' ),
				];
				$error_msg = $error_messages[ $file['error'] ] ?? __( 'Unknown upload error.', 'oswp-posts' );
				$this->errors->add( 'post_thumbnail', $error_msg );
				return;
			}

			// Validate file is an image
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$mime_type = finfo_file( $finfo, $file['tmp_name'] );
			finfo_close( $finfo );

			if ( strpos( $mime_type, 'image/' ) !== 0 ) {
				$this->errors->add( 'post_thumbnail', __( 'Please upload a valid image file.', 'oswp-posts' ) );
				return;
			}

			// Check file size (5MB max)
			$max_size = 5 * 1024 * 1024; // 5MB
			if ( $file['size'] > $max_size ) {
				$this->errors->add( 'post_thumbnail', __( 'Image file must be smaller than 5MB.', 'oswp-posts' ) );
				return;
			}

			// Use WordPress media upload function
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			require_once( ABSPATH . 'wp-admin/includes/media.php' );

			$attachment_id = media_handle_upload( 'post_thumbnail_file', 0 );

			if ( is_wp_error( $attachment_id ) ) {
				$this->errors->add( 'post_thumbnail', $attachment_id->get_error_message() );
				return;
			}

			// Update data with attachment ID
			$data['post_thumbnail'] = $attachment_id;
		}
	}

	/**
	 * Returns the list of banned/prohibited words for content moderation.
	 * Only uses keywords managed through the admin panel.
	 *
	 * @return array
	 */
	protected function get_banned_words() {
		// Get user-configured blocked keywords from admin panel
		try {
			$blocked_keywords = Keyword_Manager::get_blocked_keywords();
			if ( ! empty( $blocked_keywords ) ) {
				return $blocked_keywords;
			}
		} catch ( \Exception $e ) {
			// If there's an error getting keywords, return empty array
		}

		return [];
	}

	/**
	 * Check if a string contains any banned words.
	 *
	 * @param string $text       Text to check.
	 * @param string $field_name Human-readable field name for the error message.
	 * @param string $field_id   WP_Error key.
	 */
	protected function check_banned_words( $text, $field_name, $field_id ) {
		if ( empty( $text ) ) {
			return;
		}

		// Use the same matching logic as the keyword manager so blocked words are handled consistently.
		$found_words = Keyword_Manager::find_blocked_keywords( wp_strip_all_tags( $text ) );

		if ( ! empty( $found_words ) ) {
			// Show specific words that were found
			$found_list = implode( ', ', array_map( 'esc_html', array_slice( $found_words, 0, 3 ) ) );
			if ( count( $found_words ) > 3 ) {
				$found_list .= ', ...';
			}

			$this->errors->add(
				$field_id,
				sprintf(
					/* translators: 1: field name, 2: found words */
					__( '%s contains prohibited content. Problematic words: %s. Please remove these words and try again.', 'oswp-posts' ),
					$field_name,
					$found_list
				)
			);
		}
	}

	/**
	 * Validate post data.
	 *
	 * @param array $data Data.
	 */
	protected function validate_post_data( $data ) {
		$fields = $this->settings->get( 'post_fields', [] );

		foreach ( $fields as $field ) {
			if ( 'tab' === $field['type'] ) {
				continue;
			}

			$id   = $field['id'];
			// ignore fields removed from the form
			if ( 'post_excerpt' === $id || '_yoast_wpseo_title' === $id ) {
				continue;
			}
			$name = $field['label'];
			$val  = $data[ $id ] ?? '';

// Check character limits
$char_limit_types = [ 'text', 'email', 'tel', 'url', 'password', 'textarea', 'wysiwyg' ];
if ( in_array( $field['type'] ?? 'text', $char_limit_types, true ) ) {
$val_len = mb_strlen( (string) $val );
$min_len = isset( $field['min_limit'] ) ? absint( $field['min_limit'] ) : 0;
$max_len = ! empty( $field['max_limit'] ) ? absint( $field['max_limit'] ) : 0;

if ( $min_len > 0 && $val_len > 0 && $val_len < $min_len ) {
$this->errors->add( $id, sprintf( __( '%s must be at least %d characters.', 'oswp-posts' ), $name, $min_len ) );
}
if ( $max_len > 0 && $val_len > $max_len ) {
$this->errors->add( $id, sprintf( __( '%s cannot exceed %d characters.', 'oswp-posts' ), $name, $max_len ) );
}
}

			if ( ! empty( $field['required'] ) && empty( $val ) ) {
				$this->errors->add( $id, sprintf( __( '%s is required.', 'oswp-posts' ), $name ) );
			}
		}

		// --- Title validation ---
		$title = $data['post_title'] ?? '';
		if ( ! empty( $title ) ) {
			$title_len = mb_strlen( $title );
			$min_title = $this->settings->get( 'seo_title_min_length', 50 );
			$max_title = 80;
			// SEO requirement: configurable minimum
			if ( $title_len < $min_title ) {
				$this->errors->add( 'post_title', sprintf( __( 'Post Title must be at least %d characters long. (SEO Requirement)', 'oswp-posts' ), $min_title ) );
			}
			if ( $title_len > $max_title ) {
				$this->errors->add( 'post_title', __( 'Title must not exceed 80 characters.', 'oswp-posts' ) );
			}
			// Banned words in title
			$this->check_banned_words( $title, __( 'Title', 'oswp-posts' ), 'post_title' );
		}

		// --- Content validation ---
		$content = $data['post_content'] ?? '';
		if ( ! empty( $content ) ) {
			$plain      = wp_strip_all_tags( $content );
			$word_count = str_word_count( $plain );

			if ( $word_count < 800 ) {
				$this->errors->add( 'post_content', sprintf(
					__( 'Content must be at least 800 words. Current count: %d words.', 'oswp-posts' ),
					$word_count
				) );
			}

			if ( $word_count > 2000 ) {
				$this->errors->add( 'post_content', sprintf(
					__( 'Content must not exceed 2000 words. Current count: %d words.', 'oswp-posts' ),
					$word_count
				) );
			}

			// --- Hyperlink validation: max 1 link in entire content ---
			$link_count = preg_match_all( '/<a\s[^>]*href\s*=/i', $content, $link_matches );

			if ( $link_count > 1 ) {
				$this->errors->add( 'post_content', sprintf(
					__( 'Your content contains %d hyperlinks. Only 1 hyperlink is allowed in the entire article.', 'oswp-posts' ),
					$link_count
				) );
			}

			// No links allowed in the first 5 paragraphs
			if ( $link_count > 0 ) {
				$blocks     = preg_split( '/(<\/p>\s*<p[^>]*>|<br\s*\/?\s*>\s*<br\s*\/?\s*>|\n\s*\n)/i', $content );
				$top_5      = array_slice( $blocks, 0, 5 );
				$top_5_html = implode( "\n", $top_5 );

				if ( preg_match( '/<a\s[^>]*href\s*=/i', $top_5_html ) ) {
					$this->errors->add( 'post_content', __( 'Hyperlinks are not allowed within the first 5 paragraphs of the content.', 'oswp-posts' ) );
				}
			}

			// Banned words in content
			$this->check_banned_words( $plain, __( 'Content', 'oswp-posts' ), 'post_content' );
		}

		// --- SEO validation (hard limits) ---
		$seo_title = $data['_yoast_wpseo_title'] ?? '';
		if ( ! empty( $seo_title ) ) {
			$seo_title_len = mb_strlen( $seo_title );
			if ( $seo_title_len < 10 ) {
				$this->errors->add( '_yoast_wpseo_title', __( 'SEO Title must be at least 10 characters.', 'oswp-posts' ) );
			}
			if ( $seo_title_len > 70 ) {
				$this->errors->add( '_yoast_wpseo_title', sprintf(
					__( 'SEO Title must not exceed 70 characters (currently %d). Ideal range is 50–60 characters.', 'oswp-posts' ),
					$seo_title_len
				) );
			}
			$this->check_banned_words( $seo_title, __( 'SEO Title', 'oswp-posts' ), '_yoast_wpseo_title' );
		}

		$meta_desc = $data['_yoast_wpseo_metadesc'] ?? '';
		if ( ! empty( $meta_desc ) ) {
			$meta_desc_len = mb_strlen( $meta_desc );
			$min_meta = $this->settings->get( 'seo_meta_desc_min_length', 150 );
			$max_meta = 170;
			// SEO requirement: configurable minimum
			if ( $meta_desc_len < $min_meta ) {
				$this->errors->add( '_yoast_wpseo_metadesc', sprintf(
					__( 'Meta Description must be at least %d characters (currently %d). (SEO Requirement)', 'oswp-posts' ),
					$min_meta,
					$meta_desc_len
				) );
			}
			if ( $meta_desc_len > $max_meta ) {
				$this->errors->add( '_yoast_wpseo_metadesc', sprintf(
					__( 'Meta Description must not exceed %d characters (currently %d). Ideal range is 120–160 characters.', 'oswp-posts' ),
					$max_meta,
					$meta_desc_len
				) );
			}
			$this->check_banned_words( $meta_desc, __( 'Meta Description', 'oswp-posts' ), '_yoast_wpseo_metadesc' );
		}

		$focus_kw = $data['_yoast_wpseo_focuskw'] ?? '';
		if ( ! empty( $focus_kw ) ) {
			$this->check_banned_words( $focus_kw, __( 'Focus Keyword', 'oswp-posts' ), '_yoast_wpseo_focuskw' );
		}

		// Check for keyword stuffing: focus keyword should not appear more than 5 times in content
		if ( ! empty( $focus_kw ) && ! empty( $content ) ) {
			$plain_lower    = mb_strtolower( wp_strip_all_tags( $content ) );
			$kw_lower       = mb_strtolower( $focus_kw );
			$kw_occurrences = substr_count( $plain_lower, $kw_lower );
			if ( $kw_occurrences > 15 ) {
				$this->errors->add( '_yoast_wpseo_focuskw', sprintf(
					__( 'The focus keyword appears %d times in the content. Keyword stuffing (more than 15 occurrences) is not allowed.', 'oswp-posts' ),
					$kw_occurrences
				) );
			}
		}

		// --- Featured Image validation ---
		// SEO requirement: Exactly 1 featured image
		if ( empty( $data['post_thumbnail'] ) ) {
			$this->errors->add( 'post_thumbnail', __( 'Featured Image is required. Please upload exactly 1 featured image. (SEO Requirement)', 'oswp-posts' ) );
		}

		// --- Tags validation ---
		// SEO requirement: 3 to 5 tags
		$tags = [];
		if ( ! empty( $data['post_tags'] ) ) {
			$tags = array_filter( array_map( 'trim', explode( ',', $data['post_tags'] ) ) );
		}
		
		if ( count( $tags ) < 3 ) {
			$this->errors->add( 'post_tags', __( 'You must add at least 3 tags. (SEO Requirement)', 'oswp-posts' ) );
		} elseif ( count( $tags ) > 5 ) {
			$this->errors->add( 'post_tags', __( 'You can add a maximum of 5 tags. (SEO Requirement)', 'oswp-posts' ) );
		}

		// File system and storage checks for media fields
		foreach ( $fields as $field ) {
			$id = $field['id'];
			if ( ( 'media' === ( $field['type'] ?? '' ) || 'post_thumbnail' === $id ) && ! empty( $data[ $id ] ) ) {
				$this->validate_media_file( $data[ $id ], $field['label'] ?? $id );
			}
		}

		// Check available disk space
		$this->check_disk_space();
	}

	/**
	 * Generate a basic SEO title from the beginning of the post content.
	 * Strips tags and trims to 60 characters (ensures at least 50 if possible).
	 *
	 * @param string $plain_content Plain text post content.
	 * @return string
	 */
	protected function generate_seo_title_from_content( $plain_content ) {
		$clean = trim( preg_replace( '/\s+/', ' ', $plain_content ) );
		if ( mb_strlen( $clean ) <= 60 ) {
			return $clean;
		}
		// try to end on word boundary around 60 characters
		$substr = mb_substr( $clean, 0, 60 );
		$last_space = mb_strrpos( $substr, ' ' );
		if ( $last_space !== false && $last_space >= 50 ) {
			return mb_substr( $substr, 0, $last_space );
		}
		return $substr;
	}

	/**
	 * Generate a meta description snippet from post content.
	 * Returns first 160 characters of plain text (150+ for enforcement).
	 *
	 * @param string $plain_content Plain text post content.
	 * @return string
	 */
	protected function generate_meta_desc_from_content( $plain_content ) {
		$clean = trim( preg_replace( '/\s+/', ' ', $plain_content ) );
		if ( mb_strlen( $clean ) <= 160 ) {
			return $clean;
		}
		return mb_substr( $clean, 0, 160 );
	}

	/**
	 * Create WordPress post.
	 *
	 * @param array $data Data.
	 * @param int   $user_id User ID.
	 *
	 * @return int|WP_Error
	 */
	protected function create_post( $data, $user_id ) {
		$post_status = $this->settings->get( 'post_status_default', 'pending' );

		// Auto-approve if setting is enabled
		if ( $this->settings->get( 'post_auto_approve' ) ) {
			$post_status = 'publish';
		}

		$post_args = [
			'post_title'   => $data['post_title'] ?? '',
			'post_content' => $data['post_content'] ?? '',
			'post_author'  => $user_id,
			'post_status'  => $post_status,
			'post_type'    => 'post',
		];
		// excerpt is not used
		$post_id = wp_insert_post( $post_args );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Set category
		if ( ! empty( $data['post_category'] ) ) {
			wp_set_post_terms( $post_id, [ $data['post_category'] ], 'category' );
		}

		// Set tags
		if ( ! empty( $data['post_tags'] ) ) {
			$tags = array_map( 'trim', explode( ',', $data['post_tags'] ) );
			wp_set_post_terms( $post_id, $tags, 'post_tag' );
		}

		// Save custom fields
		$fields = $this->settings->get( 'post_fields', [] );
		$core_fields = [ 'post_title', 'post_content', 'post_category', 'post_tags', 'post_thumbnail' ];

		foreach ( $fields as $field ) {
			if ( 'tab' === $field['type'] ) {
				continue;
			}

			$id = $field['id'];
			if ( in_array( $id, $core_fields, true ) ) {
				continue;
			}

			if ( isset( $data[ $id ] ) ) {
				$meta_key = ! empty( $field['meta_key'] ) ? $field['meta_key'] : 'oswp_' . $id;
				update_post_meta( $post_id, $meta_key, $data[ $id ] );
			}
		}

		// Set featured image
		if ( ! empty( $data['post_thumbnail'] ) ) {
			set_post_thumbnail( $post_id, $data['post_thumbnail'] );
		}

		return $post_id;
	}

	/**
	 * Update existing post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    Post data.
	 * @param int   $user_id User ID.
	 *
	 * @return int|WP_Error
	 */
	protected function update_post( $post_id, $data, $user_id ) {
		// Update post
		$update_args = [
			'ID'           => $post_id,
			'post_title'   => $data['post_title'] ?? '',
			'post_content' => $data['post_content'] ?? '',
		];
		$update_result = wp_update_post( $update_args );

		if ( is_wp_error( $update_result ) ) {
			return $update_result;
		}

		// Set category
		if ( ! empty( $data['post_category'] ) ) {
			wp_set_post_terms( $post_id, [ $data['post_category'] ], 'category' );
		}

		// Set tags
		if ( ! empty( $data['post_tags'] ) ) {
			$tags = array_map( 'trim', explode( ',', $data['post_tags'] ) );
			wp_set_post_terms( $post_id, $tags, 'post_tag' );
		}

		// Save custom fields
		$fields = $this->settings->get( 'post_fields', [] );
		$core_fields = [ 'post_title', 'post_content', 'post_category', 'post_tags', 'post_thumbnail' ];

		foreach ( $fields as $field ) {
			if ( 'tab' === $field['type'] ) {
				continue;
			}

			$id = $field['id'];
			if ( in_array( $id, $core_fields, true ) ) {
				continue;
			}

			if ( isset( $data[ $id ] ) ) {
				$meta_key = ! empty( $field['meta_key'] ) ? $field['meta_key'] : 'oswp_' . $id;
				update_post_meta( $post_id, $meta_key, $data[ $id ] );
			}
		}

		// Set featured image
		if ( ! empty( $data['post_thumbnail'] ) ) {
			set_post_thumbnail( $post_id, $data['post_thumbnail'] );
		} else {
			delete_post_thumbnail( $post_id );
		}

		return $post_id;
	}

	/**
	 * Get user posts created this month.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return int
	 */
	protected function get_user_posts_this_month( $user_id ) {
		$current_month = gmdate( 'Y-m' );
		$args = [
			'author'         => $user_id,
			'posts_per_page' => -1,
			'fields'         => 'ids',
		];

		$query = new \WP_Query( $args );
		$all_posts = $query->posts;
		wp_reset_postdata();

		$count = 0;
		foreach ( $all_posts as $post_id ) {
			$post_date = gmdate( 'Y-m', strtotime( get_post( $post_id )->post_date ) );
			if ( $post_date === $current_month ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Check available disk space and upload directory writability.
	 *
	 * @throws \Exception If disk space is insufficient or upload directory is not writable.
	 */
	protected function check_disk_space() {
		$upload_dir = wp_upload_dir();
		$upload_path = $upload_dir['path'];

		// Check if upload directory is writable
		if ( ! is_writable( $upload_path ) ) {
			throw new \Exception( __( 'Upload directory is not writable. Please check permissions.', 'oswp-posts' ) );
		}

		// Check available disk space (require at least 10MB free)
		$free_space = disk_free_space( $upload_path );
		if ( $free_space !== false && $free_space < 10 * 1024 * 1024 ) { // 10MB
			throw new \Exception( __( 'Insufficient disk space. At least 10MB free space required.', 'oswp-posts' ) );
		}
	}

	/**
	 * Validate media file.
	 *
	 * @param mixed  $file File data or ID.
	 * @param string $field_name Field name for error messages.
	 *
	 * @throws \Exception If file validation fails.
	 */
	protected function validate_media_file( $file, $field_name ) {
		// If it's an attachment ID, get the file path
		if ( is_numeric( $file ) ) {
			$file_path = get_attached_file( $file );
			if ( ! $file_path ) {
				throw new \Exception( sprintf( __( 'Invalid file ID for %s.', 'oswp-posts' ), $field_name ) );
			}
		} elseif ( is_array( $file ) && isset( $file['tmp_name'] ) ) {
			// Handle uploaded file array
			$file_path = $file['tmp_name'];
			$file_name = $file['name'];
			$file_size = $file['size'];
		} else {
			throw new \Exception( sprintf( __( 'Invalid file data for %s.', 'oswp-posts' ), $field_name ) );
		}

		// Check file size (5MB limit)
		if ( isset( $file_size ) && $file_size > 5 * 1024 * 1024 ) { // 5MB
			throw new \Exception( sprintf( __( 'File size exceeds 5MB limit for %s.', 'oswp-posts' ), $field_name ) );
		}

		// Check file type if we have the file name
		if ( isset( $file_name ) ) {
			$allowed_types = [ 'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx' ];
			$file_ext = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

			if ( ! in_array( $file_ext, $allowed_types, true ) ) {
				throw new \Exception( sprintf( __( 'File type not allowed for %s. Allowed types: %s', 'oswp-posts' ), $field_name, implode( ', ', $allowed_types ) ) );
			}
		}

		// For uploaded files, check if the file actually exists
		if ( isset( $file_path ) && ! file_exists( $file_path ) ) {
			throw new \Exception( sprintf( __( 'Uploaded file not found for %s.', 'oswp-posts' ), $field_name ) );
		}
	}

	/**
	 * Group validation errors by category for better organization
	 *
	 * @param \WP_Error $errors WP_Error object with validation errors.
	 * @return array Grouped errors by category.
	 */
	protected function group_validation_errors( \WP_Error $errors ) {
		$groups = [
			'seo' => [],
			'content' => [],
			'media' => [],
			'tags' => [],
			'general' => [],
		];

		$messages = $errors->get_error_messages();

		foreach ( $messages as $message ) {
			// Categorize errors
			if ( stripos( $message, 'seo' ) !== false || 
				 stripos( $message, 'meta description' ) !== false ||
				 stripos( $message, 'seo title' ) !== false ||
				 stripos( $message, 'focus keyword' ) !== false ) {
				$groups['seo'][] = $message;
			} elseif ( stripos( $message, 'content' ) !== false ||
					   stripos( $message, 'word' ) !== false ||
					   stripos( $message, 'hyperlink' ) !== false ||
					   stripos( $message, 'keyword' ) !== false ) {
				$groups['content'][] = $message;
			} elseif ( stripos( $message, 'image' ) !== false ||
					   stripos( $message, 'attachment' ) !== false ||
					   stripos( $message, 'file' ) !== false ||
					   stripos( $message, 'thumbnail' ) !== false ) {
				$groups['media'][] = $message;
			} elseif ( stripos( $message, 'tag' ) !== false ) {
				$groups['tags'][] = $message;
			} else {
				$groups['general'][] = $message;
			}
		}

		// Remove empty groups
		return array_filter( $groups );
	}

	/**
	 * Render enhanced validation notice with grouped errors
	 *
	 * @param array $grouped_errors Grouped errors by category.
	 */
	protected function render_validation_notice( $grouped_errors ) {
		if ( empty( $grouped_errors ) ) {
			return;
		}

		$category_labels = [
			'seo' => __( '📊 SEO Requirements', 'oswp-posts' ),
			'content' => __( '📝 Content Requirements', 'oswp-posts' ),
			'media' => __( '🖼️ Media Requirements', 'oswp-posts' ),
			'tags' => __( '🏷️ Tag Requirements', 'oswp-posts' ),
			'general' => __( '⚠️ General Requirements', 'oswp-posts' ),
		];

		?>
		<div class="oswp-notice oswp-notice--error oswp-validation-notice">
			<div class="oswp-validation-notice__header">
				<strong><?php echo esc_html__( 'Please Fix the Following Issues:', 'oswp-posts' ); ?></strong>
			</div>
			
			<?php foreach ( $grouped_errors as $category => $messages ) : ?>
				<?php if ( ! empty( $messages ) ) : ?>
					<div class="oswp-validation-group oswp-validation-group--<?php echo esc_attr( $category ); ?>">
						<h4 class="oswp-validation-group__title">
							<?php echo esc_html( $category_labels[ $category ] ?? ucfirst( $category ) ); ?>
						</h4>
						<ul class="oswp-validation-group__items">
							<?php foreach ( $messages as $message ) : ?>
								<li class="oswp-validation-item">
									<span class="oswp-validation-item__icon">✗</span>
									<span class="oswp-validation-item__text"><?php echo esc_html( $message ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
