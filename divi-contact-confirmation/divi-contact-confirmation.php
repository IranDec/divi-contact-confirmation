<?php
/**
 * Plugin Name: Divi Contact Form Confirmation Email
 * Plugin URI:  https://adschi.com
 * Description: Sends an automatic confirmation email to users after they submit a Divi contact form. Compatible with Divi 4, Divi 5, and the latest WordPress.
 * Version:     1.5.3
 * Author:      Mohammad Babaei
 * Author URI:  https://adschi.com
 * License:     GPL-2.0-or-later
 * Text Domain: divi-contact-confirmation
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DCC_VERSION', '1.5.3' );
define( 'DCC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DCC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once DCC_PLUGIN_DIR . 'includes/class-dcc-logger.php';
require_once DCC_PLUGIN_DIR . 'includes/class-dcc-security.php';
require_once DCC_PLUGIN_DIR . 'includes/class-dcc-settings.php';
require_once DCC_PLUGIN_DIR . 'includes/class-dcc-mailer.php';
require_once DCC_PLUGIN_DIR . 'includes/class-dcc-hooks.php';

function dcc_init() {
	load_plugin_textdomain(
		'divi-contact-confirmation',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);

	// Run DB upgrades when version changes (e.g. new columns added)
	if ( get_option( 'dcc_db_version' ) !== '1.1' ) {
		DCC_Logger::create_table();
	}

	// Ensure cron is scheduled (covers upgrades without re-activation)
	if ( ! wp_next_scheduled( 'dcc_purge_blocked_logs' ) ) {
		wp_schedule_event( time(), 'hourly', 'dcc_purge_blocked_logs' );
	}

	DCC_Settings::init();
	DCC_Hooks::init();
}
add_action( 'plugins_loaded', 'dcc_init' );

// WP-Cron: purge blocked/failed log entries older than the configured TTL
add_action( 'dcc_purge_blocked_logs', 'dcc_run_purge_blocked_logs' );
function dcc_run_purge_blocked_logs() {
	$hours = (int) get_option( 'dcc_log_blocked_ttl', 24 );
	if ( $hours > 0 ) {
		DCC_Logger::purge_blocked( $hours );
	}
}

function dcc_activate() {
	DCC_Logger::create_table();

	$defaults = array(
		// Email settings
		'subject'    => __( 'We received your message!', 'divi-contact-confirmation' ),
		'body'       => __( "Hello {name},\n\nThank you for contacting us. We have received your message and will get back to you as soon as possible.\n\nBest regards,\n{site_name}", 'divi-contact-confirmation' ),
		'from_name'  => get_bloginfo( 'name' ),
		'from_email' => get_bloginfo( 'admin_email' ),
		// Security settings
		'sec_enabled'              => '1',
		'sec_rate_limit'           => '5',
		'sec_blocked_domains'      => '',
		'sec_blocked_keywords'     => '',
		'sec_check_mx'             => '0',
		'sec_log_blocked'          => '1',
		// reCAPTCHA v3
		'sec_recaptcha_site_key'   => '',
		'sec_recaptcha_secret_key' => '',
		'sec_recaptcha_min_score'  => '0.5',
		// Log auto-cleanup
		'log_blocked_ttl'          => '24',
	);

	foreach ( $defaults as $key => $value ) {
		if ( false === get_option( 'dcc_' . $key ) ) {
			add_option( 'dcc_' . $key, $value );
		}
	}

	// Schedule hourly cron to purge blocked logs
	if ( ! wp_next_scheduled( 'dcc_purge_blocked_logs' ) ) {
		wp_schedule_event( time(), 'hourly', 'dcc_purge_blocked_logs' );
	}
}
register_activation_hook( __FILE__, 'dcc_activate' );

function dcc_deactivate() {
	wp_clear_scheduled_hook( 'dcc_purge_blocked_logs' );
}
register_deactivation_hook( __FILE__, 'dcc_deactivate' );

function dcc_uninstall() {
	DCC_Logger::drop_table();
	$options = array(
		'subject', 'body', 'from_name', 'from_email', 'db_version',
		'sec_enabled', 'sec_rate_limit', 'sec_blocked_domains',
		'sec_blocked_keywords', 'sec_check_mx', 'sec_log_blocked',
		'sec_recaptcha_site_key', 'sec_recaptcha_secret_key', 'sec_recaptcha_min_score',
		'log_blocked_ttl',
	);
	foreach ( $options as $key ) {
		delete_option( 'dcc_' . $key );
	}
}
register_uninstall_hook( __FILE__, 'dcc_uninstall' );
