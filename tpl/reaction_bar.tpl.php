<?php
namespace remoji;

defined( 'WPINC' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.GlobalVariablesOverride -- template partial included in class-method scope; its variables come from that scope, not the global namespace.

$reactors = isset( $reactors ) ? $reactors : array();
?>
<div class="remoji_bar">
	<?php if ( $emoji_list ) : ?>
		<?php foreach ( $emoji_list as $emoji => $count ) : ?>
			<?php $names = ! empty( $reactors[ $emoji ] ) ? $reactors[ $emoji ] : array(); ?>
		<div class="remoji_container" data-remoji-id="<?php echo (int) $remoji_id; ?>" data-remoji-type="<?php echo esc_attr( $remoji_type ); ?>" data-remoji-name="<?php echo esc_attr( $emoji ); ?>"
			<?php
			if ( $names ) :
				?>
			title="<?php echo esc_attr( implode( ', ', $names ) ); ?>"<?php endif; ?>>
			<?php echo $this->emoji_html( $emoji ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- emoji_html() escapes each part. ?>
			<span class="remoji_count"><?php echo (int) $count; ?></span>
		</div>
	<?php endforeach; ?>
	<?php endif; ?>

	<?php if ( Conf::val( 'guest' ) || is_user_logged_in() ) : ?>
	<div class="remoji_add_container" data-remoji-id="<?php echo (int) $remoji_id; ?>" data-remoji-type="<?php echo esc_attr( $remoji_type ); ?>">
		<div class="remoji_add_icon"></div>
	</div>
	<?php endif; ?>

	<div class="remoji_error_bar" data-remoji-id="<?php echo (int) $remoji_id; ?>" data-remoji-type="<?php echo esc_attr( $remoji_type ); ?>" style="display: none;"><?php echo esc_html__( 'Error happened.', 'remoji' ); ?></div>
</div>
