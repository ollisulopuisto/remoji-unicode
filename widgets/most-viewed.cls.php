<?php
/**
 * Widget class
 *
 * @since 1.0
 */
namespace remoji\widgets;

defined( 'WPINC' ) || exit;

use remoji\Postview;

/**
 * Widget last reacted
 */
class Most_Viewed extends \WP_Widget {
	const CONF_TPL   = '<a href="{link}">{title}</a> <font class="remoji-success">{counter}</font>';
	const CONF_LIMIT = 10;

	public function __construct() {
		parent::__construct(
			'most_viewed',
			__( 'Most Viewed Posts', 'remoji' ),
			array( 'description' => __( 'Remoji most viewed posts list', 'remoji' ) )
		);
	}

	public function widget( $args, $instance ) {
		echo wp_kses_post( $args['before_widget'] );

		$limit = ! empty( $instance['limit'] ) ? max( 1, (int) $instance['limit'] ) : self::CONF_LIMIT;
		$tpl   = ! empty( $instance['tpl'] ) ? $instance['tpl'] : self::CONF_TPL;

		if ( ! empty( $instance['title'] ) ) {
			echo wp_kses_post( $args['before_title'] ) . esc_html( apply_filters( 'widget_title', $instance['title'] ) ) . wp_kses_post( $args['after_title'] );
		}

		echo '<ul>';

		$list       = new \WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => $limit,
				'orderby'        => 'meta_value_num',
				'order'          => 'desc',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- ordering the Most Viewed widget requires the postview meta_key.
				'meta_key'       => Postview::POST_META,
			)
		);
		$__postview = Postview::get_instance();
		if ( $list->have_posts() ) {
			foreach ( $list->posts as $v ) {
				$item_tpl = $tpl;

				$item_tpl = str_replace( '{title}', get_the_title( $v->ID ), $item_tpl );
				$item_tpl = str_replace( '{link}', get_post_permalink( $v->ID ), $item_tpl );
				$item_tpl = str_replace( '{counter}', $__postview->get_num( $v->ID ), $item_tpl );

				echo '<li>' . wp_kses_post( $item_tpl ) . '</li>';
			}
		} else {
			echo '<li>N/A</li>';
		}

		echo '</ul>';

		echo wp_kses_post( $args['after_widget'] );
	}

	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Most Viewed Posts', 'remoji' );
		$tpl   = ! empty( $instance['tpl'] ) ? $instance['tpl'] : self::CONF_TPL;
		$limit = ! empty( $instance['limit'] ) ? absint( $instance['limit'] ) : self::CONF_LIMIT;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php echo esc_html__( 'Title', 'remoji' ); ?>:</label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'tpl' ) ); ?>"><?php echo esc_html__( 'Template', 'remoji' ); ?>:</label>
			<textarea class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'tpl' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'tpl' ) ); ?>" type="text" cols="30" rows="5"><?php echo esc_textarea( $tpl ); ?></textarea>
			<span class="description"><?php echo esc_html__( 'Supported tags', 'remoji' ); ?>: <code>{link}</code> <code>{title}</code> <code>{counter}</code></span>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"><?php echo esc_html__( 'Limit', 'remoji' ); ?>:</label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>" type="number" size="3" value="<?php echo esc_attr( $limit ); ?>">
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance = array();

		$instance['title'] = ! empty( $new_instance['title'] ) ? wp_strip_all_tags( $new_instance['title'] ) : '';
		$instance['tpl']   = ! empty( $new_instance['tpl'] ) ? $new_instance['tpl'] : self::CONF_TPL;
		$instance['limit'] = ! empty( $new_instance['limit'] ) ? max( 1, (int) $new_instance['limit'] ) : self::CONF_LIMIT;

		return $instance;
	}
}
