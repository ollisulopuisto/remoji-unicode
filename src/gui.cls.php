<?php
/**
 * GUI class
 *
 * @since 1.0
 */
namespace remoji;

defined( 'WPINC' ) || exit;

class GUI extends Instance {
	protected static $_instance;

	const DB_MSG        = 'remoji.msg';
	const NOTICE_BLUE   = 'notice notice-info';
	const NOTICE_GREEN  = 'notice notice-success';
	const NOTICE_RED    = 'notice notice-error';
	const NOTICE_YELLOW = 'notice notice-warning';

	/**
	 * Picker list ( name => codepoint string or literal emoji ). Default set + custom setting + remoji_emoji_list filter.
	 */
	private $_emoji_list = array();

	/**
	 * Unicode fork: full resolvable catalog ( bundled data/emoji.map shortnames + picker list ). Used to resolve
	 * any shortname for the whitelist and for already-stored reactions.
	 */
	private $_emoji_catalog = null;

	private $_emoji_list_handy = array(
		'slightly_smiling_face',
		'thumbsup',
		'ok_hand',
		'laughing',
		'joy',
	);


	/**
	 * Init
	 *
	 * @since  1.0
	 * @access public
	 */
	public function init() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a bundled local JSON file, not a remote URL.
		$this->_emoji_list = json_decode( file_get_contents( REMOJI_DIR . 'data/emoji.json' ), true );
		if ( ! is_array( $this->_emoji_list ) ) {
			$this->_emoji_list = array();
		}

		// Unicode fork: admin "Custom Emojis" lines are appended to the picker list
		foreach ( self::parse_custom( Conf::val( 'emoji_custom' ) ) as $k => $v ) {
			if ( $v === '' ) {
				// Name-only line: take the codepoint from the bundled catalog
				$v = $this->_catalog_lookup( $k );
			}
			if ( $v ) {
				$this->_emoji_list[ $k ] = $v;
			}
		}

		// Extension point: add/modify available emojis.
		// Unicode fork: values are Unicode codepoints, e.g. 'fire' => '1F525', 'technologist' => '1F9D1-200D-1F4BB',
		// 'U+2764', or the literal emoji character itself. The original's SVG basenames ( '1f525' ) keep working.
		$this->_emoji_list = apply_filters( 'remoji_emoji_list', $this->_emoji_list );

		// Drop entries whose key or value can't be used
		foreach ( $this->_emoji_list as $k => $v ) {
			if ( ! self::valid_name( $k ) || self::to_char( $v ) === '' ) {
				unset( $this->_emoji_list[ $k ] );
			}
		}
		defined( 'debug' ) && debug2( 'Load emoji list', $this->_emoji_list );

		if ( is_admin() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		if ( Conf::val( 'comment_emoji' ) ) {
			add_filter( 'comment_text', array( $this, 'reaction_bar' ), 10, 2 );
		}

		if ( Conf::val( 'post_emoji' ) ) {
			// Auto-append to post content. Can be turned off to place manually via [remoji] / do_action( 'remoji_reaction' )
			if ( Conf::val( 'post_emoji_auto' ) ) {
				add_filter( 'the_content', array( $this, 'reaction_bar' ) );
			}

			// bbPress forum topics/replies ( support #13 )
			add_filter( 'bbp_get_topic_content', array( $this, 'bbp_reaction_bar' ), 10, 2 );
			add_filter( 'bbp_get_reply_content', array( $this, 'bbp_reaction_bar' ), 10, 2 );

			// Manual placement ( support #7 )
			add_shortcode( 'remoji', array( $this, 'shortcode' ) );
			add_action( 'remoji_reaction', array( $this, 'do_reaction' ), 10, 2 );
		}

		if ( Conf::val( 'postview' ) ) {
			Postview::get_instance()->init();
		}
		// Load when compiling release
		// foreach ( $this->_emoji_list as $k => $v ) {
		// if ( file_exists( REMOJI_DIR . 'data/emoji/' . $v . '.svg' ) ) {
		// continue;
		// }
		// file_put_contents( REMOJI_DIR . 'data/emoji/' . $v . '.svg', file_get_contents( REMOJI_DIR . '../svg_emoji_all/' . $v . '.svg' ) );
		// }
	}

