<?php
/**
 * Intercepts Divi contact form submissions and triggers the confirmation email.
 *
 * Detection strategy (three layers, first match wins):
 *
 *  1. wp_mail filter  — most reliable across ALL Divi versions.
 *     Divi sends its own admin-notification email with a Reply-To header set to
 *     the submitter's address.  We read that header and also pull the form fields
 *     straight from $_POST before Divi clears them.
 *
 *  2. et_pb_contact_form_submit action  — Divi 4 named hook (fires when available).
 *
 *  3. divi_contact_form_submitted action  — Divi 5 named hook (fires when available).
 *
 * A static flag ensures we never send more than one confirmation per request
 * even if multiple layers fire.
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
		// Layer 1 — wp_mail filter (runs for every Divi version)
		add_filter( 'wp_mail', array( __CLASS__, 'intercept_via_wp_mail' ), 5 );

		// Layer 2 — Divi 4 named action
		add_action( 'et_pb_contact_form_submit', array( __CLASS__, 'handle_divi4' ), 10, 3 );

		// Layer 3 — Divi 5 named action
		add_action( 'divi_contact_form_submitted', array( __CLASS__, 'handle_divi5' ), 10, 2 );
	}

	// -------------------------------------------------------------------------
	// Layer 1: wp_mail filter
	// -------------------------------------------------------------------------

	/**
	 * Fires on every wp_mail() call.  We only act when we detect that this call
	 * is Divi sending its admin notification (identified by the AJAX POST action
	 * and the presence of a Reply-To header containing the submitter's address).
	 *
	 * @param  array $args  wp_mail() argument array (unchanged on return).
	 * @return array
	 */
	public static function intercept_via_wp_mail( $args ) {
		if ( self::$sent ) {
			return $args;
		}

		// Only act during a Divi contact-form AJAX request
		if ( ! self::is_divi_form_request() ) {
			return $args;
		}

		// Parse the Reply-To header — Divi sets it to the submitter's email
		$headers = is_array( $args['headers'] )
			? $args['headers']
			: array_filter( array_map( 'trim', explode( "\n", $args['headers'] ) ) );

		$submitter_email = '';
		$submitter_name  = '';

		foreach ( $headers as $header ) {
			if ( stripos( trim( $header ), 'Reply-To:' ) !== 0 ) {
				continue;
			}

			// Format: "Reply-To: Name <email>" or "Reply-To: email"
			if ( preg_match( '/<([^>]+)>/', $header, $m ) && is_email( $m[1] ) ) {
				$submitter_email = sanitize_email( $m[1] );
				if ( preg_match( '/Reply-To:\s*([^<]+)\s*</i', $header, $nm ) ) {
					$submitter_name = sanitize_text_field( trim( $nm[1], " \t\"'" ) );
				}
			} elseif ( preg_match( '/Reply-To:\s*(\S+@\S+\.\S+)/i', $header, $m )
			           && is_email( trim( $m[1] ) ) ) {
				$submitter_email = sanitize_email( trim( $m[1] ) );
			}

			if ( $submitter_email ) {
				break;
			}
		}

		// Fallback: scan $_POST for an email address (Divi 4 standard field names)
		if ( ! $submitter_email ) {
			$fields          = self::fields_from_post();
			$submitter_email = self::extract_email_from_fields( $fields );
			$submitter_name  = self::extract_name_from_fields( $fields );
		}

		if ( $submitter_email ) {
			self::$sent = true;
			$fields     = self::fields_from_post();
			DCC_Mailer::send( $submitter_email, $submitter_name, $fields );
		}

		return $args;
	}

	// -------------------------------------------------------------------------
	// Layer 2: Divi 4 named action
	// -------------------------------------------------------------------------

	/**
	 * @param string $et_contact_error  'yes' = validation failed, 'no' = success
	 * @param array  $fields            Submitted field values keyed by field_id
	 * @param mixed  $module_settings   Divi module attributes
	 */
	public static function handle_divi4( $et_contact_error, $fields, $module_settings ) {
		if ( self::$sent || 'yes' === $et_contact_error ) {
			return;
		}

		$email = self::extract_email_from_fields( $fields );
		$name  = self::extract_name_from_fields( $fields );

		if ( $email ) {
			self::$sent = true;
			DCC_Mailer::send( $email, $name, $fields );
		}
	}

	// -------------------------------------------------------------------------
	// Layer 3: Divi 5 named action
	// -------------------------------------------------------------------------

	/**
	 * @param array $form_data  Associative array of submitted values
	 * @param array $module     Module config / settings
	 */
	public static function handle_divi5( $form_data, $module ) {
		if ( self::$sent ) {
			return;
		}

		$fields = isset( $form_data['fields'] ) ? $form_data['fields'] : $form_data;
		$email  = self::extract_email_from_fields( $fields );
		$name   = self::extract_name_from_fields( $fields );

		if ( $email ) {
			self::$sent = true;
			DCC_Mailer::send( $email, $name, $fields );
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns true when the current request is a Divi contact-form submission.
	 */
	private static function is_divi_form_request() {
		// AJAX requests triggered by Divi 4
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			// phpcs:ignore WordPress.Security.NonceVerification
			$action = isset( $_POST['action'] ) ? sanitize_key( $_POST['action'] ) : '';
			if ( 'et_pb_contact_form_submit' === $action ) {
				return true;
			}
		}

		// Divi 5 may use a REST endpoint or a different POST key — check for the
		// presence of Divi's own contact-form nonce field as a generic signal.
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( ! empty( $_POST['et_pb_contactform_submit'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Sanitise and return all POST fields that look like Divi form input.
	 * Skips WordPress/Divi internal keys (nonces, action, etc.).
	 */
	private static function fields_from_post() {
		$skip_prefixes = array( '_wpnonce', '_wp_http_referer', 'action', 'et_pb_contactform_submit' );
		$fields        = array();

		// phpcs:ignore WordPress.Security.NonceVerification
		foreach ( $_POST as $key => $value ) {
			if ( in_array( $key, $skip_prefixes, true ) ) {
				continue;
			}
			if ( is_string( $value ) ) {
				$fields[ sanitize_key( $key ) ] = sanitize_text_field( wp_unslash( $value ) );
			}
		}

		return $fields;
	}

	/**
	 * Finds an email address in an associative array of field values.
	 * Divi field keys: et_pb_contact_email_0, email, etc.
	 */
	private static function extract_email_from_fields( $fields ) {
		if ( ! is_array( $fields ) ) {
			return '';
		}

		// Prefer a key that contains "email"
		foreach ( $fields as $key => $value ) {
			if ( false !== strpos( strtolower( $key ), 'email' ) && is_email( $value ) ) {
				return sanitize_email( $value );
			}
		}

		// Fallback: scan all values
		foreach ( $fields as $value ) {
			if ( is_string( $value ) && is_email( $value ) ) {
				return sanitize_email( $value );
			}
		}

		return '';
	}

	/**
	 * Finds a name in an associative array of field values.
	 */
	private static function extract_name_from_fields( $fields ) {
		if ( ! is_array( $fields ) ) {
			return '';
		}

		$name_keywords = array( 'name', 'full_name', 'fullname', 'first_name', 'firstname', 'your_name', 'vorname', 'nachname' );

		foreach ( $fields as $key => $value ) {
			$key_lower = strtolower( $key );
			foreach ( $name_keywords as $kw ) {
				if ( false !== strpos( $key_lower, $kw ) ) {
					return sanitize_text_field( $value );
				}
			}
		}

		return '';
	}
}
