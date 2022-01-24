<?php
/**
 * Plugin Name:     TinyBit Core
 * Plugin URI:      PLUGIN SITE HERE
 * Description:     PLUGIN DESCRIPTION HERE
 * Author:          YOUR NAME HERE
 * Author URI:      YOUR SITE HERE
 * Text Domain:     tinybit-core
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         TBC
 */

require __DIR__ . '/functions.php';

/*
 * TBC\Integrations\Cloudflare
 */
tbc_register_class_hooks(
	'TBC\Integrations\Cloudflare',
	[
		[ 'cloudflare_purge_by_url' ],
	]
);


/**
 * Register the class autoloader
 */
spl_autoload_register(
	function( $class ) {
		$class = ltrim( $class, '\\' );
		if ( 0 !== stripos( $class, 'TBC\\' ) ) {
			return;
		}

		$parts = explode( '\\', $class );
		array_shift( $parts ); // Don't need "TBC".
		$last    = array_pop( $parts ); // File should be 'class-[...].php'.
		$last    = 'class-' . $last . '.php';
		$parts[] = $last;
		$file    = dirname( __FILE__ ) . '/inc/' . str_replace( '_', '-', strtolower( implode( '/', $parts ) ) );
		if ( file_exists( $file ) ) {
			require $file;
		}

	}
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'tbc', 'TBC\CLI' );
}
