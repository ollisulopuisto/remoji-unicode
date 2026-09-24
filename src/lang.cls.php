<?php
/**
 * Language class
 *
 * @since 1.2
 */
namespace remoji;

defined( 'WPINC' ) || exit;

class Lang extends Instance {
	protected static $_instance;

	/**
	 * Init hook
	 *
	 * @since  1.2
	 */
	public function init() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	/**
	 * Plugin loaded hooks
	 *
	 * @since 1.2
	 */
	public function plugins_loaded() {
		load_plugin_textdomain( 'remoji', false, 'remoji/lang/' );
	}

	public static function msg( $tag ) {
		switch ( true ) {
			case strpos( $tag, 'try_later ' ) === 0:
				/* translators: %s: a human-readable time duration (wrapped in a <code> tag) to wait before retrying. */
				$msg = sprintf( __( 'Please try again after %s.', 'remoji' ), '<code>' . Util::readable_time( substr( $tag, strlen( 'try_later ' ) ), 3600, true ) . '</code>' );
				break;

			case $tag === 'rate_limited':
				$msg = __( 'Too many requests. Please slow down and try again in a minute.', 'remoji' );
				break;

			case $tag === 'lack_of_param':
				$msg = __( 'Missing required parameters.', 'remoji' );
				break;

			case $tag === 'invalid_emoji':
				$msg = __( 'Invalid emoji.', 'remoji' );
				break;

			case $tag === 'invalid_emoji_type':
				$msg = __( 'Invalid reaction type.', 'remoji' );
				break;

			case $tag === 'no_id':
				$msg = __( 'Missing post ID.', 'remoji' );
				break;

			case $tag === 'invalid_target':
				$msg = __( 'This item can not be reacted to.', 'remoji' );
				break;

			case $tag === 'post_type_disabled':
				$msg = __( 'Reactions are disabled for this content type.', 'remoji' );
				break;

			case $tag === 'post_reaction_disabled':
				$msg = __( 'Reactions are disabled for this post.', 'remoji' );
				break;

			case $tag === 'login_required':
				$msg = __( 'You need to login to proceed this action.', 'remoji' );
				break;

			case $tag === 'max_emoji_per_ip':
				/* translators: %s: the maximum number of emoji reactions allowed per IP (wrapped in a <code> tag). */
				$msg = sprintf( __( 'You have reached maximum %s emojis for this post/comment.', 'remoji' ), '<code>' . Conf::val( 'max_emoji_per_ip' ) . '</code>' );
				break;

			case $tag === 'duplicate_reaction':
				$msg = __( 'You have reacted with this emoji.', 'remoji' );
				break;

			case $tag === 'post_emoji_disabled':
				$msg = __( 'Emoji reaction to Posts is disabled.', 'remoji' );
				break;

			case $tag === 'comment_emoji_disabled':
				$msg = __( 'Emoji reaction to Comments is disabled.', 'remoji' );
				break;

			default:
				$msg = 'unknown msg: ' . $tag;
				break;
		}

		return '<strong>Remoji</strong>: ' . $msg;
	}
}
