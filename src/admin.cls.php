<?php
/**
 * Admin class
 *
 * @since 1.2
 */
namespace remoji;

defined( 'WPINC' ) || exit;

class Admin extends Instance {
	protected static $_instance;

	/**
	 * Init admin
	 *
	 * @since  1.2
	 * @access public
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_filter( 'plugin_action_links_remoji/remoji.php', array( $this, 'add_plugin_links' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_reaction_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_reaction_meta_box' ), 10, 2 );

		add_action( 'admin_enqueue_scripts', array( GUI::get_instance(), 'enqueue_admin' ) );
	}

	/**
	 * Admin setting page
	 *
	 * @since  1.2
	 * @access public
	 */
	public function admin_menu() {
		add_options_page( 'Remoji', 'Remoji', 'manage_options', 'remoji', array( $this, 'setting_page' ) );
	}

	/**
	 * admin_init
	 *
	 * @since  1.2.2
	 * @access public
	 */
	public function admin_init() {
		if ( get_transient( 'remoji_activation_redirect' ) ) {
			delete_transient( 'remoji_activation_redirect' );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only inspection of the current admin request to build a link / decide asset loading; no state change.
			if ( ! is_network_admin() && ! isset( $_GET['activate-multi'] ) ) {
				wp_safe_redirect( menu_page_url( 'remoji', 0 ) );
			}
		}

		add_action( 'admin_notices', array( GUI::get_instance(), 'display_msg' ) );

		add_filter( 'manage_edit-post_columns', array( $this, 'post_row_title' ) );
		add_action( 'manage_posts_custom_column', array( $this, 'post_row_data' ) );
		add_filter( 'manage_edit-post_sortable_columns', array( $this, 'post_row_sortable' ) );
	}

	/**
	 * Post Admin Menu -> Postview Column Title
	 *
	 * @since 2.0
	 * @access public
	 */
	public function post_row_title( $posts_columns ) {
		$posts_columns['postview'] = __( 'Postview', 'remoji' );

		return $posts_columns;
	}

	/**
	 * Post Admin Menu -> Postview Column List
	 *
	 * @since 2.0
	 * @access public
	 */
	public function post_row_data( $column ) {
		if ( $column === 'postview' ) {
			global $post;
			echo esc_html( Postview::get_instance()->get_num( $post->ID ) );
		}
	}

	/**
	 * Post Admin Menu -> Postview Column sortable
	 *
	 * @since 2.0
	 * @access public
	 */
	public function post_row_sortable( $columns ) {
		$columns['postview'] = __( 'Postview', 'remoji' );
		return $columns;
	}

	/**
	 * Plugin link
	 *
	 * @since  1.1
	 * @access public
	 */
	public function add_plugin_links( $links ) {
		$links[] = '<a href="' . menu_page_url( 'remoji', 0 ) . '">' . __( 'Settings', 'remoji' ) . '</a>';

		return $links;
	}

	/**
	 * 加入單篇內容的 Remoji 控制選項。
	 *
	 * @since 2.5.1
	 * @access public
	 */
	public function add_reaction_meta_box( $post_type ) {
		if ( ! $this->_supports_reaction_meta_box( $post_type ) ) {
			return;
		}

		add_meta_box(
			'remoji-reaction-options',
			__( 'Remoji', 'remoji' ),
			array( $this, 'reaction_meta_box' ),
			$post_type,
			'side',
			'default'
		);
	}

	/**
	 * 顯示單篇內容的 Remoji 控制選項。
	 *
	 * @since 2.5.1
	 * @access public
	 */
	public function reaction_meta_box( $post ) {
		wp_nonce_field( 'remoji_post_options', 'remoji_post_options_nonce' );

		$disabled = Reaction::post_disabled( $post->ID );
		?>
		<p>
			<label>
				<input type="checkbox" name="remoji_disable_reactions" value="1" <?php checked( $disabled ); ?>>
				<?php echo esc_html__( 'Disable Remoji reactions for this post', 'remoji' ); ?>
			</label>
		</p>
		<p class="description">
			<?php echo esc_html__( 'When enabled, Remoji will not render or accept reactions for this post or its comments.', 'remoji' ); ?>
		</p>
		<?php
	}

