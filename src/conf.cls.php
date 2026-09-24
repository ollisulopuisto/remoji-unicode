<?php
/**
 * Config class
 *
 * @since 1.2
 */
namespace remoji;

defined( 'WPINC' ) || exit;

class Conf extends Instance {
	protected static $_instance;

	private $_options = array();

	public static $_default_options = array(
		'_ver'                      => '',

		'gdpr'                      => false,
		'guest'                     => true,
		'post_emoji'                => true,
		'post_emoji_auto'           => true,
		'comment_emoji'             => true,
		'max_emoji_per_ip'          => 5,
		'reaction_disabled_types'   => array(),
		'emoji_whitelist'           => array(),
		'emoji_custom'              => '', // Unicode fork: extra "name = codepoint" lines added to the picker
		'reaction_withdraw'         => true,
		'reaction_notify'           => false,
		'show_reactors'             => false,

		'postview'                  => false,
		'postview_delay'            => 2,
		'postview_tpl'              => '<span class="meta-icon"><span class="dashicons dashicons-visibility"></span></span><span class="meta-text remoji_counter" data-remoji_counter="{post_id}">{num}</span>',
		'postview_show_in_content'  => false,
		'postview_show_in_themebar' => true,

		'disable_comment'           => array(),
	);

	protected function __construct() {
	}

	/**
	 * Init config
	 *
	 * @since  1.2
	 * @access public
	 */
	public function init() {
		// Load all options
		$options = array();
		foreach ( self::$_default_options as $k => $v ) {
			$options[ $k ] = $this->_get_option( $k, $v );
		}

		$this->_options = $options;

		// Update options if not exists
		! defined( 'REMOJI_CUR_V' ) && define( 'REMOJI_CUR_V', $this->_options['_ver'] );

		if ( ! REMOJI_CUR_V || REMOJI_CUR_V !== Core::VER ) {
			if ( ! REMOJI_CUR_V ) {
				Util::version_check( 'new' );
			} else {
				// DB update
				Data::get_instance()->conf_upgrade();
			}

			foreach ( self::$_default_options as $k => $v ) {
				self::add( $k, $v );
			}

			self::update( '_ver', Core::VER );
		}
	}

	/**
	 * Get one current option
	 *
	 * @since  1.2
	 * @access public
	 */
	public static function val( $id ) {
		$instance = self::get_instance();
		if ( isset( $instance->_options[ $id ] ) ) {
			return $instance->_options[ $id ];
		}

		return null;
	}

	/**
	 * Get all options
	 *
	 * @since  1.2
	 * @access private
	 */
	public function get_options() {
		return $this->_options;
	}

	/**
	 * Add one option
	 *
	 * @since  1.2
	 * @access public
	 */
	public static function add( $id, $v ) {
		add_option( 'remoji.' . $id, $v );
	}

	/**
	 * Delete one option
	 *
	 * @since  1.2
	 * @access public
	 */
	public static function delete( $id ) {
		delete_option( 'remoji.' . $id );
	}

	/**
	 * Get option from DB
	 *
	 * @since  1.2
	 * @access private
	 */
	private function _get_option( $id, $default_v = false ) {
		return get_option( 'remoji.' . $id, $default_v );
	}

	/**
	 * Update option of remoji
	 *
	 * @since  1.2
	 * @access public
	 */
	public static function update( $id, $data ) {
		if ( ! array_key_exists( $id, self::$_default_options ) ) {
			defined( 'debug' ) && debug( 'updated failed due to missing [id] ' . $id );
			return;
		}

		defined( 'debug' ) && debug( 'update setting [id] ' . $id );

		// typecast
		$default_v = self::$_default_options[ $id ];
		if ( is_bool( $default_v ) ) {
			$data = (bool) $data;
		} elseif ( is_array( $default_v ) ) {
			if ( ! is_array( $data ) ) {
				$data = explode( "\n", $data );
				$data = array_filter( $data );
			}
		} elseif ( ! is_string( $default_v ) ) {
			$data = (int) $data;
		}

		update_option( 'remoji.' . $id, $data );

		// Change current setting
		self::get_instance()->_options[ $id ] = $data;
	}
}
