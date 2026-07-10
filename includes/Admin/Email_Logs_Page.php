<?php
/**
 * Email logs admin page.
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Admin;

use OSWP\Posts\Emails\Email_Log;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display email logs in admin panel.
 */
class Email_Logs_Page {
	/**
	 * Add menu page.
	 */
	public static function add_menu() {
		add_submenu_page(
			'oswp-posts-dashboard',
			__( 'Email Logs', 'oswp-posts' ),
			__( 'Email Logs', 'oswp-posts' ),
			'manage_options',
			'oswp-email-logs',
			[ __CLASS__, 'render_page' ]
		);
	}

	/**
	 * Enqueue scripts and styles for the admin page.
	 */
	public static function enqueue_scripts( $hook ) {
		// Only load on our specific page
		if ( 'oswp-posts_page_oswp-email-logs' !== $hook ) {
			return;
		}

		wp_enqueue_script( 'jquery' );
		wp_add_inline_script( 'jquery', '
			jQuery(document).ready(function($) {
				// Select all checkbox
				$("#select-all").on("change", function() {
					$("input[name=\"log_ids[]\"]").prop("checked", this.checked);
				});

				// View log details
				$(document).on("click", ".view-log", function(e) {
					e.preventDefault();
					var logId = $(this).data("log-id");
					$.ajax({
						url: ajaxurl,
						type: "POST",
						data: {
							action: "oswp_get_email_log",
							log_id: logId,
							nonce: "' . wp_create_nonce( 'oswp_get_email_log' ) . '"
						},
						success: function(response) {
							if (response.success) {
								$("#modal-content").html(response.data);
								$("#email-log-modal").css("display", "flex");
							}
						}
					});
				});

				// Close modal
				$("#close-modal, #email-log-modal").on("click", function(e) {
					if (this.id === "email-log-modal" || this.id === "close-modal") {
						$("#email-log-modal").hide();
					}
				});
			});
		' );
	}

	/**
	 * Render the email logs page.
	 */
	public static function render_page() {
		// Handle bulk actions
		if ( isset( $_POST['action'] ) && check_admin_referer( 'oswp_email_logs_action' ) ) {
			self::handle_bulk_actions();
		}

		$paged   = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$offset  = ( $paged - 1 ) * 20;
		$filters = self::get_filters_from_request();

		// Get logs and count
		$logs  = Email_Log::get_logs( array_merge( $filters, [ 'limit' => 20, 'offset' => $offset ] ) );
		$total = Email_Log::count_logs( $filters );
		$stats = Email_Log::get_statistics();

		// Calculate pagination
		$pages = ceil( $total / 20 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Email Logs', 'oswp-posts' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'This is a temporary system to log and debug all sent emails. You can remove this system anytime.', 'oswp-posts' ); ?>
			</p>

			<!-- Statistics -->
			<div class="oswp-stats-box" style="margin: 20px 0; padding: 15px; background: #f5f5f5; border-radius: 5px; border-left: 4px solid #0073aa;">
				<p style="margin: 5px 0;">
					<strong><?php esc_html_e( 'Total Emails:', 'oswp-posts' ); ?></strong> <span style="color: #0073aa; font-size: 18px;"><?php echo esc_html( $stats['total'] ); ?></span>
				</p>
				<p style="margin: 5px 0;">
					<strong><?php esc_html_e( 'Sent:', 'oswp-posts' ); ?></strong> <span style="color: green;"><?php echo esc_html( $stats['sent'] ); ?></span>
				</p>
				<p style="margin: 5px 0;">
					<strong><?php esc_html_e( 'Failed:', 'oswp-posts' ); ?></strong> <span style="color: red;"><?php echo esc_html( $stats['failed'] ); ?></span>
				</p>
			</div>

			<!-- Filters -->
			<form method="get" style="margin: 20px 0;">
				<input type="hidden" name="page" value="oswp-email-logs" />
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
					<div>
						<label><?php esc_html_e( 'Email', 'oswp-posts' ); ?></label>
						<input type="email" name="email" value="<?php echo isset( $_GET['email'] ) ? esc_attr( sanitize_email( $_GET['email'] ) ) : ''; ?>" placeholder="example@domain.com" />
					</div>
					<div>
						<label><?php esc_html_e( 'Template', 'oswp-posts' ); ?></label>
						<input type="text" name="template" value="<?php echo isset( $_GET['template'] ) ? esc_attr( sanitize_text_field( $_GET['template'] ) ) : ''; ?>" placeholder="verification, login, etc." />
					</div>
					<div>
						<label><?php esc_html_e( 'Status', 'oswp-posts' ); ?></label>
						<select name="status">
							<option value=""><?php esc_html_e( 'All', 'oswp-posts' ); ?></option>
							<option value="sent" <?php selected( isset( $_GET['status'] ) && 'sent' === $_GET['status'] ); ?>><?php esc_html_e( 'Sent', 'oswp-posts' ); ?></option>
							<option value="failed" <?php selected( isset( $_GET['status'] ) && 'failed' === $_GET['status'] ); ?>><?php esc_html_e( 'Failed', 'oswp-posts' ); ?></option>
						</select>
					</div>
					<div style="display: flex; align-items: flex-end;">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'oswp-posts' ); ?></button>
					</div>
				</div>
			</form>

			<!-- Bulk Actions -->
			<form method="post" style="margin-bottom: 20px;">
				<?php wp_nonce_field( 'oswp_email_logs_action' ); ?>
				<div style="display: flex; gap: 10px;">
					<select name="action">
						<option value=""><?php esc_html_e( 'Bulk Actions', 'oswp-posts' ); ?></option>
						<option value="delete_selected"><?php esc_html_e( 'Delete Selected', 'oswp-posts' ); ?></option>
						<option value="clear_all"><?php esc_html_e( 'Clear All Logs', 'oswp-posts' ); ?></option>
						<option value="cleanup_old"><?php esc_html_e( 'Clear Logs Older Than 7 Days', 'oswp-posts' ); ?></option>
					</select>
					<button type="submit" class="button"><?php esc_html_e( 'Apply', 'oswp-posts' ); ?></button>
				</div>

				<!-- Table -->
				<div style="margin-top: 20px;">
					<table class="widefat striped">
						<thead>
							<tr>
								<th style="width: 50px;"><input type="checkbox" id="select-all" /></th>
								<th><?php esc_html_e( 'Email', 'oswp-posts' ); ?></th>
								<th><?php esc_html_e( 'Template', 'oswp-posts' ); ?></th>
								<th><?php esc_html_e( 'Subject', 'oswp-posts' ); ?></th>
								<th><?php esc_html_e( 'Status', 'oswp-posts' ); ?></th>
								<th><?php esc_html_e( 'Sent At', 'oswp-posts' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'oswp-posts' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( ! empty( $logs ) ) : ?>
								<?php foreach ( $logs as $log ) : ?>
									<tr>
										<td><input type="checkbox" name="log_ids[]" value="<?php echo esc_attr( $log->id ); ?>" /></td>
										<td><?php echo esc_html( $log->recipient_email ); ?></td>
										<td><code><?php echo esc_html( $log->template_key ); ?></code></td>
										<td style="max-width: 300px; word-break: break-word;">
											<?php echo esc_html( substr( $log->subject, 0, 50 ) ); ?>
											<?php if ( strlen( $log->subject ) > 50 ) : ?>
												...
											<?php endif; ?>
										</td>
										<td>
											<?php if ( 'sent' === $log->status ) : ?>
												<span style="color: green; font-weight: bold;">✓ <?php esc_html_e( 'Sent', 'oswp-posts' ); ?></span>
											<?php else : ?>
												<span style="color: red; font-weight: bold;">✗ <?php esc_html_e( 'Failed', 'oswp-posts' ); ?></span>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( $log->sent_at ); ?></td>
										<td>
											<a href="#" class="view-log" data-log-id="<?php echo esc_attr( $log->id ); ?>" style="cursor: pointer; color: #0073aa;">
												<?php esc_html_e( 'View', 'oswp-posts' ); ?>
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php else : ?>
								<tr>
									<td colspan="7" style="text-align: center; padding: 20px;">
										<?php esc_html_e( 'No email logs found.', 'oswp-posts' ); ?>
									</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<!-- Pagination -->
				<?php if ( $pages > 1 ) : ?>
					<div style="margin-top: 20px; text-align: center;">
						<?php
						$page_links = paginate_links(
							[
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => __( '← Previous', 'oswp-posts' ),
								'next_text' => __( 'Next →', 'oswp-posts' ),
								'total'     => $pages,
								'current'   => $paged,
								'type'      => 'array',
							]
						);
						if ( ! empty( $page_links ) ) {
							echo wp_kses_post( implode( '&nbsp;', $page_links ) );
						}
						?>
					</div>
				<?php endif; ?>
			</form>
		</div>

		<!-- Modal for viewing full email -->
		<div id="email-log-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
			<div style="background: white; border-radius: 5px; padding: 30px; max-width: 800px; max-height: 90vh; overflow-y: auto; width: 90%;">
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
					<h2><?php esc_html_e( 'Email Details', 'oswp-posts' ); ?></h2>
					<button type="button" id="close-modal" style="background: none; border: none; font-size: 24px; cursor: pointer;">×</button>
				</div>
				<div id="modal-content"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get filter values from request.
	 *
	 * @return array
	 */
	private static function get_filters_from_request() {
		$filters = [];

		if ( ! empty( $_GET['status'] ) ) {
			$filters['status'] = sanitize_text_field( $_GET['status'] );
		}

		if ( ! empty( $_GET['template'] ) ) {
			$filters['template'] = sanitize_text_field( $_GET['template'] );
		}

		if ( ! empty( $_GET['email'] ) ) {
			$filters['email'] = sanitize_email( $_GET['email'] );
		}

		return $filters;
	}

	/**
	 * Handle bulk actions.
	 */
	private static function handle_bulk_actions() {
		$action = isset( $_POST['action'] ) ? sanitize_text_field( $_POST['action'] ) : '';

		if ( 'delete_selected' === $action ) {
			if ( ! empty( $_POST['log_ids'] ) ) {
				global $wpdb;
				$table_name = Email_Log::get_table_name();
				$log_ids    = array_map( 'absint', $_POST['log_ids'] );

				foreach ( $log_ids as $log_id ) {
					$wpdb->delete( $table_name, [ 'id' => $log_id ], [ '%d' ] );
				}

				add_action( 'admin_notices', function() {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Selected logs deleted.', 'oswp-posts' ) . '</p></div>';
				} );
			}
		} elseif ( 'clear_all' === $action ) {
			Email_Log::truncate_logs();
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'All email logs cleared.', 'oswp-posts' ) . '</p></div>';
			} );
		} elseif ( 'cleanup_old' === $action ) {
			$count = Email_Log::cleanup_old_logs( 7 );
			add_action( 'admin_notices', function() use ( $count ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( '%d old logs deleted.', 'oswp-posts' ), $count ) . '</p></div>';
			} );
		}
	}

	/**
	 * AJAX handler to get and display log details.
	 */
	public static function ajax_get_log() {
		check_ajax_referer( 'oswp_get_email_log' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$log_id = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;
		$log    = Email_Log::get_log( $log_id );

		if ( ! $log ) {
			wp_send_json_error( 'Log not found' );
		}

		ob_start();
		?>
		<div style="margin-bottom: 20px;">
			<p><strong><?php esc_html_e( 'Email:', 'oswp-posts' ); ?></strong> <?php echo esc_html( $log->recipient_email ); ?></p>
			<p><strong><?php esc_html_e( 'User ID:', 'oswp-posts' ); ?></strong> <?php echo $log->recipient_user_id ? esc_html( $log->recipient_user_id ) : esc_html__( 'N/A', 'oswp-posts' ); ?></p>
			<p><strong><?php esc_html_e( 'Template:', 'oswp-posts' ); ?></strong> <code><?php echo esc_html( $log->template_key ); ?></code></p>
			<p><strong><?php esc_html_e( 'Status:', 'oswp-posts' ); ?></strong> 
				<?php if ( 'sent' === $log->status ) : ?>
					<span style="color: green;">✓ <?php esc_html_e( 'Sent', 'oswp-posts' ); ?></span>
				<?php else : ?>
					<span style="color: red;">✗ <?php esc_html_e( 'Failed', 'oswp-posts' ); ?></span>
				<?php endif; ?>
			</p>
			<p><strong><?php esc_html_e( 'Sent At:', 'oswp-posts' ); ?></strong> <?php echo esc_html( $log->sent_at ); ?></p>
		</div>

		<div style="border-top: 1px solid #ddd; padding-top: 20px;">
			<h3><?php esc_html_e( 'Subject', 'oswp-posts' ); ?></h3>
			<p style="background: #f5f5f5; padding: 10px; border-radius: 3px;">
				<?php echo esc_html( $log->subject ); ?>
			</p>
		</div>

		<div style="margin-top: 20px;">
			<h3><?php esc_html_e( 'Body', 'oswp-posts' ); ?></h3>
			<div style="background: #f5f5f5; padding: 15px; border-radius: 3px; max-height: 400px; overflow-y: auto;">
				<?php echo wp_kses_post( $log->body ); ?>
			</div>
		</div>

		<?php if ( ! empty( $log->error_message ) ) : ?>
			<div style="margin-top: 20px; border: 1px solid #ffcccc; background: #fff5f5; padding: 15px; border-radius: 3px;">
				<h3 style="color: red; margin-top: 0;"><?php esc_html_e( 'Error', 'oswp-posts' ); ?></h3>
				<p><?php echo esc_html( $log->error_message ); ?></p>
			</div>
		<?php endif; ?>
		<?php
		$html = ob_get_clean();

		wp_send_json_success( $html );
	}
}
