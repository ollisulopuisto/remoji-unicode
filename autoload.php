<?php
/**
 * Auto registration
 *
 * @since       1.0
 */
defined( 'WPINC' ) || exit;

if ( ! function_exists( 'remoji_autoload' ) ) {
	function remoji_autoload( $cls ) {
		if ( strpos( $cls, 'remoji' ) !== 0 ) {
			return;
		}

		$file = explode( '\\', $cls );
		array_shift( $file );
		$file = implode( '/', $file );
		$file = str_replace( '_', '-', strtolower( $file ) );

		if ( strpos( $file, 'lib/' ) === 0 || strpos( $file, 'widgets/' ) === 0 || strpos( $file, 'cli/' ) === 0 ) {
			$file = REMOJI_DIR . $file . '.cls.php';
		} else {
			$file = REMOJI_DIR . 'src/' . $file . '.cls.php';
		}

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}

spl_autoload_register( 'remoji_autoload' );
