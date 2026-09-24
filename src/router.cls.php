<?php
/**
 * Router class
 *
 * @since 1.2
 */
namespace remoji;

defined( 'WPINC' ) || exit;

class Router extends Instance {
	protected static $_instance;

	const NONCE  = 'remoji_nonce';
	const ACTION = 'remoji_action';
	const TYPE   = 'remoji_type';
	const I      = 'remoji_i';

	const ACTION_REACTION = 'reaction';

	private static $_action;

	/**
	 * Init
	 */
	public function init() {
		add_action( 'init', array( $this, 'handler' ) );
	}

	/**
	 * Auto handler in `after_user_init`
	 *
	 * @since  1.5
	 */
	public function handler() {
		$cls = self::get_action();
		if ( ! $cls ) {
			return;
		}

		$cls = __NAMESPACE__ . '\\' . $cls;

		if ( ! method_exists( $cls, 'handler' ) ) {
			return;
		}

		$cls::handler();

		self::redirect();
	}

	/**
	 * Redirect page and drop self params
	 *
	 * @since  1.2
	 */
	public static function redirect( $url = false ) {
		global $pagenow;
		$qs = '';
		if ( ! $url ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- redirect() runs only after the nonce-gated action flow in handler(); here it merely strips the plugin's own query args to rebuild a clean redirect URL (no state change).
			if ( ! empty( $_GET ) ) {
				if ( isset( $_GET[ self::ACTION ] ) ) {
					unset( $_GET[ self::ACTION ] );
				}
				if ( isset( $_GET[ self::NONCE ] ) ) {
					unset( $_GET[ self::NONCE ] );
				}
				if ( isset( $_GET[ self::TYPE ] ) ) {
					unset( $_GET[ self::TYPE ] );
				}
				if ( isset( $_GET[ self::I ] ) ) {
					unset( $_GET[ self::I ] );
				}
				if ( ! empty( $_GET ) ) {
					$qs = '?' . http_build_query( $_GET );
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			if ( is_network_admin() ) {
				$url = network_admin_url( $pagenow . $qs );
			} else {
				$url = admin_url( $pagenow . $qs );
			}
		}

		wp_safe_redirect( $url );
		exit();
	}

	/**
	 * Parse action
	 *
	 * @since  1.2
	 */
	public static function get_action() {
		if ( ! isset( self::$_action ) ) {
			self::$_action = false;
			self::get_instance()->verify_action();
			if ( self::$_action ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- guarded dev-only debug output.
				defined( 'debug' ) && debug( 'remoji action verified: ' . var_export( self::$_action, true ) );
			}
		}
		return self::$_action;
	}

	/**
	 * Verify action
	 *
	 * @since  1.2
	 */
	private function verify_action() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the request is authenticated by verify_nonce() (wp_verify_nonce) below before any action runs; the sniff cannot trace the custom wrapper.
		if ( empty( $_REQUEST[ self::ACTION ] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_REQUEST[ self::ACTION ] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! $this->verify_nonce( $action ) ) {
			return;
		}

		$_can_option = current_user_can( 'manage_options' );

		switch ( $action ) {
			case self::ACTION_REACTION:
				if ( $_can_option ) {
					self::$_action = $action;
				}
				return;

			default:
				defined( 'debug' ) && debug( 'remoji match falied: ' . $action );
				return;
		}
	}

	/**
	 * Verify nonce
	 *
	 * @since  1.2
	 */
	private function verify_nonce( $action ) {
		if ( ! isset( $_REQUEST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ self::NONCE ] ) ), $action ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get type value
	 *
	 * @since 1.2
	 * @access public
	 */
	public static function verify_type() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verify_type() is only reached from the nonce-verified reaction action flow; the type is a non-authoritative routing hint.
		if ( empty( $_REQUEST[ self::TYPE ] ) ) {
			defined( 'debug' ) && debug( 'no type', 2 );
			return false;
		}

		defined( 'debug' ) && debug( 'parsed type: ' . sanitize_text_field( wp_unslash( $_REQUEST[ self::TYPE ] ) ), 2 );

		return sanitize_text_field( wp_unslash( $_REQUEST[ self::TYPE ] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}
}