	/**
	 * Show reaction panel
	 *
	 * @since  1.0
	 * @access public
	 */
	public function show_reaction_panel() {
		ob_start();
		include REMOJI_DIR . 'tpl/reaction_popover.tpl.php';
		$content = ob_get_contents();
		ob_end_clean();

		$content = str_replace( array( "\n", "\t" ), '', $content );

		return $content;
	}

	/**
	 * Append emoji bar to comment
	 *
	 * @since  1.0
	 * @access public
	 */
	public function reaction_bar( $content, $comment = null ) {
		// Never inject into feeds, the admin, or excerpt generation — wp_trim_excerpt() also runs `the_content` ( support #6 )
		if ( is_admin() || is_feed() || doing_filter( 'get_the_excerpt' ) ) {
			return $content;
		}

		if ( $comment !== null ) {
			$remoji_type = 'comment';
			$remoji_id   = $comment->comment_ID;

			// Per-post-type disable applies to the comment's parent post too ( support #5 )
			if ( in_array( get_post_type( $comment->comment_post_ID ), (array) Conf::val( 'reaction_disabled_types' ), true ) ) {
				return $content;
			}
		} else {
			$remoji_type = 'post';
			$remoji_id   = get_the_ID();

			// Per-post-type disable ( support #5 )
			if ( in_array( get_post_type( $remoji_id ), (array) Conf::val( 'reaction_disabled_types' ), true ) ) {
				return $content;
			}
		}

		return $content . $this->render_bar( $remoji_id, $remoji_type );
	}

	/**
	 * Build the reaction-bar markup for a target. Shared by the content/comment filters,
	 * the [remoji] shortcode, the `remoji_reaction` action, and bbPress.
	 *
	 * @since 2.5
	 * @access public
	 */
	public function render_bar( $remoji_id, $remoji_type = 'post' ) {
		$remoji_id = (int) $remoji_id;
		if ( ! $remoji_id ) {
			return '';
		}

		$remoji_type = $remoji_type === 'comment' ? 'comment' : 'post';
		if ( ! Reaction::get_instance()->is_reactable( $remoji_type, $remoji_id ) ) {
			return '';
		}

		$emoji_list = $remoji_type === 'comment' ? get_comment_meta( $remoji_id, 'remoji', true ) : get_post_meta( $remoji_id, 'remoji', true );
		$reactors   = Reaction::get_instance()->reactors( $remoji_id, $remoji_type );

		ob_start();
		include REMOJI_DIR . 'tpl/reaction_bar.tpl.php';
		$html = str_replace( array( "\n", "\r", "\t" ), '', ob_get_contents() );
		ob_end_clean();

		return $html;
	}

	/**
	 * [remoji] shortcode — render the reaction bar for the current (or a given) post.
	 *
	 * @since 2.5
	 * @access public
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'   => 0,
				'type' => 'post',
			),
			$atts,
			'remoji'
		);
		$id   = (int) $atts['id'] ? (int) $atts['id'] : get_the_ID();
		$type = $atts['type'] === 'comment' ? 'comment' : 'post';

		return $this->render_bar( $id, $type );
	}

	/**
	 * do_action( 'remoji_reaction', $id, $type ) — echo the reaction bar from a theme/template.
	 *
	 * @since 2.5
	 * @access public
	 */
	public function do_reaction( $id = 0, $type = 'post' ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_bar() returns markup whose dynamic parts are escaped in reaction_bar.tpl.php.
		echo $this->render_bar( $id ? $id : get_the_ID(), $type === 'comment' ? 'comment' : 'post' );
	}

	/**
	 * bbPress topic/reply content filter — append the reaction bar using the bbPress-provided id.
	 *
	 * @since 2.5
	 * @access public
	 */
	public function bbp_reaction_bar( $content, $id = 0 ) {
		if ( is_admin() || is_feed() || ! $id ) {
			return $content;
		}
		if ( in_array( get_post_type( $id ), (array) Conf::val( 'reaction_disabled_types' ), true ) ) {
			return $content;
		}

		return $content . $this->render_bar( $id, 'post' );
	}

