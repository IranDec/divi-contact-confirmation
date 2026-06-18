<?php
/**
 * Registers all hooks that intercept Divi contact form submissions.
 *
 * Divi 4 fires  et_pb_contact_form_submit  (AJAX action).
 * Divi 5 fires  divi_contact_form_submitted  (new action introduced in Divi 5).
 * Both hooks are registered so the plugin works across versions without any
 * configuration from the site owner.
 *
 * @author  Mohammad Babaei <https://adschi.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCC_Hooks {

	public static function init() {
		// Divi 4 — fires inside the AJAX handler after validation passes
		add_action( 'et_pb_contact_form_submit', array( __CLASS__, 'handle_divi4' ), 10, 3 );

		// Divi 5 — new dedicated action
		add_action( 'divi_contact_form_submitted', array( __CLASS__, 'handle_divi5' ), 10, 2 );
	}

	/**
	 * Divi 4 handler.
	 *
	 * @param string $et_contact_error  'yes'|'no'
	 * @param array  $fields            Submitted field values keyed by field_id
	 * @param array  $module_settings   Divi module attributes
	 */
	public static function handle_divi4( $et_contact_error, $fields, $module_settings ) {
		if ( 'yes' === $et_contact_error ) {
			return;
		}

		$email = self::extract_email_from_fields( $fields );
		$name  = self::extract_name_from_fields( $fields );

		if ( $email ) {
			DCC_Mailer::send( $email, $name, $fields );
		}
	}

	/**
	 * Divi 5 handler.
	 *
	 * @param array $form_data  Associative array of submitted values
	 * @param array $module     Module config / settings
	 */
	public static function handle_divi5( $form_data, $module ) {
		$fields = isset( $form_data['fields'] ) ? $form_data['fields'] : $form_data;

		$email = self::extract_email_from_fields( $fields );
		$name  = self::extract_name_from_fields( $fields );

		if ( $email ) {
			DCC_Mailer::send( $email, $name, $fields );
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Finds an email address inside submitted field values.
	 * Divi stores fields with keys like "et_pb_contact_email_0", "email", etc.
	 */
	private static function extract_email_from_fields( $fields ) {
		if ( ! is_array( $fields ) ) {
			return '';
		}

		foreach ( $fields as $key => $value ) {
			$key_lower = strtolower( $key );
			if ( false !== strpos( $key_lower, 'email' ) && is_email( $value ) ) {
				return sanitize_email( $value );
			}
		}

		// Fallback: scan all values for something that looks like an email
		foreach ( $fields as $value ) {
			if ( is_string( $value ) && is_email( $value ) ) {
				return sanitize_email( $value );
			}
		}

		return '';
	}

	/**
	 * Finds a name inside submitted field values.
	 */
	private static function extract_name_from_fields( $fields ) {
		if ( ! is_array( $fields ) ) {
			return '';
		}

		$name_keys = array( 'name', 'full_name', 'fullname', 'first_name', 'firstname', 'your_name' );

		foreach ( $fields as $key => $value ) {
			$key_lower = strtolower( $key );
			foreach ( $name_keys as $name_key ) {
				if ( false !== strpos( $key_lower, $name_key ) ) {
					return sanitize_text_field( $value );
				}
			}
		}

		return '';
	}
}
