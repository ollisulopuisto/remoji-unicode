<?php
/**
 * Widget class
 *
 * @since 1.7
 */
namespace remoji;

defined( 'WPINC' ) || exit;

class Widget extends Instance {
	protected static $_instance;

	public function init() {

		add_action(
			'widgets_init',
			function () {
				register_widget( 'remoji\widgets\Last_Reacted' );
				register_widget( 'remoji\widgets\Most_Viewed' );
			}
		);
	}
}