	/**
	 * Return one or all emoji
	 *
	 * List mode returns the picker list ( name => codepoint ). Single-key mode returns the codepoint string for a name.
	 *
	 * @since  1.0
	 * @access public
	 */
	public function emoji( $key = false ) {
		// List mode: honor the whitelist ( support #2 ) so the picker only offers allowed emojis
		if ( ! $key ) {
			$whitelist = (array) Conf::val( 'emoji_whitelist' );
			if ( ! $whitelist ) {
				return $this->_emoji_list;
			}

			// Unicode fork: whitelist names may come from the full catalog, and their order is the picker order
			$list = array();
			foreach ( $whitelist as $k ) {
				$v = $this->emoji( $k );
				if ( $v ) {
					$list[ $k ] = $v;
				}
			}
			return $list;
		}

		// Single-key lookup stays unfiltered: it also resolves ALREADY-STORED reactions whose emoji
		// may have since been removed from the whitelist. New-reaction whitelist enforcement lives in Reaction::add().
		if ( ! empty( $this->_emoji_list[ $key ] ) ) {
			return $this->_emoji_list[ $key ];
		}

		$v = $this->_catalog_lookup( $key );
		if ( ! $v ) {
			defined( 'debug' ) && debug( 'missing emoji [key] ' . $key );
			return null;
		}

		return $v;
	}

	/**
	 * Unicode fork: the rendered emoji character(s) for a name, or '' when unknown.
	 *
	 * @since 2.6.1
	 * @access public
	 */
	public function emoji_char( $key ) {
		$v = $this->emoji( $key );
		return $v ? self::to_char( $v ) : '';
	}

	/**
	 * Unicode fork: escaped <span> markup for one emoji name ( '' when unknown ).
	 *
	 * @since 2.6.1
	 * @access public
	 */
	public function emoji_html( $key, $extra_class = '' ) {
		$char = $this->emoji_char( $key );
		if ( $char === '' ) {
			return '';
		}

		$cls = 'remoji_emoji' . ( $extra_class ? ' ' . $extra_class : '' );

		return '<span class="' . esc_attr( $cls ) . '" role="img" aria-label="' . esc_attr( str_replace( '_', ' ', $key ) ) . '">' . esc_html( $char ) . '</span>';
	}

	/**
	 * Unicode fork: whether a name can be used for a NEW reaction ( in the whitelist if set, else in the picker list ).
	 *
	 * @since 2.6.1
	 * @access public
	 */
	public function is_offered( $key ) {
		$whitelist = (array) Conf::val( 'emoji_whitelist' );
		if ( $whitelist ) {
			return in_array( $key, $whitelist, true ) && $this->emoji_char( $key ) !== '';
		}

		return isset( $this->_emoji_list[ $key ] );
	}

	/**
	 * Return handy emojis
	 *
	 * @since  1.0
	 * @access public
	 */
	public function emoji_handy() {
		$whitelist = (array) Conf::val( 'emoji_whitelist' );
		if ( ! $whitelist ) {
			return array_values( array_intersect( $this->_emoji_list_handy, array_keys( $this->_emoji_list ) ) );
		}

		$handy = array_values( array_intersect( $this->_emoji_list_handy, $whitelist ) );
		if ( ! $handy ) {
			// Unicode fork: fall back to the first few whitelisted names so the footer isn't empty
			$handy = array_slice( array_keys( $this->emoji() ), 0, 5 );
		}

		return $handy;
	}

	/**
	 * The full (unfiltered) picker map, ignoring the whitelist. Used by the settings UI reference list.
	 *
	 * @since 2.5
	 * @access public
	 */
	public function all_emoji() {
		return $this->_emoji_list;
	}

	/**
	 * Unicode fork: every resolvable name ( bundled catalog + picker list ), name => codepoint.
	 *
	 * @since 2.6.1
	 * @access public
	 */
	public function catalog() {
		$this->_load_catalog();
		return array_merge( $this->_emoji_catalog, $this->_emoji_list );
	}

	/**
	 * Unicode fork: look a name up in the bundled shortname catalog ( data/emoji.map ).
	 *
	 * @since 2.6.1
	 * @access private
	 */
	private function _catalog_lookup( $key ) {
		$this->_load_catalog();
		return isset( $this->_emoji_catalog[ $key ] ) ? $this->_emoji_catalog[ $key ] : null;
	}

