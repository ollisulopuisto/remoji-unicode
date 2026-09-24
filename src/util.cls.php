<?php
/**
 * Utility class
 *
 * @since 1.0
 */
namespace remoji;

defined( 'WPINC' ) || exit;

class Util extends Instance {
	protected static $_instance;

	/**
	 * Init Utility
	 *
	 * @since 1.3
	 * @access public
	 */
	public function init() {
	}

	/**
	 * Builds an url with an action and a nonce.
	 *
	 * @since  1.4
	 * @access public
	 */
	public static function build_url( $action, $type = false, $is_ajax = false, $page = null, $append_arr = null ) {
		$prefix = '?';

		if ( ! $is_ajax ) {
			if ( $page ) {
				// If use admin url
				if ( $page === true ) {
					$page = 'admin.php';
				} elseif ( strpos( $page, '?' ) !== false ) {
						$prefix = '&';
				}
				$combined = $page . $prefix . Router::ACTION . '=' . $action;
			} else {
				// Current page rebuild URL
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only inspection of the current admin request to build a link / decide asset loading; no state change.
				$params = $_GET;

				if ( ! empty( $params ) ) {
					if ( isset( $params[ Router::ACTION ] ) ) {
						unset( $params[ Router::ACTION ] );
					}
					if ( isset( $params[ Router::NONCE ] ) ) {
						unset( $params[ Router::NONCE ] );
					}
					if ( ! empty( $params ) ) {
						$prefix .= http_build_query( $params ) . '&';
					}
				}
				global $pagenow;
				$combined = $pagenow . $prefix . Router::ACTION . '=' . $action;
			}
		} else {
			$combined = 'admin-ajax.php?action=remoji_ajax&' . Router::ACTION . '=' . $action;
		}

		if ( is_network_admin() ) {
			$prenonce = network_admin_url( $combined );
		} else {
			$prenonce = admin_url( $combined );
		}
		$url = wp_nonce_url( $prenonce, $action, Router::NONCE );

		if ( $type ) {
			// Remove potential param `type` from url
			$url = wp_parse_url( htmlspecialchars_decode( $url ) );
			parse_str( $url['query'], $query );

			$built_arr = array_merge( $query, array( Router::TYPE => $type ) );
			if ( $append_arr ) {
				$built_arr = array_merge( $built_arr, $append_arr );
			}
			$url['query'] = http_build_query( $built_arr );
			self::compatibility();
			$url = http_build_url( $url );
			$url = htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
		}

		return $url;
	}

	/**
	 * Improve compatibility to PHP old versions
	 *
	 * @since  1.4
	 */
	public static function compatibility() {
		require_once REMOJI_DIR . 'lib/php-compatibility.func.php';
	}

	/**
	 * Set seconds/timestamp to readable format
	 *
	 * @since  1.0
	 * @access public
	 */
	public static function readable_time( $seconds_or_timestamp, $timeout = 3600, $forword = false ) {

		if ( strlen( $seconds_or_timestamp ) === 10 ) {
			$seconds = time() - $seconds_or_timestamp;
			if ( $seconds > $timeout ) {
				return gmdate( 'm/d/Y H:i:s', $seconds_or_timestamp + get_option( 'gmt_offset' ) * 60 * 60 );
			}
		} else {
			$seconds = $seconds_or_timestamp;
		}

		$res = '';
		if ( $seconds > 86400 ) {
			$num      = floor( $seconds / 86400 );
			$res     .= $num . 'd';
			$seconds %= 86400;
		}

		if ( $seconds > 3600 ) {
			if ( $res ) {
				$res .= ', ';
			}
			$num      = floor( $seconds / 3600 );
			$res     .= $num . 'h';
			$seconds %= 3600;
		}

		if ( $seconds > 60 ) {
			if ( $res ) {
				$res .= ', ';
			}
			$num      = floor( $seconds / 60 );
			$res     .= $num . 'm';
			$seconds %= 60;
		}

		if ( $seconds > 0 ) {
			if ( $res ) {
				$res .= ' ';
			}
			$res .= $seconds . 's';
		}

		if ( ! $res ) {
			return $forword ? __( 'right now', 'remoji' ) : __( 'just now', 'remoji' );
		}

		/* translators: %s: a human-readable elapsed time duration (e.g. "1d, 2h, 5m"). */
		$res = $forword ? $res : sprintf( __( ' %s ago', 'remoji' ), $res );

		return $res;
	}

	/**
	 * Generate pagination
	 *
	 * @since 1.4
	 * @access public
	 */
	public static function pagination( $total, $limit, $return_offset = false ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only inspection of the current admin request to build a link / decide asset loading; no state change.
		$pagenum = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 1;
		$pagenum = max( 1, $pagenum );

		$offset       = ( $pagenum - 1 ) * $limit;
		$num_of_pages = ceil( $total / $limit );

		if ( $total > 0 && $offset >= $total ) {
			$offset = max( 0, $total - $limit );
		}

		if ( $return_offset ) {
			return $offset;
		}

		$page_links = paginate_links(
			array(
				'base'      => add_query_arg( 'pagenum', '%#%' ),
				'format'    => '',
				'prev_text' => __( '&laquo;', 'remoji' ),
				'next_text' => __( '&raquo;', 'remoji' ),
				'total'     => $num_of_pages,
				'current'   => $pagenum,
			)
		);

		return '<div class="tablenav"><div class="tablenav-pages" style="margin: 1em 0">' . $page_links . '</div></div>';
	}

	/**
	 * Version check
	 *
	 * @since 1.2
	 * @access public
	 */
	public static function version_check( $tag ) {
		return false;
	}

	/**
	 * Deactivate
	 *
	 * @since  1.0
	 * @access public
	 */
	public static function deactivate() {
	}

	/**
	 * Uninstall clearance
	 *
	 * @since  1.0
	 * @access public
	 */
	public static function uninstall() {
		Data::get_instance()->tables_del();
	}

	/**
	 * Activation redirect
	 *
	 * @since  1.0
	 * @access public
	 */
	public static function activate() {
		Data::get_instance()->tables_create();
	}
}
