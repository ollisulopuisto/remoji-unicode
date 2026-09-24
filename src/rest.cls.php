<?php
/**
 * Rest class
 *
 * @since 1.0
 */
namespace remoji;

defined( 'WPINC' ) || exit;

class REST extends Instance {
	protected static $_instance;

	/**
	 * Init
	 *
	 * @since  1.0
	 * @access public
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
	}

	/**
	 * Register REST hooks
	 *
	 * @since  1.0
	 * @access public
	 */
	public function rest_api_init() {
		register_rest_route(
			'remoji/v1',
			'/show_reaction_panel',
			array(
				'methods'             => 'GET',
				'callback'            => __CLASS__ . '::show_reaction_panel',
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'remoji/v1',
			'/add',
			array(
				'methods'             => 'POST',
				'callback'            => __CLASS__ . '::add',
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'remoji/v1',
			'/postview',
			array(
				'methods'             => 'POST',
				'callback'            => __CLASS__ . '::postview',
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Return the panel content for frontend page
	 *
	 * @since 1.0
	 * @access public
	 */
	public static function show_reaction_panel() {
		$data = array( 'data' => GUI::get_instance()->show_reaction_panel() );
		return self::ok( $data );
	}

	/**
	 * Add Reaction
	 */
	public static function add() {
		return Reaction::get_instance()->add();
	}

	/**
	 * Count postview
	 */
	public static function postview() {
		return Postview::get_instance()->count_num();
	}

	/**
	 * Return content
	 */
	public static function ok( $data = array() ) {
		$data['_res'] = 'ok';
		return $data;
	}

	/**
	 * Return error
	 */
	public static function err( $msg ) {
		defined( 'debug' ) && debug( '❌ [err] ' . $msg );
		return array(
			'_res' => 'err',
			'_msg' => Lang::msg( $msg ),
		);
	}
}
