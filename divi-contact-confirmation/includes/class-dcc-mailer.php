<?php
/**
 * Builds and sends the confirmation email to the form submitter.
 *
 * @author  Mohammad Babaei <https://adschi.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCC_Mailer {

	/**
	 * Send a confirmation email.
	 *
	 * @param string $to_email  Recipient email address
	 * @param string $to_name   Recipient display name (may be empty)
	 * @param array  $fields    All submitted form fields (used for placeholders)
	 */
	public static function send( $to_email, $to_name, $fields = array() ) {
		$subject    = get_option( 'dcc_subject', '' );
		$body       = get_option( 'dcc_body', '' );
		$from_name  = get_option( 'dcc_from_name', get_bloginfo( 'name' ) );
		$from_email = get_option( 'dcc_from_email', get_bloginfo( 'admin_email' ) );

		// Replace built-in placeholders
		$replacements = array(
			'{name}'      => $to_name ?: $to_email,
			'{email}'     => $to_email,
			'{site_name}' => get_bloginfo( 'name' ),
			'{site_url}'  => get_bloginfo( 'url' ),
			'{date}'      => wp_date( get_option( 'date_format' ) ),
			'{time}'      => wp_date( get_option( 'time_format' ) ),
		);

		// Also expose every submitted field as {field_key}
		if ( is_array( $fields ) ) {
			foreach ( $fields as $key => $value ) {
				$placeholder                  = '{' . sanitize_key( $key ) . '}';
				$replacements[ $placeholder ] = is_string( $value ) ? sanitize_text_field( $value ) : '';
			}
		}

		$subject = str_replace( array_keys( $replacements ), array_values( $replacements ), $subject );
		$body    = str_replace( array_keys( $replacements ), array_values( $replacements ), $body );

		$subject = apply_filters( 'dcc_confirmation_subject', $subject, $to_email, $fields );
		$body    = apply_filters( 'dcc_confirmation_body', $body, $to_email, $fields );

		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_email ),
		);
		$headers = apply_filters( 'dcc_confirmation_headers', $headers, $to_email, $fields );

		// Security checks — run after placeholders are resolved so keyword
		// matching sees the final field values, not raw IDs.
		$security_result = DCC_Security::check( $to_email, $fields );

		// Allow developer override
		$security_result = apply_filters( 'dcc_should_send', $security_result, $to_email, $fields );

		if ( true !== $security_result ) {
			if ( get_option( 'dcc_sec_log_blocked', '1' ) ) {
				DCC_Logger::write( array(
					'recipient_email' => $to_email,
					'recipient_name'  => $to_name,
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

		if ( ! $sent ) {
			global $phpmailer;
			$error_msg = isset( $phpmailer ) ? $phpmailer->ErrorInfo : '';
			error_log( sprintf( '[DCC] wp_mail failed — to: %s | error: %s', $to_email, $error_msg ) );
		}

		DCC_Logger::write( array(
			'recipient_email' => $to_email,
			'recipient_name'  => $to_name,
			'subject'         => $subject,
			'sent'            => $sent,
			'error_message'   => $error_msg,
		) );

		return $sent;
	}
}
