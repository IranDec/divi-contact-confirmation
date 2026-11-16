<?php
/**
 * Triggers the confirmation email after a Divi contact form was submitted successfully.
 *
 * Since 2.0.0 only Divi's own "form accepted" hooks are used:
 *
 *  - et_pb_contact_form_submit (Divi 4): fired by Divi after its nonce, captcha and spam-protection
 *    checks, with $et_contact_error = true when the form was rejected.
 *  - divi_contact_form_submitted (Divi 5).
 *
 * The 1.x output-buffer and wp_mail() interception layers were removed: they reacted to requests
 * Divi had not accepted and were the entry point for abuse.
 *
 * @author  Mohammad Babaei <https://adschi.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCC_Hooks {

	/** Prevents sending more than one confirmation per HTTP request. */
	private static $sent = false;

	public static function init() {
		add_action( 'et_pb_contact_form_submit', array( __CLASS__, 'handle_divi4' ), 20, 3 );
		add_action( 'divi_contact_form_submitted', array( __CLASS__, 'handle_divi5' ), 20, 2 );
	}

	/**
	 * Divi 4: do_action( 'et_pb_contact_form_submit', $processed_fields_values, $et_contact_error, $contact_form_info ).
	 * (1.x read the arguments in the wrong order, so rejected forms were not recognised.)
	 */
	public static function handle_divi4( $processed_fields, $et_contact_error = false, $contact_form_info = array() ) {
		if ( self::$sent || ! empty( $et_contact_error ) || ! is_array( $processed_fields ) ) {
			return;
		}

		$fields = array();
		$email  = '';
		foreach ( $processed_fields as $key => $field ) {
			$value = is_array( $field ) && isset( $field['value'] ) ? (string) $field['value'] : ( is_string( $field ) ? $field : '' );
			$type  = is_array( $field ) && isset( $field['type'] ) ? (string) $field['type'] : '';
			$fields[ sanitize_key( (string) $key ) ] = sanitize_textarea_field( $value );
			if ( '' === $email && ( 'email' === $type || false !== strpos( strtolower( (string) $key ), 'email' ) ) && is_email( trim( $value ) ) ) {
				$email = sanitize_email( trim( $value ) );
			}
		}
		if ( '' === $email ) {
			$email = self::extract_email_from_fields( $fields );
		}

		if ( $email ) {
			self::$sent = true;
			DCC_Mailer::send( $email, $fields );
		}
	}

	/** Divi 5. */
	public static function handle_divi5( $form_data, $module = null ) {
		if ( self::$sent || ! is_array( $form_data ) ) {
			return;
		}

		$raw    = isset( $form_data['fields'] ) && is_array( $form_data['fields'] ) ? $form_data['fields'] : $form_data;
		$fields = array();
		foreach ( $raw as $key => $value ) {
			if ( is_array( $value ) && isset( $value['value'] ) ) {
				$value = $value['value'];
			}
			if ( is_string( $value ) ) {
				$fields[ sanitize_key( (string) $key ) ] = sanitize_textarea_field( $value );
			}
		}
		$email = self::extract_email_from_fields( $fields );

		if ( $email ) {
			self::$sent = true;
			DCC_Mailer::send( $email, $fields );
		}
	}

	/** Finds a valid email address in a field array (a key containing "email" first). */
	private static function extract_email_from_fields( $fields ) {
		foreach ( $fields as $key => $value ) {
			if ( false !== strpos( strtolower( (string) $key ), 'email' ) && is_email( trim( (string) $value ) ) ) {
				return sanitize_email( trim( (string) $value ) );
			}
		}
		foreach ( $fields as $value ) {
			if ( is_string( $value ) && is_email( trim( $value ) ) ) {
				return sanitize_email( trim( $value ) );
			}
		}

		return '';
	}
}
