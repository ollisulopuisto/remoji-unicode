<?php
namespace remoji;

defined( 'WPINC' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.GlobalVariablesOverride -- template partial included in class-method scope; its variables come from that scope, not the global namespace.

$__gui      = GUI::get_instance();
$__reaction = Reaction::get_instance();

$list       = $__reaction->history_list( 20 );
$count      = $__reaction->count_list();
$pagination = Util::pagination( $count, 20 );
?>
<h3 class="remoji-title-short">
	<?php echo esc_html__( 'Reaction Log', 'remoji' ); ?>
</h3>

<?php echo esc_html__( 'Total', 'remoji' ) . ': ' . (int) $count; ?>

<?php echo wp_kses_post( $pagination ); ?>

<table class="wp-list-table widefat striped">
	<thead>
	<tr>
		<th>#</th>
		<th><?php echo esc_html__( 'Date', 'remoji' ); ?></th>
		<th><?php echo esc_html__( 'Reactor', 'remoji' ); ?></th>
		<th><?php echo esc_html__( 'Reaction', 'remoji' ); ?></th>
		<th><?php echo esc_html__( 'Operation', 'remoji' ); ?></th>
		<th><?php echo esc_html__( 'Related Topic', 'remoji' ); ?></th>
	</tr>
	</thead>
	<tbody>
	<?php foreach ( $list as $v ) : ?>
		<tr>
			<td><?php echo (int) $v->id; ?></td>
			<td><?php echo esc_html( Util::readable_time( $v->dateline ) ); ?></td>
			<td>
				<?php
				// No IP or location is shown (only an opaque IP hash is stored). Logged-in reactors show their display name.
				$remoji_user = ! empty( $v->user_id ) ? get_userdata( (int) $v->user_id ) : false;
				echo esc_html( $remoji_user ? $remoji_user->display_name : __( 'Guest', 'remoji' ) );
				?>
			</td>
			<td>
				<?php
				$remoji_html = $__gui->emoji_html( $v->emoji, 'remoji_emoji_log' );
				echo $remoji_html ? $remoji_html : '<code>' . esc_html( $v->emoji ) . '</code>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- emoji_html() escapes each part.
				?>
			</td>
			<td>
				<a href="<?php echo esc_url( Util::build_url( Router::ACTION_REACTION, Reaction::TYPE_DEL, false, null, array( 'remoji_id' => $v->id ) ) ); ?>" class="button remoji-btn-danger"><?php echo esc_html__( 'Delete', 'remoji' ); ?></a>
			</td>
			<td>
				<?php if ( 'post' === $v->related_type ) : ?>
					<a href="<?php echo esc_url( get_post_permalink( $v->related_id ) ); ?>" target="_blank"><?php echo esc_html( get_the_title( $v->related_id ) ); ?></a>
				<?php elseif ( 'comment' === $v->related_type ) : ?>
					<?php $remoji_comment = get_comment( $v->related_id ); ?>
					<?php if ( $remoji_comment ) : ?>
						<?php echo esc_html__( 'Comment on', 'remoji' ); ?>
						<a href="<?php echo esc_url( get_comment_link( $v->related_id ) ); ?>" target="_blank"><?php echo esc_html( get_the_title( $remoji_comment->comment_post_ID ) ); ?></a>
					<?php else : ?>
						<?php echo esc_html__( 'Comment', 'remoji' ); ?> #<?php echo (int) $v->related_id; ?>
					<?php endif; ?>
				<?php else : ?>
					<?php echo esc_html( $v->related_type ); ?>
					<?php echo (int) $v->related_id; ?>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>

<?php echo wp_kses_post( $pagination ); ?>