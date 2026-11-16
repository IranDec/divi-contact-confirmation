<?php
/**
 * Builds and sends the confirmation email to the form submitter.
 *
 * Security rule (2.0.0): the email never contains anything the visitor typed. Only site-level
 * placeholders are replaced; {name}, {email} and form-field placeholders are removed. In 1.x a
 * bot could put scam text and links into the "name" field and a victim's address into "email",
 * and the site mailed the scam with its own good reputation.
 *
 * @author  Mohammad Babaei <https://adschi.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCC_Mailer {

	/** The only placeholders that are replaced. */
	const SAFE_PLACEHOLDERS = array( '{site_name}', '{site_url}', '{date}', '{time}' );

	/**
	 * Send a confirmation email.
	 *
	 * @param string $to_email      Recipient email address
	 * @param array  $fields        Submitted form fields - only checked by the security rules, never mailed
	 * @param bool   $bypass_limits True for the admin test email (limits do not apply)
	 *
	 * @return bool sent
	 */
	public static function send( $to_email, $fields = array(), $bypass_limits = false ) {
		$to_email   = sanitize_email( (string) $to_email );
		$fields     = is_array( $fields ) ? $fields : array();
		$from_name  = get_option( 'dcc_from_name', get_bloginfo( 'name' ) );
		$from_email = get_option( 'dcc_from_email', get_bloginfo( 'admin_email' ) );

		$replacements = array(
			'{site_name}' => get_bloginfo( 'name' ),
			'{site_url}'  => home_url( '/' ),
			'{date}'      => wp_date( get_option( 'date_format' ) ),
			'{time}'      => wp_date( get_option( 'time_format' ) ),
		);
		$subject = strtr( self::clean_template( get_option( 'dcc_subject', '' ) ), $replacements );
		$body    = strtr( self::clean_template( get_option( 'dcc_body', '' ) ), $replacements );

		// Filters receive only the recipient address (no form fields since 2.0.0).
		$subject = apply_filters( 'dcc_confirmation_subject', $subject, $to_email );
		$body    = apply_filters( 'dcc_confirmation_body', $body, $to_email );

		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			sprintf( 'From: %s <%s>', str_replace( array( "\r", "\n", '<', '>' ), '', $from_name ), sanitize_email( $from_email ) ),
			'Auto-Submitted: auto-replied',
			'X-Auto-Response-Suppress: All',
		);
		$headers = apply_filters( 'dcc_confirmation_headers', $headers, $to_email );

		$security_result = DCC_Security::check( $to_email, $fields, $bypass_limits );
		$security_result = apply_filters( 'dcc_should_send', $security_result, $to_email );

		if ( true !== $security_result ) {
			if ( get_option( 'dcc_sec_log_blocked', '1' ) ) {
				DCC_Logger::write( array(
					'recipient_email' => $to_email,
					'recipient_name'  => '',
					'subject'         => $subject,
					'sent'            => false,
					'status'          => 'blocked',
					'error_message'   => DCC_Security::reason_label( $security_result ),
				) );
			}

			return false;
		}

		$sent      = wp_mail( $to_email, $subject, $body, $headers );
		$error_msg = '';

		if ( $sent ) {
			DCC_Security::record_sent( $to_email );
		} else {
			global $phpmailer;
			$error_msg = isset( $phpmailer ) ? $phpmailer->ErrorInfo : '';
			error_log( sprintf( '[DCC] wp_mail failed - to: %s | error: %s', $to_email, $error_msg ) );
		}

		DCC_Logger::write( array(
			'recipient_email' => $to_email,
			'recipient_name'  => '',
			'subject'         => $subject,
			'sent'            => $sent,
			'error_message'   => $error_msg,
		) );

		return $sent;
	}

	/**
	 * Removes every placeholder except the safe ones and tidies what is left
	 * ("Hello {name}," becomes "Hello,").
	 */
	public static function clean_template( $text ) {
		$text = preg_replace_callback( '/[ \t]*\{[a-z0-9_\-]+\}/i', function ( $m ) {
			return in_array( strtolower( trim( $m[0] ) ), self::SAFE_PLACEHOLDERS, true ) ? $m[0] : '';
		}, (string) $text );
		// Leftovers of removed placeholders: "( , )", ", ,".
		$text = preg_replace( '/\(\s*(?:[,;]\s*)*\)/', '', $text );
		$text = preg_replace( '/,(?:[ \t]*,)+/', ',', $text );

		return preg_replace( '/[ \t]+([,.!?;:])/', '$1', $text );
	}
}
