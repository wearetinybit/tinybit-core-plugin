<?php
/**
 * Plugin Name:     TinyBit Core
 * Plugin URI:      PLUGIN SITE HERE
 * Description:     PLUGIN DESCRIPTION HERE
 * Author:          YOUR NAME HERE
 * Author URI:      YOUR SITE HERE
 * Text Domain:     tinybit-core
 * Domain Path:     /languages
 * Version:         0.2.2
 *
 * @package         TBC
 */

require __DIR__ . '/000-loader.php';

/*
 * TBC\Frontend
 */
tbc_register_class_hooks(
	'TBC\Frontend',
	[
		[ 'the_content', 'filter_the_content_early', 1 ],
	]
);

/*
 * TBC\Media
 */
tbc_register_class_hooks(
	'TBC\Media',
	[
		[ 'wp_generate_attachment_metadata', 10, 2 ],
	]
);

/*
 * TBC\Integrations\Cloudflare
 */
tbc_register_class_hooks(
	'TBC\Integrations\Cloudflare',
	[
		[ 'cloudflare_purge_by_url' ],
	]
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'tbc', 'TBC\CLI' );
}
