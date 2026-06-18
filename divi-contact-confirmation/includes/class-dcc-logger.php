<?php
/**
 * Handles writing and reading log entries from a custom DB table.
 *
 * Table: {prefix}dcc_log
 * Columns: id, sent_at, recipient_email, recipient_name, subject, status, error_message
 *
 * @author  Mohammad Babaei <https://adschi.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCC_Logger {

	const TABLE_BASE = 'dcc_log';

	/**
	 * Create (or upgrade) the log table. Safe to call on every activation.
	 */
	public static function create_table() {
		global $wpdb;

		$table      = $wpdb->prefix . self::TABLE_BASE;
		$charset    = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			sent_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			recipient_email VARCHAR(200)  NOT NULL DEFAULT '',
			recipient_name  VARCHAR(200)  NOT NULL DEFAULT '',
			subject         VARCHAR(500)  NOT NULL DEFAULT '',
			status          VARCHAR(10)   NOT NULL DEFAULT 'sent',
			error_message   TEXT          NOT NULL DEFAULT '',
			PRIMARY KEY (id),
			KEY sent_at (sent_at),
			KEY status  (status)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'dcc_db_version', '1.0' );
	}

	/**
	 * Drop the log table. Called on uninstall only.
	 */
	public static function drop_table() {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_BASE;
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Insert one log row.
	 *
	 * @param array $data {
	 *     @type string $recipient_email
	 *     @type string $recipient_name
	 *     @type string $subject
	 *     @type bool   $sent
	 *     @type string $error_message
	 * }
	 */
	public static function write( $data ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . self::TABLE_BASE,
			array(
				'sent_at'         => current_time( 'mysql' ),
				'recipient_email' => sanitize_email( $data['recipient_email'] ?? '' ),
				'recipient_name'  => sanitize_text_field( $data['recipient_name'] ?? '' ),
				'subject'         => sanitize_text_field( $data['subject'] ?? '' ),
				'status'          => $data['status'] ?? ( empty( $data['sent'] ) ? 'failed' : 'sent' ),
				'error_message'   => sanitize_text_field( $data['error_message'] ?? '' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Fetch a page of log rows, newest first.
	 *
	 * @param int $per_page
	 * @param int $page      1-based
	 * @return array { rows, total }
	 */
	public static function get_rows( $per_page = 20, $page = 1 ) {
		global $wpdb;

		$table  = $wpdb->prefix . self::TABLE_BASE;
		$offset = ( max( 1, (int) $page ) - 1 ) * (int) $per_page;

		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` ORDER BY sent_at DESC LIMIT %d OFFSET %d", // phpcs:ignore
				(int) $per_page,
				$offset
			)
		);
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore

		return array( 'rows' => $rows, 'total' => $total );
	}

	/**
	 * Delete all log rows.
	 */
	public static function clear() {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE `' . $wpdb->prefix . self::TABLE_BASE . '`' ); // phpcs:ignore
	}
}
