<?php
/**
 * Email Logs Cleanup Tool - ADMIN ONLY
 * 
 * This file provides easy functions to manage and remove the email logging system.
 * Only include this in your admin or run these functions manually if you need to clean up.
 * 
 * Usage:
 * - Add this to your theme functions.php temporarily or create an admin page
 * - Call oswp_cleanup_email_logs() to remove the table completely
 * 
 * @package OSWP\Posts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Completely remove the email logs table.
 * This should be called during plugin deactivation or manually if needed.
 */
function oswp_remove_email_logs_table() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// Use the Email_Log class to remove the table
	if ( class_exists( 'OSWP\Posts\Emails\Email_Log' ) ) {
		\OSWP\Posts\Emails\Email_Log::remove_table();
		return true;
	}

	return false;
}

/**
 * Clear all old email logs (older than X days).
 * 
 * @param int $days Number of days to keep. Default 30.
 * @return int Number of deleted records.
 */
function oswp_cleanup_old_email_logs( $days = 30 ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}

	if ( class_exists( 'OSWP\Posts\Emails\Email_Log' ) ) {
		return \OSWP\Posts\Emails\Email_Log::cleanup_old_logs( $days );
	}

	return 0;
}

/**
 * Get email logs statistics.
 * Useful for monitoring purposes.
 * 
 * @return array Array with 'total', 'sent', and 'failed' counts.
 */
function oswp_get_email_logs_stats() {
	if ( class_exists( 'OSWP\Posts\Emails\Email_Log' ) ) {
		return \OSWP\Posts\Emails\Email_Log::get_statistics();
	}

	return [ 'total' => 0, 'sent' => 0, 'failed' => 0 ];
}

/**
 * Export email logs to CSV (admin only).
 * 
 * @param array $args Query arguments.
 * @return string CSV content.
 */
function oswp_export_email_logs_csv( $args = [] ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}

	if ( ! class_exists( 'OSWP\Posts\Emails\Email_Log' ) ) {
		return '';
	}

	$logs = \OSWP\Posts\Emails\Email_Log::get_logs( array_merge( $args, [ 'limit' => -1 ] ) );

	$csv = "Email,User ID,Template,Subject,Status,Sent At,Error\n";

	foreach ( $logs as $log ) {
		$error = str_replace( '"', '""', $log->error_message ?? '' );
		$csv .= sprintf(
			'"%s","%s","%s","%s","%s","%s","%s"' . "\n",
			$log->recipient_email,
			$log->recipient_user_id ?? '',
			$log->template_key,
			str_replace( '"', '""', $log->subject ),
			$log->status,
			$log->sent_at,
			$error
		);
	}

	return $csv;
}
