<?php
/**
 * Comment class
 *
 * @since 1.0
 */
namespace remoji;

defined( 'WPINC' ) || exit;

class Comment extends Instance {
	protected static $_instance;

	private $_disabled_posttypes;

	/**
	 * Init
	 *
	 * @access public
	 */
	public function init() {
		$this->_disabled_posttypes = Conf::val( 'disable_comment' );
		if ( ! $this->_disabled_posttypes ) {
			return;
		}

		add_filter( 'comments_array', array( $this, 'filter_comments' ), 20, 2 );
		add_filter( 'comments_open', array( $this, 'filter_comment_status' ), 20, 2 );
		add_action( 'template_redirect', array( $this, 'comment_template' ) );
	}

	/**
	 * Filter existing comments
	 */
	public function filter_comments( $comments, $post_id ) {
		$post = get_post( $post_id );
		if ( in_array( $post->post_type, $this->_disabled_posttypes, true ) ) {
			return array();
		}

		return $comments;
	}

	/**
	 * Control comment status
	 */
	public function filter_comment_status( $open, $post_id ) {
		$post = get_post( $post_id );
		if ( in_array( $post->post_type, $this->_disabled_posttypes, true ) ) {
			return false;
		}

		return $open;
	}

	/**
	 * Replace the comment template
	 */
	public function comment_template() {
		if ( ! is_singular() ) {
			return;
		}

		if ( ! in_array( get_post_type(), $this->_disabled_posttypes, true ) ) {
			return;
		}

		add_filter( 'comments_template', array( $this, 'dummy_tpl' ) );
	}

	/**
	 * Replace the template to empty one
	 */
	public function dummy_tpl() {
		return REMOJI_DIR . 'tpl/dummy.tpl.php';
	}
}