	/**
	 * Unicode fork: lazy-load data/emoji.map ( ~2,200 Slack/GitHub-style shortnames, "_"-joined codepoints ).
	 * The file is a JSON object body without the surrounding braces.
	 *
	 * @since 2.6.1
	 * @access private
	 */
	private function _load_catalog() {
		if ( $this->_emoji_catalog !== null ) {
			return;
		}

		$this->_emoji_catalog = array();

		$file = REMOJI_DIR . 'data/emoji.map';
		if ( ! file_exists( $file ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a bundled local file, not a remote URL.
		$raw = trim( (string) file_get_contents( $file ) );
		$raw = rtrim( $raw, ", \r\n\t" );
		if ( $raw === '' ) {
			return;
		}
		if ( $raw[0] !== '{' ) {
			$raw = '{' . $raw . '}';
		}

		$map = json_decode( $raw, true );
		if ( ! is_array( $map ) ) {
			return;
		}

		foreach ( $map as $k => $v ) {
			if ( self::valid_name( $k ) && is_string( $v ) && self::to_char( $v ) !== '' ) {
				$this->_emoji_catalog[ $k ] = $v;
			}
		}
	}

	/**
	 * Unicode fork: allowed emoji name ( stored in DB + used in data attributes / CSS selectors ).
	 *
	 * @since 2.6.1
	 * @access public
	 */
	public static function valid_name( $key ) {
		return is_string( $key ) && preg_match( '/^[a-z0-9_+\-]{1,64}$/i', $key );
	}

	/**
	 * Unicode fork: convert a codepoint spec to the emoji character(s).
	 *
	 * Accepts '1f525', '1F525', 'U+1F525', sequences joined by '-', '_' or spaces ( '1f9d1-200d-1f4bb' ),
	 * or a literal emoji string. Returns '' for anything unusable ( control chars, surrogates, private use, markup ).
	 *
	 * @since 2.6.1
	 * @access public
	 */
	public static function to_char( $v ) {
		if ( ! is_string( $v ) && ! is_int( $v ) ) {
			return '';
		}
		$v = trim( (string) $v );
		if ( $v === '' ) {
			return '';
		}

		if ( preg_match( '/^(?:u\+)?[0-9a-f]{1,6}(?:[\s_\-]+(?:u\+)?[0-9a-f]{1,6})*$/i', $v ) ) {
			$cps = array();
			foreach ( preg_split( '/[\s_\-]+/', $v ) as $hex ) {
				$cp = hexdec( preg_replace( '/^u\+/i', '', $hex ) );
				if ( $cp < 0x20 || ( $cp >= 0x7F && $cp < 0xA0 ) || $cp > 0x10FFFF || ( $cp >= 0xD800 && $cp <= 0xDFFF ) || ( $cp >= 0xE000 && $cp <= 0xF8FF ) ) {
					return '';
				}
				$cps[] = (int) $cp;
			}

			if ( count( $cps ) > 16 ) {
				return '';
			}

			// A bare ASCII character ( e.g. '23' = '#' ) is not an emoji on its own
			if ( count( $cps ) === 1 && $cps[0] < 0x80 ) {
				return '';
			}

			// Force emoji presentation for text-default single codepoints ( e.g. 263a, 2764, a9 ) and keycaps
			if ( count( $cps ) === 1 && $cps[0] < 0x1F300 ) {
				$cps[] = 0xFE0F;
			} elseif ( count( $cps ) === 2 && $cps[1] === 0x20E3 ) {
				array_splice( $cps, 1, 0, array( 0xFE0F ) );
			}

			$out = '';
			foreach ( $cps as $cp ) {
				$out .= self::_utf8( $cp );
			}
			return $out;
		}

		// Literal emoji: valid UTF-8, short, contains non-ASCII, no markup/whitespace
		if ( strlen( $v ) > 64 || ! preg_match( '//u', $v ) || ! preg_match( '/[^\x00-\x7F]/', $v ) || preg_match( '/[\x00-\x20<>&"\'\x7F]/', $v ) ) {
			return '';
		}

		return $v;
	}

	/**
	 * Unicode fork: one codepoint as UTF-8 ( no mbstring dependency ).
	 *
	 * @since 2.6.1
	 * @access private
	 */
	private static function _utf8( $cp ) {
		if ( $cp < 0x80 ) {
			return chr( $cp );
		}
		if ( $cp < 0x800 ) {
			return chr( 0xC0 | ( $cp >> 6 ) ) . chr( 0x80 | ( $cp & 0x3F ) );
		}
		if ( $cp < 0x10000 ) {
			return chr( 0xE0 | ( $cp >> 12 ) ) . chr( 0x80 | ( ( $cp >> 6 ) & 0x3F ) ) . chr( 0x80 | ( $cp & 0x3F ) );
		}
		return chr( 0xF0 | ( $cp >> 18 ) ) . chr( 0x80 | ( ( $cp >> 12 ) & 0x3F ) ) . chr( 0x80 | ( ( $cp >> 6 ) & 0x3F ) ) . chr( 0x80 | ( $cp & 0x3F ) );
	}

	/**
	 * Unicode fork: literal emoji / codepoint spec => normalized lowercase hex codepoints joined by '-'. '' if invalid.
	 *
	 * @since 2.6.1
	 * @access public
	 */
	public static function to_hex( $v ) {
		$char = self::to_char( $v );
		if ( $char === '' ) {
			return '';
		}

		$out = array();
		$len = strlen( $char );
		for ( $i = 0; $i < $len; ) {
			$c = ord( $char[ $i ] );
			if ( $c < 0x80 ) {
				$cp = $c;
				$n  = 1;
			} elseif ( ( $c & 0xE0 ) === 0xC0 ) {
				$cp = $c & 0x1F;
				$n  = 2;
			} elseif ( ( $c & 0xF0 ) === 0xE0 ) {
				$cp = $c & 0x0F;
				$n  = 3;
			} else {
				$cp = $c & 0x07;
				$n  = 4;
			}
			for ( $j = 1; $j < $n; $j++ ) {
				$cp = ( $cp << 6 ) | ( ord( $char[ $i + $j ] ) & 0x3F );
			}
			$out[] = dechex( $cp );
			$i    += $n;
		}

		return implode( '-', $out );
	}

	/**
	 * Unicode fork: parse the "Custom Emojis" setting.
	 *
	 * One per line: `name = 1F525`, `name = 🔥`, `name: U+1F525`, or just `name` ( resolved from the bundled catalog ).
	 * Returns name => value ( value '' for name-only lines ). Invalid lines are collected in $rejected.
	 *
	 * @since 2.6.1
	 * @access public
	 */
	public static function parse_custom( $raw, &$rejected = null ) {
		$rejected = array();
		$out      = array();

		if ( is_array( $raw ) ) {
			$raw = implode( "\n", $raw );
		}
		if ( ! is_string( $raw ) || trim( $raw ) === '' ) {
			return $out;
		}

		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( $line );
			if ( $line === '' || $line[0] === '#' ) {
				continue;
			}

			$parts = preg_split( '/\s*[=:]\s*/', $line, 2 );
			$name  = strtolower( trim( $parts[0] ) );
			$value = isset( $parts[1] ) ? trim( $parts[1] ) : '';

			if ( ! self::valid_name( $name ) || ( $value !== '' && self::to_char( $value ) === '' ) ) {
				$rejected[] = $line;
				continue;
			}

			$out[ $name ] = $value;
		}

		return $out;
	}

	/**
	 * Enqueue js
	 *
	 * @since  1.0
	 * @access public
	 */
	public function enqueue_scripts() {
		$this->enqueue_style();

		wp_register_script( 'remoji-js', REMOJI_URL . 'assets/remoji.js', array( 'jquery' ), Core::VER, false );

		$localize_data                            = array();
		$localize_data['show_reaction_panel_url'] = get_rest_url( null, 'remoji/v1/show_reaction_panel' );
		$localize_data['reaction_submit_url']     = get_rest_url( null, 'remoji/v1/add' );

		// Only logged-in users get a REST nonce; guests go nonce-less so cached pages still submit ( support #8 )
		if ( is_user_logged_in() ) {
			$localize_data['nonce'] = wp_create_nonce( 'wp_rest' );
		}

		if ( Conf::val( 'postview' ) && is_singular() && ! is_preview() ) {
			// Load ajax count
			$localize_data['postview_url']   = get_rest_url( null, 'remoji/v1/postview' );
			$localize_data['postview_delay'] = Conf::val( 'postview_delay' );
			$localize_data['postview_id']    = get_the_ID();
		}

		wp_localize_script( 'remoji-js', 'remoji', $localize_data );
		wp_enqueue_script( 'remoji-js' );
	}

	/**
	 * Load style
	 *
	 * @since 1.0
	 */
	public function enqueue_style() {
		wp_enqueue_style( 'remoji-css', REMOJI_URL . 'assets/css/remoji.css', array(), Core::VER, 'all' );

		// Unicode fork: optional self-hosted emoji font ( e.g. a subset of Noto Color Emoji ) so every visitor sees the same glyphs.
		// add_filter( 'remoji_emoji_font_url', function () { return get_stylesheet_directory_uri() . '/fonts/emoji-subset.woff2'; } );
		$font_url = apply_filters( 'remoji_emoji_font_url', '' );
		if ( $font_url ) {
			$css = '@font-face{font-family:"Remoji Emoji";src:url("' . esc_url_raw( $font_url ) . '");font-display:swap;}'
				. '.remoji_emoji{font-family:"Remoji Emoji","Apple Color Emoji","Segoe UI Emoji","Noto Color Emoji",sans-serif;}';
			wp_add_inline_style( 'remoji-css', $css );
		}
	}

	/**
	 * Load css/js for admin
	 *
	 * @since 1.2
	 */
	public function enqueue_admin() {
		// Only enqueue on plugin pages
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only inspection of the current admin request to build a link / decide asset loading; no state change.
		if ( empty( $_GET['page'] ) || strpos( sanitize_key( wp_unslash( $_GET['page'] ) ), 'remoji' ) !== 0 ) {
			return;
		}

		$this->enqueue_style();

		wp_register_script( 'remoji_admin', REMOJI_URL . 'assets/remoji_admin.js', array( 'jquery' ), Core::VER, false );

		wp_enqueue_script( 'remoji_admin' );
	}

	/**
	 * Register this setting to save
	 *
	 * @since  1.2
	 * @access public
	 */
	public function enroll( $id ) {
		echo '<input type="hidden" name="_settings-enroll[]" value="' . esc_attr( $id ) . '" />';
	}

	/**
	 * Build a textarea
	 *
	 * @since 1.2
	 * @access public
	 */
	public function build_textarea( $id, $cols = false, $val = null ) {
		if ( $val === null ) {
			$val = Conf::val( $id );

			if ( is_array( $val ) ) {
				$val = implode( "\n", $val );
			}
		}

		if ( ! $cols ) {
			$cols = 80;
		}

		$this->enroll( $id );

		echo "<textarea name='" . esc_attr( $id ) . "' rows='9' cols='" . (int) $cols . "'>" . esc_textarea( $val ) . '</textarea>';
	}

	/**
	 * Build a text input field
	 *
	 * @since 1.2
	 * @access public
	 */
	public function build_input( $id, $cls = null, $val = null, $type = 'text' ) {
		if ( $val === null ) {
			$val = Conf::val( $id );
		}

		$label_id = preg_replace( '|\W|', '', $id );

		if ( $type === 'text' ) {
			$cls = "regular-text $cls";
		}

		$this->enroll( $id );

		echo "<input type='" . esc_attr( $type ) . "' class='" . esc_attr( $cls ) . "' name='" . esc_attr( $id ) . "' value='" . esc_attr( $val ) . "' id='input_" . esc_attr( $label_id ) . "' /> ";
	}

	/**
	 * Build a switch div html snippet
	 *
	 * @since 1.2
	 * @access public
	 */
	public function build_switch( $id, $title_list = false ) {
		$this->enroll( $id );

		echo '<div class="remoji-switch">';

		if ( ! $title_list ) {
			$title_list = array(
				__( 'OFF', 'remoji' ),
				__( 'ON', 'remoji' ),
			);
		}

		foreach ( $title_list as $k => $v ) {
			$this->_build_radio( $id, $k, $v );
		}

		echo '</div>';
	}

	/**
	 * Build a radio input html codes and output
	 *
	 * @since 1.2
	 * @access private
	 */
	private function _build_radio( $id, $val, $txt ) {
		$id_attr = 'input_radio_' . preg_replace( '|\W|', '', $id ) . '_' . $val;

		if ( ! is_string( Conf::$_default_options[ $id ] ) ) {
			$checked = (int) Conf::val( $id ) === (int) $val ? ' checked ' : '';
		} else {
			$checked = Conf::val( $id ) === $val ? ' checked ' : '';
		}

		echo "<input type='radio' autocomplete='off' name='" . esc_attr( $id ) . "' id='" . esc_attr( $id_attr ) . "' value='" . esc_attr( $val ) . "'" . ( $checked ? ' checked' : '' ) . " /> <label for='" . esc_attr( $id_attr ) . "'>" . esc_html( $txt ) . '</label>';
	}

	/**
	 * Build a checkbox
	 *
	 * @access public
	 */
	public function build_checkbox( $id, $title, $checked = null, $value = 1 ) {
		if ( $checked === null && Conf::val( $id ) ) {
			$checked = true;
		}
		$checked = $checked ? ' checked ' : '';

		$label_id = preg_replace( '|\W|', '', $id );

		if ( $value !== 1 ) {
			$label_id .= '_' . $value;
		}

		$this->enroll( $id );

		echo "<div class='remoji-tick'>
			<input type='checkbox' name='" . esc_attr( $id ) . "' id='input_checkbox_" . esc_attr( $label_id ) . "' value='" . esc_attr( $value ) . "'" . ( $checked ? ' checked' : '' ) . " />
			<label for='input_checkbox_" . esc_attr( $label_id ) . "'>" . esc_html( $title ) . '</label>
		</div>';
	}

	/**
	 * Builds a single msg.
	 *
	 * @access private
	 */
	private static function _build_msg( $color, $str ) {
		return '<div class="' . $color . ' is-dismissible"><p>' . $str . '</p></div>';
	}

	/**
	 * Display info notice
	 *
	 * @access public
	 */
	public static function info( $msg, $echo_now = false ) {
		self::_add_notice( self::NOTICE_BLUE, $msg, $echo_now );
	}

	/**
	 * Display note notice
	 *
	 * @access public
	 */
	public static function note( $msg, $echo_now = false ) {
		self::_add_notice( self::NOTICE_YELLOW, $msg, $echo_now );
	}

	/**
	 * Display success notice
	 *
	 * @access public
	 */
	public static function succeed( $msg, $echo_now = false ) {
		self::_add_notice( self::NOTICE_GREEN, $msg, $echo_now );
	}

	/**
	 * Display error notice
	 *
	 * @access public
	 */
	public static function error( $msg, $echo_now = false ) {
		self::_add_notice( self::NOTICE_RED, $msg, $echo_now );
	}

	/**
	 * Adds a notice to display on the admin page
	 *
	 * @access private
	 */
	private static function _add_notice( $color, $msg, $echo_now = false ) {
		// Bypass adding for CLI or cron
		if ( defined( 'DOING_CRON' ) ) {
			// WP CLI will show the info directly
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				$msg = wp_strip_all_tags( $msg );
				if ( $color === self::NOTICE_RED ) {
					\WP_CLI::error( $msg );
				} else {
					\WP_CLI::success( $msg );
				}
			}
			return;
		}

		if ( $echo_now ) {
			echo wp_kses_post( self::_build_msg( $color, $msg ) );
			return;
		}

		$messages = get_option( self::DB_MSG );

		if ( is_array( $msg ) ) {
			foreach ( $msg as $str ) {
				$messages[] = self::_build_msg( $color, $str );
			}
		} else {
			$messages[] = self::_build_msg( $color, $msg );
		}
		update_option( self::DB_MSG, $messages );
	}

	/**
	 * Display admin msg
	 *
	 * @access public
	 */
	public function display_msg() {
		// One time msg
		$messages = get_option( self::DB_MSG );
		if ( is_array( $messages ) ) {
			$messages = array_unique( $messages );

			$added_thickbox = false;
			foreach ( $messages as $msg ) {
				// Added for popup links
				if ( strpos( $msg, 'TB_iframe' ) && ! $added_thickbox ) {
					add_thickbox();
					$added_thickbox = true;
				}
				echo wp_kses_post( $msg );
			}
		}
		delete_option( self::DB_MSG );
	}
}
