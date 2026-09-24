<?php
/**
 * Data structure class
 *
 * @since 1.2
 */
namespace remoji;

defined( 'WPINC' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- all queries target the plugin's own {prefix}remoji_history table; values are bound via $wpdb->prepare(), and a bespoke table has no WordPress object-cache API.

class Data extends Instance {
	protected static $_instance;

	private $_db_updater = array(
		'2.5'   => array(
			'remoji_update_2_5',
		),
		'2.6.2' => array(
			'remoji_update_2_6_2',
		),
	);

	const TB_HISTORY = 'remoji_history';

	/**
	 * Init
	 *
	 * @since  1.2
	 * @access protected
	 */
	protected function __construct() {
	}

	/**
	 * Data upgrade
	 *
	 * @since  1.2
	 */
	public function conf_upgrade() {
		require_once REMOJI_DIR . 'src/data.upgrade.func.php';

		foreach ( $this->_db_updater as $k => $v ) {
			if ( version_compare( REMOJI_CUR_V, $k, '<' ) ) {
				// run each callback
				foreach ( $v as $v2 ) {
					defined( 'debug' ) && debug( '[Data] Updating [ori_v] ' . REMOJI_CUR_V . " \t[to] $k \t[func] $v2" );
					call_user_func( $v2 );
				}
			}
		}

		Conf::delete( '_ver' );
		Conf::add( '_ver', Core::VER );

		Util::version_check( 'upgrade' );
	}

	/**
	 * Get the table name
	 *
	 * @since  1.2
	 * @access public
	 */
	public function tb( $tb ) {
		global $wpdb;

		switch ( $tb ) {
			case 'history':
				return $wpdb->prefix . self::TB_HISTORY;

			default:
				break;
		}
	}

	/**
	 * Check if one table exists or not
	 *
	 * @since  1.2
	 * @access public
	 */
	public function tb_exist( $tb ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is an internal constant (Data::tb()); no user-supplied values in this query.
		return $wpdb->get_var( 'SHOW TABLES LIKE "' . $this->tb( $tb ) . '"' );
	}

	/**
	 * Get data structure of one table
	 *
	 * @since  1.2
	 * @access private
	 */
	private function _tb_structure( $tb ) {
		return F::read( REMOJI_DIR . 'data/sql/' . $tb . '.sql' );
	}

	/**
	 * Create img optm table and sync data from wp_postmeta
	 *
	 * @since  1.2
	 * @access public
	 */
	public function tb_create( $tb ) {
		global $wpdb;

		defined( 'debug' ) && debug2( '[Data] Checking table ' . $tb );

		// Check if table exists first
		if ( $this->tb_exist( $tb ) ) {
			defined( 'debug' ) && debug2( '[Data] Existed' );
			return;
		}

		defined( 'debug' ) && debug( '[Data] Creating ' . $tb );

		$sql = sprintf(
			'CREATE TABLE IF NOT EXISTS `%1$s` (' . $this->_tb_structure( $tb ) . ') %2$s;',
			$this->tb( $tb ),
			$wpdb->get_charset_collate() // 'DEFAULT CHARSET=utf8'
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is an internal constant (Data::tb()) and the schema comes from a bundled local .sql file; no user-supplied values in this query.
		$res = $wpdb->query( $sql );
		if ( $res !== true ) {
			defined( 'debug' ) && debug( '[Data] Warning! Creating table failed!', $sql );
		}
	}

	/**
	 * Drop table
	 *
	 * @since  1.2
	 * @access public
	 */
	public function tb_del( $tb ) {
		global $wpdb;

		if ( ! $this->tb_exist( $tb ) ) {
			return;
		}

		defined( 'debug' ) && debug( '[Data] Deleting table ' . $tb );

		$q = 'DROP TABLE IF EXISTS ' . $this->tb( $tb );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is an internal constant (Data::tb()); no user-supplied values in this query.
		$wpdb->query( $q );
	}

	/**
	 * Create all tables
	 *
	 * @since  1.2
	 * @access public
	 */
	public function tables_create() {
		$this->tb_create( 'history' );
	}

	/**
	 * Drop generated tables
	 *
	 * @since  1.2
	 * @access public
	 */
	public function tables_del() {
		$this->tb_del( 'history' );
	}
}
