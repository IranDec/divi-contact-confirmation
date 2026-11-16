<?php
/**
 * Decides whether a confirmation email may be sent.
 *
 * 2.0.0: built against the abuse seen in June 2026 (bots from ~80 IPs put
 * "Join TRONYX >>> https://..." into the name field and victims' addresses into the email field):
 *  - links / spam markers in any field, tagged addresses (victim+yoursite@...), own domain;
 *  - one confirmation per address per 24 h (gmail dots and +tags count as the same address);
 *  - total limits per hour and per day - when reached, confirmations pause and the admin is warned
 *    once a day (an IP limit alone does not help against botnets).
 * The 1.x reCAPTCHA (which let missing tokens through) was removed - use Divi's own spam
 * protection in the contact form module; Divi rejects the form before this plugin runs.
 *
 * @author  Mohammad Babaei <https://adschi.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCC_Security {

	const OPT_SENT  = 'dcc_sent_times';
	const OPT_ALERT = 'dcc_alert_day';

	/**
	 * @return true|string true = allowed, otherwise a reason code (see reason_label())
	 */
	public static function check( $email, $fields, $bypass_limits = false ) {
		if ( ! get_option( 'dcc_sec_enabled', '1' ) ) {
			return 'plugin_disabled';
		}
		$email = strtolower( trim( (string) $email ) );
		if ( '' === $email || ! is_email( $email ) ) {
			return 'invalid_email';
		}

		list( $local, $domain ) = explode( '@', $email, 2 );
		$site = preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
		if ( $site && ( $domain === $site || substr( $domain, -strlen( '.' . $site ) ) === '.' . $site ) ) {
			return 'own_domain';
		}
		$site_word = preg_replace( '/[^a-z0-9]/', '', strtok( $site, '.' ) );
		$tag       = false !== strpos( $local, '+' ) ? preg_replace( '/[^a-z0-9]/', '', substr( $local, strpos( $local, '+' ) + 1 ) ) : '';
		if ( '' !== $tag && strlen( $site_word ) >= 4 && false !== strpos( $tag, $site_word ) ) {
			return 'tagged_address';
		}

		foreach ( (array) $fields as $key => $value ) {
			$value = (string) $value;
			if ( preg_match( '~https?://|www\.|>>>|\[url|<a\s|\b[a-z0-9-]{2,}\.(?:cc|ru|cn|xyz|top|icu|click|link|shop|online|site|live|bond|sbs|cfd|tk|gq|ml)\b~i', $value ) ) {
				return 'link_in_form';
			}
			if ( false !== strpos( strtolower( (string) $key ), 'name' ) && ( strlen( $value ) > 80 || false !== strpos( $value, '@' ) ) ) {
				return 'suspicious_name';
			}
		}

		$blocked_domains = get_option( 'dcc_sec_blocked_domains', '' );
		if ( $blocked_domains && true !== ( $r = self::check_blocked_domain( $domain, $blocked_domains ) ) ) {
			return $r;
		}
		$blocked_keywords = get_option( 'dcc_sec_blocked_keywords', '' );
		if ( $blocked_keywords && true !== ( $r = self::check_blocked_keywords( $fields, $blocked_keywords ) ) ) {
			return $r;
		}
		if ( get_option( 'dcc_sec_check_mx', '0' ) && function_exists( 'checkdnsrr' ) && ! checkdnsrr( $domain, 'MX' ) ) {
			return 'no_mx_record';
		}

		if ( $bypass_limits ) {
			return true;
		}

		if ( get_transient( 'dcc_addr_' . self::address_key( $email ) ) ) {
			return 'address_limit';
		}

		$ip_limit = (int) get_option( 'dcc_sec_rate_limit', 3 );
		if ( $ip_limit > 0 ) {
			$key   = 'dcc_rl_' . md5( self::visitor_ip() );
			$count = (int) get_transient( $key );
			if ( $count >= $ip_limit ) {
				return 'rate_limit_exceeded';
			}
			set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		}

		$per_hour = (int) get_option( 'dcc_sec_limit_hour', 10 );
		$per_day  = (int) get_option( 'dcc_sec_limit_day', 40 );
		$times    = self::recent_sends();
		$hour     = count( array_filter( $times, function ( $t ) {
			return $t > time() - HOUR_IN_SECONDS;
		} ) );
		if ( ( $per_hour > 0 && $hour >= $per_hour ) || ( $per_day > 0 && count( $times ) >= $per_day ) ) {
			self::alert_admin( $per_hour, $per_day );

			return 'total_limit';
		}

		return true;
	}

	/** Called after a confirmation was sent. */
	public static function record_sent( $email ) {
		set_transient( 'dcc_addr_' . self::address_key( $email ), 1, DAY_IN_SECONDS );
		$times   = self::recent_sends();
		$times[] = time();
		update_option( self::OPT_SENT, array_slice( $times, -500 ), false );
	}

	/** gmail ignores dots and "+tags": they count as one address. */
	private static function address_key( $email ) {
		list( $local, $domain ) = explode( '@', strtolower( trim( $email ) ), 2 );
		$local = preg_replace( '/\+.*$/', '', $local );
		if ( in_array( $domain, array( 'gmail.com', 'googlemail.com' ), true ) ) {
			$local  = str_replace( '.', '', $local );
			$domain = 'gmail.com';
		}

		return md5( $local . '@' . $domain );
	}

	private static function recent_sends() {
		$times = get_option( self::OPT_SENT, array() );

		return array_values( array_filter( is_array( $times ) ? $times : array(), function ( $t ) {
			return (int) $t > time() - DAY_IN_SECONDS;
		} ) );
	}

	private static function alert_admin( $per_hour, $per_day ) {
		if ( get_option( self::OPT_ALERT ) === gmdate( 'Y-m-d' ) ) {
			return;
		}
		update_option( self::OPT_ALERT, gmdate( 'Y-m-d' ), false );
		wp_mail(
			get_option( 'admin_email' ),
			sprintf( '[%s] %s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), __( 'Contact form confirmations paused', 'divi-contact-confirmation' ) ),
			sprintf(
				/* translators: 1: limit per hour, 2: limit per day */
				__( "Unusually many contact forms were submitted, so confirmation emails to visitors are paused (limit: %1\$d per hour / %2\$d per day).\n\nThe form messages themselves still reach you as usual. Details: Settings > Contact Confirmation > Logs.", 'divi-contact-confirmation' ),
				$per_hour,
				$per_day
			)
		);
	}

	private static function check_blocked_domain( $domain, $blocked_domains_raw ) {
		$blocked = array_filter( array_map( 'trim', explode( ',', strtolower( $blocked_domains_raw ) ) ) );
		foreach ( $blocked as $blocked_domain ) {
			if ( $domain === $blocked_domain || substr( $domain, -strlen( '.' . $blocked_domain ) ) === '.' . $blocked_domain ) {
				return 'blocked_domain';
			}
		}

		return true;
	}

	private static function check_blocked_keywords( $fields, $blocked_keywords_raw ) {
		$keywords = array_filter( array_map( 'trim', explode( ',', strtolower( $blocked_keywords_raw ) ) ) );
		foreach ( (array) $fields as $value ) {
			$value = strtolower( (string) $value );
			foreach ( $keywords as $keyword ) {
				if ( '' !== $keyword && false !== strpos( $value, $keyword ) ) {
					return 'blocked_keyword';
				}
			}
		}

		return true;
	}

	/**
	 * The visitor's IP. Proxy headers are only trusted for Cloudflare (CF-Connecting-IP);
	 * X-Forwarded-For can be set by anyone and made the 1.x IP limit easy to bypass.
	 */
	private static function visitor_ip() {
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR' ) as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = trim( sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/** Human-readable label for a block reason code. */
	public static function reason_label( $reason ) {
		$labels = array(
			'plugin_disabled'     => __( 'Plugin disabled', 'divi-contact-confirmation' ),
			'invalid_email'       => __( 'No valid email address', 'divi-contact-confirmation' ),
			'own_domain'          => __( 'Address of this site', 'divi-contact-confirmation' ),
			'tagged_address'      => __( 'Spam: address tagged with the site name', 'divi-contact-confirmation' ),
			'link_in_form'        => __( 'Spam: link or spam text in the form', 'divi-contact-confirmation' ),
			'suspicious_name'     => __( 'Spam: suspicious name', 'divi-contact-confirmation' ),
			'address_limit'       => __( 'Address already confirmed in the last 24 hours', 'divi-contact-confirmation' ),
			'rate_limit_exceeded' => __( 'Rate limit exceeded', 'divi-contact-confirmation' ),
			'total_limit'         => __( 'Total limit reached - confirmations paused', 'divi-contact-confirmation' ),
			'blocked_domain'      => __( 'Blocked email domain', 'divi-contact-confirmation' ),
			'blocked_keyword'     => __( 'Blocked keyword found', 'divi-contact-confirmation' ),
			'no_mx_record'        => __( 'No MX record for domain', 'divi-contact-confirmation' ),
		);

		return isset( $labels[ $reason ] ) ? $labels[ $reason ] : $reason;
	}
}
