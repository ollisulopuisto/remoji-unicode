<?php
/**
 * IP class
 *
 * @since 1.0
 */
namespace remoji;

defined( 'WPINC' ) || exit;

class IP extends Instance {
	protected static $_instance;

	/**
	 * Get visitor's IP
	 *
	 * @since  1.0
	 * @access public
	 */
	public static function me() {
		$candidates = array();

		// REMOTE_ADDR is the only IP the client can't spoof, so it's the default source (protects the
		// per-IP cap + guest reaction ownership). Sites behind a trusted CDN/proxy can opt into the
		// forwarded headers via the `remoji_trust_proxy_headers` filter.
		if ( apply_filters( 'remoji_trust_proxy_headers', false ) && function_exists( 'apache_request_headers' ) ) {
			$apache_headers = apache_request_headers();
			if ( ! empty( $apache_headers['True-Client-IP'] ) ) {
				$candidates[] = $apache_headers['True-Client-IP'];
			}
			if ( ! empty( $apache_headers['X-Forwarded-For'] ) ) {
				$forwarded    = explode( ',', $apache_headers['X-Forwarded-For'] );
				$candidates[] = $forwarded[0];
			}
		}

		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$candidates[] = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		// Return the first syntactically valid IP so a spoofed/garbage header can never become the stored key
		foreach ( $candidates as $candidate ) {
			$candidate = trim( $candidate );
			// Strip a trailing port from IPv4 (e.g. 1.2.3.4:5678)
			$candidate = preg_replace( '/^(\d+\.\d+\.\d+\.\d+):\d+$/', '\1', $candidate );
			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Keyed hash of an IP (HMAC-SHA256, key derived from the site's secret salts).
	 *
	 * Raw IPs are never stored or sent anywhere. The hash is only used as an opaque key for the
	 * per-IP reaction cap, guest withdraw ownership and the request rate limit. Unlike the old
	 * unkeyed md5 (trivially brute-forced over the IPv4 space) it can't be reversed without the salt.
	 * Note: rotating the WordPress salts changes every hash, so guests lose ownership of older reactions.
	 *
	 * @since 2.6.2
	 * @access public
	 */
	public static function hash( $ip = false ) {
		if ( $ip === false ) {
			$ip = self::me();
		}

		return hash_hmac( 'sha256', 'remoji-ip|' . $ip, self::_hash_key() );
	}

	/**
	 * Hash of a pre-2.6.2 GDPR-mode (md5) row, as re-keyed by the 2.6.2 migration.
	 * Lets guests keep withdraw ownership / cap accounting for reactions made before the upgrade.
	 * The md5 is computed in memory only and never stored.
	 *
	 * @since 2.6.2
	 * @access public
	 */
	public static function legacy_md5_hash( $md5 ) {
		return hash_hmac( 'sha256', 'remoji-md5|' . strtolower( $md5 ), self::_hash_key() );
	}

	/**
	 * Every stored key that may identify the current visitor's IP (current HMAC + re-keyed legacy md5).
	 *
	 * @since 2.6.2
	 * @access public
	 */
	public static function owner_keys( $ip = false ) {
		if ( $ip === false ) {
			$ip = self::me();
		}

		return array( self::hash( $ip ), self::legacy_md5_hash( md5( $ip ) ) );
	}

	/**
	 * Whether a stored value already is a 2.6.2+ HMAC.
	 *
	 * @since 2.6.2
	 */
	public static function is_hash( $val ) {
		return is_string( $val ) && (bool) preg_match( '/^[0-9a-f]{64}$/', $val );
	}

	/**
	 * HMAC key. Filterable so sites can pin a dedicated secret (e.g. a constant in wp-config.php).
	 *
	 * @since 2.6.2
	 */
	private static function _hash_key() {
		return (string) apply_filters( 'remoji_ip_hash_key', wp_salt( 'auth' ) . 'remoji_ip_hmac' );
	}
}
