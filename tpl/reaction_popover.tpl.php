<?php
namespace remoji;

defined( 'WPINC' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.GlobalVariablesOverride -- template partial included in class-method scope; its variables come from that scope, not the global namespace.

$list           = $this->emoji();
$row_count      = max( 1, (int) ceil( count( $list ) / 9 ) );
$div_max_height = $row_count * 33;
?>

<div id="remoji_panel">
	<div class="remoji_picker_list">
		<div style="overflow: visible; height: 0px; width: 0px;">
			<div class="remoji_picker_list_scroller" style="height: <?php echo esc_attr( $div_max_height ); ?>px;">
				<div class="" style="width: auto; height: <?php echo esc_attr( $div_max_height ); ?>px; max-width: 100%; max-height: <?php echo esc_attr( $div_max_height ); ?>px; overflow: hidden; position: relative;">
				<?php $i = 0; ?>
				<?php foreach ( $list as $word => $emoji ) : ?>
					<?php if ( 0 === $i % 9 ) : ?>
					<div class="remoji_picker_row" style="top: <?php echo (int) ( floor( $i / 9 ) * 33 + 3 ); ?>px">
					<?php endif; ?>
					<?php
						echo '<div data-remoji-color="' . (int) ( $i % 6 ) . '" data-remoji-name="' . esc_attr( $word ) . '" class="remoji_picker_item" title="' . esc_attr( $word ) . '">' . $this->emoji_html( $word ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- emoji_html() escapes each part.
					?>
					<?php if ( 8 === $i % 9 ) : ?>
					</div>
					<?php endif; ?>
					<?php ++$i; ?>
				<?php endforeach; ?>
				<?php if ( $i > 0 && 0 !== $i % 9 ) : ?>
					</div>
				<?php endif; ?>

				</div>
			</div>
		</div>
	</div>
	<div class="remoji_picker_footer">
		<div id="remoji_preview"></div>
		<div id="remoji_preview_text"></div>
		<div class="remoji_picker_handy">
			<?php $i = 0; ?>
			<?php foreach ( $this->emoji_handy() as $word ) : ?>
				<?php
				echo '<div data-remoji-color="' . (int) ( $i % 6 ) . '" data-remoji-name="' . esc_attr( $word ) . '" class="remoji_picker_item" title="' . esc_attr( $word ) . '">' . $this->emoji_html( $word ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- emoji_html() escapes each part.
				?>
				<?php ++$i; ?>
			<?php endforeach; ?>
		</div>
	</div>
</div>
