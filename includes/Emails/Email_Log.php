<?php
/**
 * Email logging system - TEMPORARY for testing purposes.
 *
 * @package OSWP\Posts
 */

namespace OSWP\Posts\Emails;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages email logs in database.
 * Purpose: Temporary system to store and track all sent emails for debugging and testing.
 * Can be easily removed by calling remove_tables() during plugin deactivation.
 */
class Email_Log {
	/**
	 * Table name.
	 *
	 * @var string
	 */
	private static $table_name = 'oswp_email_logs';

	/**
	 * Get full table name with WordPress prefix.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::$table_name;
	}

	/**
	 * Create table on plugin activation.
	 */
	public static function create_table() {
		global $wpdb;
		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			recipient_email VARCHAR(100) NOT NULL,
			recipient_user_id BIGINT(20) UNSIGNED DEFAULT NULL,
			template_key VARCHAR(100) NOT NULL,
			subject VARCHAR(255) NOT NULL,
			body LONGTEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			error_message LONGTEXT,
			sent_at DATETIME NOT NULL,
			created_at DATETIME NOT NULL,
			KEY recipient_email (recipient_email),
			KEY template_key (template_key),
			KEY status (status),
			KEY sent_at (sent_at),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Drop table on plugin deactivation (clean removal).
	 */
	public static function remove_table() {
		global $wpdb;
		$table_name = self::get_table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name};" );
	}

	/**
	 * Log a sent email to database.
	 *
	 * @param string              $recipient_email Email address.
	 * @param int|null            $user_id         User ID if applicable.
	 * @param string              $template_key    Template identifier.
	 * @param string              $subject         Email subject.
	 * @param string              $body            Email body.
	 * @param bool                $success         Whether email was sent successfully.
	 * @param string|null         $error_message   Error message if failed.
	 *
	 * @return int|false Insert ID or false on failure.
	 */
	public static function log_email( $recipient_email, $user_id, $template_key, $subject, $body, $success = true, $error_message = null ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$status = $success ? 'sent' : 'failed';

		$result = $wpdb->insert(
			$table_name,
			[
				'recipient_email'  => sanitize_email( $recipient_email ),
				'recipient_user_id'=> $user_id ? absint( $user_id ) : null,
				'template_key'     => sanitize_text_field( $template_key ),
				'subject'          => sanitize_text_field( $subject ),
				'body'             => wp_kses_post( $body ),
				'status'           => $status,
				'error_message'    => $error_message ? sanitize_textarea_field( $error_message ) : null,
				'sent_at'          => current_time( 'mysql' ),
				'created_at'       => current_time( 'mysql' ),
			],
			[
				'%s', // recipient_email
				'%d', // recipient_user_id
				'%s', // template_key
				'%s', // subject
				'%s', // body
				'%s', // status
				'%s', // error_message
				'%s', // sent_at
				'%s', // created_at
			]
		);

		return false !== $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get all logged emails.
	 *
	 * @param array $args Query arguments.
	 *
	 * @return array
	 */
	public static function get_logs( array $args = [] ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$defaults = [
			'status'   => '',
			'template' => '',
			'email'    => '',
			'user_id'  => '',
			'limit'    => 100,
			'offset'   => 0,
			'orderby'  => 'sent_at',
			'order'    => 'DESC',
		];

		$args = wp_parse_args( $args, $defaults );

		$query = "SELECT * FROM {$table_name} WHERE 1=1";

		if ( ! empty( $args['status'] ) ) {
			$query .= $wpdb->prepare( ' AND status = %s', $args['status'] );
		}

		if ( ! empty( $args['template'] ) ) {
			$query .= $wpdb->prepare( ' AND template_key = %s', $args['template'] );
		}

		if ( ! empty( $args['email'] ) ) {
			$query .= $wpdb->prepare( ' AND recipient_email LIKE %s', '%' . $wpdb->esc_like( $args['email'] ) . '%' );
		}

		if ( ! empty( $args['user_id'] ) ) {
			$query .= $wpdb->prepare( ' AND recipient_user_id = %d', absint( $args['user_id'] ) );
		}

		$query .= " ORDER BY {$args['orderby']} " . strtoupper( $args['order'] ) . " LIMIT " . absint( $args['limit'] ) . " OFFSET " . absint( $args['offset'] );

		return $wpdb->get_results( $query ) ?? [];
	}

	/**
	 * Get log count.
	 *
	 * @param array $args Query arguments.
	 *
	 * @return int
	 */
	public static function count_logs( array $args = [] ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$defaults = [
			'status'   => '',
			'template' => '',
			'email'    => '',
			'user_id'  => '',
		];

		$args = wp_parse_args( $args, $defaults );

		$query = "SELECT COUNT(*) FROM {$table_name} WHERE 1=1";

		if ( ! empty( $args['status'] ) ) {
			$query .= $wpdb->prepare( ' AND status = %s', $args['status'] );
		}

		if ( ! empty( $args['template'] ) ) {
			$query .= $wpdb->prepare( ' AND template_key = %s', $args['template'] );
		}

		if ( ! empty( $args['email'] ) ) {
			$query .= $wpdb->prepare( ' AND recipient_email LIKE %s', '%' . $wpdb->esc_like( $args['email'] ) . '%' );
		}

		if ( ! empty( $args['user_id'] ) ) {
			$query .= $wpdb->prepare( ' AND recipient_user_id = %d', absint( $args['user_id'] ) );
		}

		return absint( $wpdb->get_var( $query ) ?? 0 );
	}

	/**
	 * Get a single log entry.
	 *
	 * @param int $log_id Log ID.
	 *
	 * @return object|null
	 */
	public static function get_log( $log_id ) {
		global $wpdb;
		$table_name = self::get_table_name();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d",
				absint( $log_id )
			)
		);
	}

	/**
	 * Delete logs older than X days.
	 *
	 * @param int $days Number of days to keep. Default 30.
	 *
	 * @return int Number of rows deleted.
	 */
	public static function cleanup_old_logs( $days = 30 ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$date = gmdate( 'Y-m-d H:i:s', time() - ( absint( $days ) * DAY_IN_SECONDS ) );

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE created_at < %s",
				$date
			)
		);
	}

	/**
	 * Delete all logs (complete cleanup).
	 *
	 * @return int Number of rows deleted.
	 */
	public static function truncate_logs() {
		global $wpdb;
		$table_name = self::get_table_name();

		return $wpdb->query( "TRUNCATE TABLE {$table_name}" );
	}

	/**
	 * Get statistics.
	 *
	 * @return array
	 */
	public static function get_statistics() {
		global $wpdb;
		$table_name = self::get_table_name();

		return [
			'total'  => absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ) ?? 0 ),
			'sent'   => absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'sent'" ) ?? 0 ),
			'failed' => absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'failed'" ) ?? 0 ),
		];
	}
}