	/**
	 * 儲存單篇內容的 Remoji 控制選項。
	 *
	 * @since 2.5.1
	 * @access public
	 */
	public function save_reaction_meta_box( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified immediately below before saving post meta.
		if ( empty( $_POST['remoji_post_options_nonce'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- this is the nonce field being verified for the post meta save.
		$nonce = sanitize_text_field( wp_unslash( $_POST['remoji_post_options_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'remoji_post_options' ) ) {
			return;
		}

		if ( ! $post || ! $this->_supports_reaction_meta_box( $post->post_type ) ) {
			return;
		}

		$post_type = get_post_type_object( $post->post_type );
		if ( ! $post_type || ! current_user_can( $post_type->cap->edit_post, $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce and capability checks have already passed.
		if ( ! empty( $_POST['remoji_disable_reactions'] ) ) {
			update_post_meta( $post_id, Reaction::META_DISABLED, '1' );
		} else {
			delete_post_meta( $post_id, Reaction::META_DISABLED );
		}
	}

	/**
	 * Display and save options
	 *
	 * @since  1.2
	 * @access public
	 */
	public function setting_page() {
		Data::get_instance()->tb_create( 'history' );

		if ( ! empty( $_POST ) ) {
			check_admin_referer( 'remoji' );

			$raw_data = self::cleanup_text( $_POST );

			// Save options
			$list = array();

			foreach ( Conf::get_instance()->get_options() as $id => $v ) {
				if ( $id === '_ver' ) {
					continue;
				}

				$list[ $id ] = isset( $raw_data[ $id ] ) ? $raw_data[ $id ] : false;
			}

			// Unicode fork: normalize custom emoji lines to "name = hex-codepoints" ( keeps 4-byte emoji out of the options table )
			$custom_rejected = array();
			$custom          = GUI::parse_custom( isset( $list['emoji_custom'] ) ? $list['emoji_custom'] : '', $custom_rejected );
			$custom_lines    = array();
			foreach ( $custom as $name => $value ) {
				if ( $value !== '' ) {
					$custom_lines[] = $name . ' = ' . GUI::to_hex( $value );
				} elseif ( GUI::get_instance()->emoji( $name ) ) {
					$custom_lines[] = $name;
				} else {
					$custom_rejected[] = $name;
					unset( $custom[ $name ] );
				}
			}
			$list['emoji_custom'] = implode( "\n", $custom_lines );
			if ( $custom_rejected ) {
				GUI::error( __( 'Some custom emoji lines were not understood and were dropped:', 'remoji' ) . ' ' . implode( ', ', $custom_rejected ) );
			}

			// Normalize the emoji whitelist: split lines, trim (incl CR), drop blanks/dupes, keep only real emoji keys.
			// Unicode fork: any name from the bundled catalog or the custom list is valid.
			if ( ! empty( $list['emoji_whitelist'] ) ) {
				$valid                   = array_merge( array_keys( GUI::get_instance()->catalog() ), array_keys( $custom ) );
				$list['emoji_whitelist'] = self::_clean_key_list( $list['emoji_whitelist'], $valid );
			}

			foreach ( $list as $id => $v ) {
				Conf::update( $id, $v );
			}

			GUI::succeed( __( 'Options saved successfully!', 'remoji' ), true );
		}

		require_once REMOJI_DIR . 'tpl/entry.tpl.php';
	}

	/**
	 * Clean up the input string of any extra slashes/spaces.
	 *
	 * @access public
	 */
	public static function cleanup_text( $input ) {
		if ( is_array( $input ) ) {
			return array_map( __CLASS__ . '::cleanup_text', $input );
		}

		return stripslashes( trim( $input ) );
	}

	/**
	 * Normalize a newline/array list of keys: trim (incl CR), drop blanks + duplicates, keep only $valid keys.
	 *
	 * @since 2.5
	 * @access private
	 */
	private static function _clean_key_list( $raw, $valid ) {
		if ( ! is_array( $raw ) ) {
			$raw = explode( "\n", $raw );
		}

		$out = array();
		foreach ( $raw as $k ) {
			$k = trim( $k );
			if ( $k === '' || in_array( $k, $out, true ) || ! in_array( $k, $valid, true ) ) {
				continue;
			}
			$out[] = $k;
		}

		return $out;
	}

	/**
	 * 此 post type 是否能在編輯頁顯示單篇 Remoji 選項。
	 *
	 * @since 2.5.1
	 * @access private
	 */
	private function _supports_reaction_meta_box( $post_type ) {
		$post_type = get_post_type_object( $post_type );

		return $post_type
			&& ! empty( $post_type->public )
			&& ! empty( $post_type->show_ui )
			&& ! in_array( $post_type->name, (array) Conf::val( 'reaction_disabled_types' ), true )
			&& ( Conf::val( 'post_emoji' ) || Conf::val( 'comment_emoji' ) );
	}
}
