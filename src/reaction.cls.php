<?php
/**
 * Reaction class
 *
 * @since 1.0
 */
namespace remoji;

defined( 'WPINC' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- all queries target the plugin's own {prefix}remoji_history table; values are bound via $wpdb->prepare(), and a bespoke table has no WordPress object-cache API.

class Reaction extends Instance {
	protected static $_instance;

	const TYPE_DEL = 'del';
	const META_DISABLED = '_remoji_disable_reactions';

	private $_tb;
	private $__data;

	protected function __construct() {
		$this->__data = Data::get_instance();
		$this->_tb    = $this->__data->tb( 'history' );
	}

	/**
	 * Add (or, for an owner's repeat click, withdraw) a reaction.
	 * Returns action=added|withdrawn plus the updated count so the frontend never has to guess from the DOM.
	 */
	public function add() {
		defined( 'debug' ) && debug2( 'add reaction' );

		// Per-IP request rate limit, checked before any DB/meta work so add/withdraw loops and
		// request floods can't exhaust PHP workers or bypass the per-IP cap by cycling rows.
		$retry_after = $this->_rate_limited();
		if ( $retry_after ) {
			$err            = REST::err( 'rate_limited' );
			$err['message'] = wp_strip_all_tags( $err['_msg'] );
			$res            = new \WP_REST_Response( $err, 429 );
			$res->header( 'Retry-After', (string) $retry_after );
			return $res;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- public REST endpoint (permission_callback __return_true); abuse is bounded by per-IP rate limiting, not a nonce (cache compatibility).
		if ( empty( $_POST['emoji'] ) || empty( $_POST['related_id'] ) || empty( $_POST['related_type'] ) ) {
			return REST::err( 'lack_of_param' );
		}

		$emoji        = sanitize_text_field( wp_unslash( $_POST['emoji'] ) );
		$related_id   = (int) $_POST['related_id'];
		$related_type = sanitize_text_field( wp_unslash( $_POST['related_type'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Emoji must exist ( Unicode fork: resolve to the rendered character instead of an SVG basename )
		$gui  = GUI::get_instance();
		$char = $emoji ? $gui->emoji_char( $emoji ) : '';
		if ( ! $emoji || $char === '' ) {
			return REST::err( 'invalid_emoji' );
		}

		// Enforce the emoji whitelist / picker list server-side ( support #2 ) so a crafted POST can't use a key that isn't offered.
		// Unicode fork: with no whitelist, only names in the picker list are accepted ( not the whole bundled catalog ).
		if ( ! $gui->is_offered( $emoji ) ) {
			return REST::err( 'invalid_emoji' );
		}

		if ( ! in_array( $related_type, array( 'post', 'comment' ), true ) ) {
			return REST::err( 'invalid_emoji_type' );
		}

		if ( $related_type === 'post' && ! Conf::val( 'post_emoji' ) ) {
			return REST::err( 'post_emoji_disabled' );
		}

		if ( $related_type === 'comment' && ! Conf::val( 'comment_emoji' ) ) {
			return REST::err( 'comment_emoji_disabled' );
		}

		// Login gate when guest reactions are disabled
		if ( ! Conf::val( 'guest' ) && ! is_user_logged_in() ) {
			return REST::err( 'login_required' );
		}

		// Target must be a real, publicly reactable post/comment (blocks direct POST to orphan/hidden/disabled IDs)
		$reactable = $this->_reactable_target( $related_type, $related_id );
		if ( $reactable !== 'ok' ) {
			return REST::err( $reactable );
		}

		// Ensure the log table exists before it gets queried/inserted below
		$this->__data->tb_create( 'history' );

		// Repeat click by the same owner (logged-in => user_id, guest => IP): withdraw if allowed, else reject
		$existing = $this->_find_own_reaction( $emoji, $related_id, $related_type );
		if ( $existing ) {
			if ( ! Conf::val( 'reaction_withdraw' ) ) {
				return REST::err( 'duplicate_reaction' );
			}

			$count = $this->_withdraw( $existing, $related_id, $related_type, $emoji );

			return REST::ok(
				array(
					'action' => 'withdrawn',
					'count'  => $count,
					'emoji'  => $char,
				)
			);
		}

		// Per-IP cap
		if ( $this->_reached_cap( $related_id, $related_type ) ) {
			return REST::err( 'max_emoji_per_ip' );
		}

		$this->_log_reaction( $emoji, $related_id, $related_type );

		$count = $this->_bump_meta( $related_id, $related_type, $emoji, 1 );

		do_action( 'remoji_reaction_add', $related_type, $related_id, $emoji );

		// Optional email notification — added reactions only
		$this->_notify( $related_type, $related_id );

		return REST::ok(
			array(
				'action' => 'added',
				'count'  => $count,
				'emoji'  => $char,
			)
		);
	}

	/**
	 * Delete reaction
	 */
	private function _del() {
		global $wpdb;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reached only from the nonce-verified admin action flow (Router::handler after wp_verify_nonce); id is cast to int.
		$id = empty( $_GET['remoji_id'] ) ? 0 : (int) $_GET['remoji_id'];
		if ( $id <= 0 ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is an internal constant (Data::tb()); all values are bound via $wpdb->prepare().
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$this->_tb` WHERE id = %d", $id ) );
		if ( ! $row ) {
			return;
		}

		$emoji        = $row->emoji;
		$related_id   = $row->related_id;
		$related_type = $row->related_type;

		// Delete log
		$q = "DELETE FROM `$this->_tb` WHERE id = %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is an internal constant (Data::tb()); all values are bound via $wpdb->prepare().
		$deleted = $wpdb->query( $wpdb->prepare( $q, $id ) );
		if ( ! $deleted ) {
			return;
		}

		$this->_bump_meta( $related_id, $related_type, $emoji, -1 );

		do_action( 'remoji_reaction_del', $related_type, $related_id, $emoji );

		GUI::succeed( __( 'Delete emoji reaction record successfully!', 'remoji' ) );
	}

	/**
	 * Validate the reaction target is a real, publicly reactable post/comment.
	 * Blocks direct POSTs to orphan/unpublished/hidden IDs (which would pollute meta/history and could
	 * surface non-public titles through the public "Last Reacted" widget). Returns 'ok' or an error tag.
	 *
	 * @since 2.5
	 */
	private function _reactable_target( $related_type, $related_id ) {
		$disabled = (array) Conf::val( 'reaction_disabled_types' );

		if ( $related_type === 'post' ) {
			$post = get_post( $related_id );
			if ( ! $this->_public_post( $post ) ) {
				return 'invalid_target';
			}
			if ( in_array( $post->post_type, $disabled, true ) ) {
				return 'post_type_disabled';
			}
			if ( self::post_disabled( $post->ID ) ) {
				return 'post_reaction_disabled';
			}
			return 'ok';
		}

		// comment: the comment must be approved AND its parent post publicly visible
		$comment = get_comment( $related_id );
		if ( ! $comment || $comment->comment_approved !== '1' ) {
			return 'invalid_target';
		}
		$parent = get_post( $comment->comment_post_ID );
		if ( ! $this->_public_post( $parent ) ) {
			return 'invalid_target';
		}
		if ( in_array( $parent->post_type, $disabled, true ) ) {
			return 'post_type_disabled';
		}
		if ( self::post_disabled( $parent->ID ) ) {
			return 'post_reaction_disabled';
		}
		return 'ok';
	}

	/**
	 * 單篇內容是否停用 Remoji reactions。
	 *
	 * @since 2.5.1
	 * @access public
	 */
	public static function post_disabled( $post_id ) {
		return (bool) get_post_meta( (int) $post_id, self::META_DISABLED, true );
	}

	/**
	 * Whether a post object is publicly viewable: exists, published, a public post type, and (for bbPress)
	 * not inside a private/hidden forum. Shared by the post and comment-parent target checks so both
	 * paths — and the public widgets that trust is_reactable() — apply the same visibility rules.
	 *
	 * @since 2.5
	 */
	private function _public_post( $post ) {
		return $post
			&& $post->post_status === 'publish'
			&& ! post_password_required( $post )
			&& $this->_is_public_type( $post->post_type )
			&& $this->_bbp_visible( $post );
	}

	/**
	 * Whether a post type is publicly viewable (excludes private CPTs, attachments registered non-public, etc.).
	 *
	 * @since 2.5
	 */
	private function _is_public_type( $post_type ) {
		$obj = get_post_type_object( $post_type );
		return $obj && ! empty( $obj->public );
	}

	/**
	 * When bbPress is active, don't treat a reply/topic that lives in a private or hidden forum as public.
	 * Returns true for non-bbPress content or when bbPress is not installed.
	 *
	 * @since 2.5
	 */
	private function _bbp_visible( $post ) {
		if ( ! function_exists( 'bbp_get_topic_post_type' ) || ! function_exists( 'bbp_get_reply_post_type' ) ) {
			return true;
		}

		$topic_pt = bbp_get_topic_post_type();
		$reply_pt = bbp_get_reply_post_type();
		if ( $post->post_type !== $topic_pt && $post->post_type !== $reply_pt ) {
			return true;
		}

		// Resolve the containing forum id
		$forum_id = 0;
		if ( $post->post_type === $reply_pt && function_exists( 'bbp_get_reply_forum_id' ) ) {
			$forum_id = bbp_get_reply_forum_id( $post->ID );
		} elseif ( $post->post_type === $topic_pt && function_exists( 'bbp_get_topic_forum_id' ) ) {
			$forum_id = bbp_get_topic_forum_id( $post->ID );
		}
		if ( ! $forum_id ) {
			return true;
		}

		if ( function_exists( 'bbp_is_forum_private' ) && bbp_is_forum_private( $forum_id ) ) {
			return false;
		}
		if ( function_exists( 'bbp_is_forum_hidden' ) && bbp_is_forum_hidden( $forum_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether a reaction target is currently publicly viewable. Used by the public widgets/log so a post
	 * or comment that was reacted to but has since become draft/private/deleted isn't surfaced.
	 *
	 * @since 2.5
	 * @access public
	 */
	public function is_reactable( $related_type, $related_id ) {
		return $this->_reactable_target( $related_type, $related_id ) === 'ok';
	}

	/**
	 * Per-IP rate limit for the public add endpoint (fixed window, stored in a transient keyed by the IP HMAC).
	 * Defaults to 30 requests per 60 seconds; tune with the `remoji_rate_limit` / `remoji_rate_limit_window` filters
	 * (a limit of 0 disables it). Counts every add request, including withdraws and rejected ones.
	 * Returns 0 when allowed, else the number of seconds until the window resets.
	 *
	 * @since 2.6.2
	 */
	private function _rate_limited() {
		$limit  = (int) apply_filters( 'remoji_rate_limit', 30 );
		$window = max( 1, (int) apply_filters( 'remoji_rate_limit_window', MINUTE_IN_SECONDS ) );
		if ( $limit <= 0 ) {
			return 0;
		}

		$key  = 'remoji_rl_' . substr( IP::hash(), 0, 40 );
		$now  = time();
		$data = get_transient( $key );

		if ( ! is_array( $data ) || empty( $data['start'] ) || $now - (int) $data['start'] >= $window ) {
			$data = array(
				'start' => $now,
				'count' => 0,
			);
		}

		$reset = max( 1, (int) $data['start'] + $window - $now );

		if ( (int) $data['count'] >= $limit ) {
			return $reset;
		}

		++$data['count'];
		set_transient( $key, $data, $reset );

		return 0;
	}

	/**
	 * Identify the current reactor: logged-in users by user_id, guests by their IP's keyed hash.
	 * Returns [ column, value ] used for duplicate/withdraw ownership; for guests the value is the
	 * list of stored keys that may belong to this IP (current HMAC + re-keyed legacy md5).
	 *
	 * @since 2.5
	 */
	private function _owner() {
		$uid = get_current_user_id();
		if ( $uid ) {
			return array( 'user_id', $uid );
		}

		return array( 'ip', IP::owner_keys() );
	}

	/**
	 * The current owner's existing row for this exact emoji/target, if any.
	 *
	 * @since 2.5
	 */
	private function _find_own_reaction( $emoji, $related_id, $related_type ) {
		global $wpdb;

		list( $col, $val ) = $this->_owner();

		if ( $col === 'user_id' ) {
			// Also match this user's pre-2.5 rows (user_id defaulted to 0 by the migration) by their IP,
			// so an upgraded site keeps duplicate/withdraw ownership for reactions made before the column existed.
			list( $ip_key, $ip_legacy ) = IP::owner_keys();

			$q = "SELECT * FROM `$this->_tb` WHERE emoji = %s AND related_id = %d AND related_type = %s AND ( user_id = %d OR ( user_id = 0 AND ip IN ( %s, %s ) ) ) ORDER BY id DESC LIMIT 1";
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is an internal constant (Data::tb()); all values are bound via $wpdb->prepare().
			return $wpdb->get_row( $wpdb->prepare( $q, array( $emoji, $related_id, $related_type, $val, $ip_key, $ip_legacy ) ) );
		}

		list( $ip_key, $ip_legacy ) = $val;

		$q = "SELECT * FROM `$this->_tb` WHERE ip IN ( %s, %s ) AND emoji = %s AND related_id = %d AND related_type = %s ORDER BY id DESC LIMIT 1";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is an internal constant (Data::tb()); all values are bound via $wpdb->prepare().
		return $wpdb->get_row( $wpdb->prepare( $q, array( $ip_key, $ip_legacy, $emoji, $related_id, $related_type ) ) );
	}

	/**
	 * Whether the visitor's IP reached the per-IP cap for this target.
	 *
	 * @since 2.5
	 */
	private function _reached_cap( $related_id, $related_type ) {
		global $wpdb;

		$max = (int) Conf::val( 'max_emoji_per_ip' );
		if ( $max <= 0 ) {
			return false;
		}

		list( $ip_key, $ip_legacy ) = IP::owner_keys();

		$q = "SELECT COUNT(*) FROM `$this->_tb` WHERE ip IN ( %s, %s ) AND related_id = %d AND related_type = %s";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is an internal constant (Data::tb()); all values are bound via $wpdb->prepare().
		$count = $wpdb->get_var( $wpdb->prepare( $q, array( $ip_key, $ip_legacy, $related_id, $related_type ) ) );

		return $count >= $max;
	}

	/**
	 * Withdraw an existing reaction row + decrement its meta counter. Returns the new count for that emoji.
	 *
	 * @since 2.5
	 */
	private function _withdraw( $row, $related_id, $related_type, $emoji ) {
		global $wpdb;

		// Only decrement when THIS request actually removed the row. A concurrent duplicate withdraw
		// deletes 0 rows and must not double-decrement the visible count.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is an internal constant (Data::tb()); all values are bound via $wpdb->prepare().
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM `$this->_tb` WHERE id = %d", $row->id ) );
		if ( ! $deleted ) {
			return $this->_emoji_count( $related_id, $related_type, $emoji );
		}

		$count = $this->_bump_meta( $related_id, $related_type, $emoji, -1 );

		do_action( 'remoji_reaction_del', $related_type, $related_id, $emoji );

		return $count;
	}

	/**
	 * Current stored count for one emoji on a target, without modifying it.
	 *
	 * @since 2.5
	 */
	private function _emoji_count( $related_id, $related_type, $emoji ) {
		$emoji_list = $related_type === 'comment' ? get_comment_meta( $related_id, 'remoji', true ) : get_post_meta( $related_id, 'remoji', true );
		if ( ! is_array( $emoji_list ) || empty( $emoji_list[ $emoji ] ) ) {
			return 0;
		}

		return (int) $emoji_list[ $emoji ];
	}

	/**
	 * Increment/decrement the per-emoji counter stored in post/comment meta.
	 * Returns the resulting count for that emoji (0 when it drops to none and is removed).
	 *
	 * @since 2.5
	 */
	private function _bump_meta( $related_id, $related_type, $emoji, $delta ) {
		$lock_name = $this->_meta_lock_name( $related_id, $related_type );
		$locked    = $this->_acquire_meta_lock( $lock_name );

		$emoji_list = $related_type === 'comment' ? get_comment_meta( $related_id, 'remoji', true ) : get_post_meta( $related_id, 'remoji', true );
		if ( ! is_array( $emoji_list ) ) {
			$emoji_list = array();
		}

		$current  = isset( $emoji_list[ $emoji ] ) ? (int) $emoji_list[ $emoji ] : 0;
		$current += $delta;

		if ( $current > 0 ) {
			$emoji_list[ $emoji ] = $current;
		} else {
			$current = 0;
			unset( $emoji_list[ $emoji ] );
		}

		$related_type === 'comment' ? update_comment_meta( $related_id, 'remoji', $emoji_list ) : update_post_meta( $related_id, 'remoji', $emoji_list );

		if ( $locked ) {
			$this->_release_meta_lock( $lock_name );
		}

		return $current;
	}

	/**
	 * 建立短且穩定的 DB 命名鎖名稱，避免同一 target 的序列化 counter meta 被併發覆寫。
	 *
	 * @since 2.5.1
	 */
	private function _meta_lock_name( $related_id, $related_type ) {
		return 'remoji_meta_' . md5( $related_type . ':' . (int) $related_id );
	}

	/**
	 * 嘗試取得 counter meta 的 DB 命名鎖。
	 *
	 * @since 2.5.1
	 */
	private function _acquire_meta_lock( $lock_name ) {
		global $wpdb;

		$locked = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 5 )', $lock_name ) );

		return (string) $locked === '1';
	}

	/**
	 * 釋放 counter meta 的 DB 命名鎖。
	 *
	 * @since 2.5.1
	 */
	private function _release_meta_lock( $lock_name ) {
		global $wpdb;

		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $lock_name ) );
	}

	/**
	 * Reactor display names grouped by emoji for a target, for the "who reacted" display.
	 * One history query + one user lookup (no N+1). Guests collapse to a generic label.
	 * Returns [] when disabled or under GDPR (which forbids identifying reactors).
	 *
	 * @since 2.5
	 */
	public function reactors( $related_id, $related_type ) {
		global $wpdb;

		if ( ! Conf::val( 'show_reactors' ) || Conf::val( 'gdpr' ) ) {
			return array();
		}
		if ( ! $this->__data->tb_exist( 'history' ) ) {
			return array();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is an internal constant (Data::tb()); all values are bound via $wpdb->prepare().
				"SELECT emoji, user_id FROM `$this->_tb` WHERE related_id = %d AND related_type = %s ORDER BY id ASC",
				$related_id,
				$related_type
			)
		);
		if ( ! $rows ) {
			return array();
		}

		// Batch-resolve user ids -> display names in a single query
		$ids   = array_filter( array_unique( wp_list_pluck( $rows, 'user_id' ) ) );
		$names = array();
		if ( $ids ) {
			$users = get_users(
				array(
					'include' => $ids,
					'fields'  => array( 'ID', 'display_name' ),
				)
			);
			foreach ( $users as $u ) {
				$names[ $u->ID ] = $u->display_name;
			}
		}

		$guest = __( 'Guest', 'remoji' );
		$out   = array();
		foreach ( $rows as $r ) {
			$uid                = (int) $r->user_id;
			$out[ $r->emoji ][] = ( $uid && isset( $names[ $uid ] ) ) ? $names[ $uid ] : $guest;
		}

		return $out;
	}

	/**
	 * Optionally email the post author when their content gets a NEW reaction.
	 * Skips author self-reaction and throttles per target so the public endpoint can't be turned into a mail flood.
	 *
	 * @since 2.5
	 */
	private function _notify( $related_type, $related_id ) {
		if ( ! Conf::val( 'reaction_notify' ) ) {
			return;
		}

		$recipient_email = '';
		$permalink       = '';

		if ( $related_type === 'comment' ) {
			$comment = get_comment( $related_id );
			if ( ! $comment ) {
				return;
			}
			$post = get_post( $comment->comment_post_ID );

			// Skip a logged-in commenter reacting to their own comment.
			$uid = get_current_user_id();
			if ( $uid && (int) $comment->user_id === $uid ) {
				return;
			}

			if ( (int) $comment->user_id > 0 ) {
				$comment_author = get_userdata( (int) $comment->user_id );
				if ( $comment_author && is_email( $comment_author->user_email ) ) {
					$recipient_email = $comment_author->user_email;
				}
			}
			if ( ! $recipient_email && is_email( $comment->comment_author_email ) ) {
				$recipient_email = $comment->comment_author_email;
			}

			$permalink = get_comment_link( $comment );
		} else {
			$post = get_post( $related_id );

			if ( ! $post ) {
				return;
			}

			// Skip the post author reacting to their own post.
			$uid = get_current_user_id();
			if ( $uid && (int) $post->post_author === $uid ) {
				return;
			}

			$post_author = get_userdata( $post->post_author );
			if ( $post_author && is_email( $post_author->user_email ) ) {
				$recipient_email = $post_author->user_email;
			}

			$permalink = get_permalink( $post->ID );
		}
		if ( ! $post ) {
			return;
		}

		if ( ! $recipient_email ) {
			return;
		}

		// Throttle: one mail per target within the cooldown window (type-scoped key so post 123 != comment 123)
		$key = 'remoji_notify_' . md5( $related_type . ':' . $related_id );
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, 5 * MINUTE_IN_SECONDS );

		$title = get_the_title( $post->ID );
		/* translators: %s: the post/comment title. */
		$subject = sprintf( __( 'New reaction on "%s"', 'remoji' ), $title );
		/* translators: %1$s: the content type (post or comment); %2$s: the post/comment title. */
		$body = sprintf( __( 'Your %1$s "%2$s" just received a new emoji reaction.', 'remoji' ), $related_type, $title ) . "\n" . $permalink;

		wp_mail( $recipient_email, wp_specialchars_decode( $subject ), wp_specialchars_decode( $body ) );
	}

	/**
	 * Log the reaction
	 */
	private function _log_reaction( $emoji, $related_id, $related_type ) {
		global $wpdb;

		// Only an opaque keyed hash of the IP is stored (always, regardless of GDPR mode). No geolocation
		// lookup and no location data: the `ip_geo` column is kept for schema compatibility and left empty.
		$ip = IP::hash();

		$q = "INSERT INTO `$this->_tb` SET ip = %s, ip_geo = %s, emoji = %s, related_id = %d, related_type = %s, user_id = %d, dateline = %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is an internal constant (Data::tb()); all values are bound via $wpdb->prepare().
		$wpdb->query( $wpdb->prepare( $q, array( $ip, '', $emoji, $related_id, $related_type, get_current_user_id(), time() ) ) );
	}

	/**
	 * Display log
	 *
	 * @since  1.2
	 * @access public
	 */
	public function history_list( $limit, $offset = false ) {
		global $wpdb;

		if ( $offset === false ) {
			$total  = $this->count_list();
			$offset = Util::pagination( $total, $limit, true );
		}

		$q = "SELECT * FROM `$this->_tb` ORDER BY id DESC LIMIT %d, %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is an internal constant (Data::tb()); all values are bound via $wpdb->prepare().
		return $wpdb->get_results( $wpdb->prepare( $q, $offset, $limit ) );
	}

	/**
	 * Count the log list
	 */
	public function count_list() {
		global $wpdb;

		if ( ! $this->__data->tb_exist( 'history' ) ) {
			return false;
		}

		$q = "SELECT COUNT(*) FROM `$this->_tb`";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is an internal constant (Data::tb()); no user-supplied values in this query.
		return $wpdb->get_var( $q );
	}

	/**
	 * Handler
	 *
	 * @since  1.4
	 */
	public static function handler() {
		$instance = self::get_instance();

		$type = Router::verify_type();

		switch ( $type ) {
			case self::TYPE_DEL:
				$instance->_del();
				break;

			default:
				break;
		}
	}
}
