<?php
namespace remoji;

defined( 'WPINC' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.GlobalVariablesOverride -- template partial included in class-method scope; its variables come from that scope, not the global namespace.

$__gui = GUI::get_instance();

?>
<form method="post" action="<?php menu_page_url( 'remoji' ); ?>" class="remoji-relative">
<?php wp_nonce_field( 'remoji' ); ?>

<h3 class="remoji-title-short"><?php echo esc_html__( 'Emoji Reaction Settings', 'remoji' ); ?></h3>

<table class="wp-list-table striped remoji-table"><tbody>
	<tr>
		<th><?php echo esc_html__( 'Emoji Reaction on Posts', 'remoji' ); ?></th>
		<td>
			<?php $__gui->build_switch( 'post_emoji' ); ?>
			<div class="remoji-desc">
				<?php echo esc_html__( 'Allow emoji reactions to posts.', 'remoji' ); ?>
			</div>
		</td>
	</tr>

	<tr>
		<th><?php echo esc_html__( 'Emoji Reaction on Comments', 'remoji' ); ?></th>
		<td>
			<?php $__gui->build_switch( 'comment_emoji' ); ?>
			<div class="remoji-desc">
				<?php echo esc_html__( 'Allow emoji reactions to comments.', 'remoji' ); ?>
			</div>
		</td>
	</tr>

	<tr>
		<th><?php echo esc_html__( 'Guest Reaction', 'remoji' ); ?></th>
		<td>
			<?php $__gui->build_switch( 'guest' ); ?>
			<div class="remoji-desc">
				<?php echo esc_html__( 'Allow guest visitors to send the reactions. If turned OFF, only logged-in users can react.', 'remoji' ); ?>
			</div>
		</td>
	</tr>

	<tr>
		<th><?php echo esc_html__( 'GDPR Compliance', 'remoji' ); ?></th>
		<td>
			<?php $__gui->build_switch( 'gdpr' ); ?>
			<div class="remoji-desc">
				<?php echo esc_html__( 'Visitor IPs are always stored only as a keyed hash (HMAC) and no location data is collected. With this feature turned on, reactor names are also never shown.', 'remoji' ); ?>
			</div>
		</td>
	</tr>

	<tr>
		<th><?php echo esc_html__( 'Max Emojis Per IP', 'remoji' ); ?></th>
		<td>
			<p><?php $__gui->build_input( 'max_emoji_per_ip', 'remoji-input-short' ); ?></p>

			<div class="remoji-desc">
				<?php echo esc_html__( 'Only allow these amount of emojis per IP per post.', 'remoji' ); ?>
				<?php echo esc_html__( 'Set to 0 for unlimited reactions.', 'remoji' ); ?>
			</div>
		</td>
	</tr>

	<tr>
		<th><?php echo esc_html__( 'Withdraw Reaction', 'remoji' ); ?></th>
		<td>
			<?php $__gui->build_switch( 'reaction_withdraw' ); ?>
			<div class="remoji-desc">
				<?php echo esc_html__( 'Allow a visitor to withdraw their own reaction by clicking the same emoji again.', 'remoji' ); ?>
			</div>
		</td>
	</tr>

	<tr>
		<th><?php echo esc_html__( 'Show Reactors', 'remoji' ); ?></th>
		<td>
			<?php $__gui->build_switch( 'show_reactors' ); ?>
			<div class="remoji-desc">
				<?php echo esc_html__( 'Show who reacted (logged-in user names) on hover. Guests are shown as a generic label. Disabled automatically when GDPR mode is on.', 'remoji' ); ?>
			</div>
		</td>
	</tr>

	<tr>
		<th><?php echo esc_html__( 'Reaction Notification', 'remoji' ); ?></th>
		<td>
			<?php $__gui->build_switch( 'reaction_notify' ); ?>
			<div class="remoji-desc">
				<?php echo esc_html__( 'Email the content author when their post/comment receives a new reaction (throttled; author self-reactions are skipped).', 'remoji' ); ?>
			</div>
		</td>
	</tr>

	<tr>
		<th><?php echo esc_html__( 'Limit Emojis', 'remoji' ); ?></th>
		<td>
			<?php $__gui->build_textarea( 'emoji_whitelist' ); ?>
			<div class="remoji-desc">
				<?php echo esc_html__( 'One emoji name per line to restrict the picker to exactly these emojis, in this order. Leave empty to offer the default list below.', 'remoji' ); ?>
				<?php echo esc_html__( 'Any name from the bundled catalog (about 2,200 Slack/GitHub-style shortnames such as fire, skull, clown_face, eyes, 100) or from Custom Emojis works here.', 'remoji' ); ?>
			</div>
			<div class="remoji-desc">
				<?php echo esc_html__( 'Default list', 'remoji' ); ?>:
				<?php foreach ( $__gui->all_emoji() as $remoji_name => $remoji_cp ) : ?>
					<span title="<?php echo esc_attr( $remoji_name ); ?>" style="white-space:nowrap;margin-right:8px;"><?php echo $__gui->emoji_html( $remoji_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- emoji_html() escapes each part. ?> <code><?php echo esc_html( $remoji_name ); ?></code></span>
				<?php endforeach; ?>
			</div>
		</td>
	</tr>

	<tr>
		<th><?php echo esc_html__( 'Custom Emojis', 'remoji' ); ?></th>
		<td>
			<?php $__gui->build_textarea( 'emoji_custom' ); ?>
			<div class="remoji-desc">
				<?php echo esc_html__( 'Add any Unicode emoji to the default list. One per line:', 'remoji' ); ?>
				<code>name = 1F525</code>, <code>name = U+1FAE0</code>, <code>name = 1F9D1-200D-1F4BB</code>,
				<?php echo esc_html__( 'or paste the emoji itself', 'remoji' ); ?> (<code>name = &#x1F525;</code>).
				<?php echo esc_html__( 'A bare catalog name on its own line (e.g. skull) also works.', 'remoji' ); ?>
				<?php echo esc_html__( 'Names may use letters, digits, _ + and -. Pasted emojis are saved as codepoints.', 'remoji' ); ?>
			</div>
		</td>
	</tr>

	<tr>
		<th><?php echo esc_html__( 'Auto-Append To Content', 'remoji' ); ?></th>
		<td>
			<?php $__gui->build_switch( 'post_emoji_auto' ); ?>
			<div class="remoji-desc">
				<?php echo esc_html__( 'Automatically append the reaction bar to post content.', 'remoji' ); ?>
				<?php echo esc_html__( 'Turn OFF to place it manually with the shortcode or PHP hook below.', 'remoji' ); ?>
				<br>
				<?php echo esc_html__( 'Shortcode', 'remoji' ); ?>: <code>[remoji]</code> <?php echo esc_html__( 'or', 'remoji' ); ?> <code>[remoji id="3"]</code>.
				<?php echo esc_html__( 'Hook', 'remoji' ); ?>: <code>do_action( 'remoji_reaction' );</code> <?php echo esc_html__( 'or', 'remoji' ); ?> <code>do_action( 'remoji_reaction', $post_id );</code>.
			</div>
		</td>
	</tr>

	<tr>
		<th><?php echo esc_html__( 'Disable Reactions On', 'remoji' ); ?></th>
		<td>
			<div class="remoji-desc">
				<?php echo esc_html__( 'Select the post types to disable reactions on (e.g. pages)', 'remoji' ); ?>:
			</div>
			<div class="remoji-tick-list">
				<?php foreach ( get_post_types() as $remoji_ptype ) : ?>
					<?php $__gui->build_checkbox( 'reaction_disabled_types[]', $remoji_ptype, in_array( $remoji_ptype, Conf::val( 'reaction_disabled_types' ), true ), $remoji_ptype ); ?>
				<?php endforeach; ?>
			</div>
		</td>
	</tr>
</tbody></table>

<h3 class="remoji-title-short"><?php echo esc_html__( 'Post View Settings', 'remoji' ); ?></h3>

<table class="wp-list-table striped remoji-table"><tbody>
	<tr>
		<th><?php echo esc_html__( 'Post View Count', 'remoji' ); ?></th>
		<td>
			<?php $__gui->build_switch( 'postview' ); ?>
			<div class="remoji-desc">
				<?php echo esc_html__( 'Enable post view count and show the number in posts.', 'remoji' ); ?>
				<?php echo esc_html__( 'Compatible with all cache plugins!', 'remoji' ); ?>
				<br><font class="remoji-success">
					<strong><?php echo esc_html__( 'API Supported', 'remoji' ); ?>:</strong>
					<?php echo esc_html__( 'Shortcode', 'remoji' ); ?>: <code>[views]</code> or <code>[views id="2"]</code>.
					<?php echo esc_html__( 'Hooks', 'remoji' ); ?>: <code>do_action( 'remoji_postview' );</code> or <code>do_action( 'remoji_postview', $any_post_id_to_quote );</code>.
				</font>
			</div>
		</td>
	</tr>

	<tr>
		<th><?php echo esc_html__( 'Delayed Count', 'remoji' ); ?></th>
		<td>
			<p><?php $__gui->build_input( 'postview_delay', 'remoji-input-short' ); ?> <?php echo esc_html__( 'Second(s)', 'remoji' ); ?></p>

			<div class="remoji-desc">
				<?php echo esc_html__( 'Only count the visit when the visitor stayed for longer than the above time.', 'remoji' ); ?>
				<?php echo esc_html__( 'This can avoid counting search engine bots and other invalid visitors.', 'remoji' ); ?>
				<?php echo esc_html__( 'Set to 0 to count right away.', 'remoji' ); ?>
			</div>
		</td>
	</tr>

	<tr>
		<th><?php echo esc_html__( 'Post View Template', 'remoji' ); ?></th>
		<td>
			<?php $__gui->build_input( 'postview_tpl', 'remoji-input-long' ); ?>

			<div class="remoji-desc">
				<?php /* translators: %s: the dashicons CSS class name, wrapped in a <code> tag. */ ?>
				<?php echo wp_kses_post( sprintf( __( 'You can replace %s to other WordPress icons.', 'remoji' ), '<code>dashicons-visibility</code>' ) ); ?>
				<?php echo esc_html__( 'For more icons please visit ', 'remoji' ); ?><a href="https://developer.wordpress.org/resource/dashicons/#visibility" target="_blank">https://developer.wordpress.org/resource/dashicons/#visibility</a>
			</div>
			<div class="remoji-desc">
				<?php echo esc_html__( 'The default template is', 'remoji' ); ?>: <code><?php echo esc_html( Conf::$_default_options['postview_tpl'] ); ?></code>
			</div>
		</td>
	</tr>

	<tr>
		<th><?php echo esc_html__( 'Append Post View To Content', 'remoji' ); ?></th>
		<td>
			<?php $__gui->build_switch( 'postview_show_in_content' ); ?>
			<div class="remoji-desc">
				<?php echo esc_html__( 'This option can show the post view number in the post content bottom.', 'remoji' ); ?>
			</div>
		</td>
	</tr>

	<tr>
		<th><?php echo esc_html__( 'Append Post View To Theme', 'remoji' ); ?></th>
		<td>
			<?php $__gui->build_switch( 'postview_show_in_themebar' ); ?>
			<div class="remoji-desc">
				<?php echo esc_html__( 'If you are using default WordPress theme, this option can append the views automatically after the comment count.', 'remoji' ); ?>
			</div>
			<div class="remoji-desc">
				<?php echo esc_html__( 'NOTE: Currently only support the following themes', 'remoji' ); ?>: <code>Twentytwenty</code>
			</div>
		</td>
	</tr>
</tbody></table>

<h3 class="remoji-title-short"><?php echo esc_html__( 'Comment Settings', 'remoji' ); ?></h3>

<table class="wp-list-table striped remoji-table"><tbody>
	<tr>
		<th><?php echo esc_html__( 'Disable Comment', 'remoji' ); ?></th>
		<td>
			<div class="remoji-desc">
				<?php echo esc_html__( 'Select the post types to disable comments on', 'remoji' ); ?>:
			</div>
			<div class="remoji-tick-list">
				<?php foreach ( get_post_types() as $remoji_ptype ) : ?>
					<?php $__gui->build_checkbox( 'disable_comment[]', $remoji_ptype, in_array( $remoji_ptype, Conf::val( 'disable_comment' ), true ), $remoji_ptype ); ?>
				<?php endforeach; ?>
			</div>
		</td>
	</tr>
</tbody></table>

<div class='remoji-top20'></div>

<?php submit_button( __( 'Save Changes', 'remoji' ), 'primary', 'remoji-submit' ); ?>
<?php submit_button( __( 'Save Changes', 'remoji' ), 'primary remoji-float-submit', 'remoji-float-submit' ); ?>

</form>
