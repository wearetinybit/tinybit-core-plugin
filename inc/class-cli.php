<?php
/**
 * TinyBit Core Utilities
 *
 * @package TBC
 */

namespace TBC;

use WP_CLI;

/**
 * TinyBit Core Utilities.
 */
class CLI {

	/**
	 * Generates width-constrained images at the same proportion as the original image.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Original image file.
	 *
	 * --widths=<widths>
	 * : Comma-separated image widths to generate.
	 *
	 * @subcommand generate-image-widths
	 */
	public function generate_image_widths( $args, $assoc_args ) {

		list( $file ) = $args;
		if ( ! file_exists( $file ) ) {
			WP_CLI::error( 'File does not exist.' );
		}

		$widths = array_unique( array_map( 'intval', explode( ',', $assoc_args['widths'] ) ) );
		sort( $widths );
		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			WP_CLI::error( $editor );
		}
		$size = $editor->get_size();
		foreach ( $widths as $width ) {
			if ( $width >= $size['width'] ) {
				WP_CLI::warning( sprintf( 'Skipping: provided width \'%d\' exceeds original image width \'%d\'.', $width, $size['width'] ) );
				continue;
			}
			$editor = wp_get_image_editor( $file );
			if ( is_wp_error( $editor ) ) {
				WP_CLI::error( $editor );
			}
			$editor->resize( $width, null, false );
			$dir      = dirname( $file );
			$ext      = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
			$new_file = wp_basename( $file, ".$ext" );
			$new_size = $editor->get_size();
			$new_file = preg_replace(
				'#(-[\d]+x[\d]+)?$#',
				'',
				$new_file
			) . '-' . (int) $new_size['width'] . 'x' . (int) $new_size['height'];
			$editor->save( trailingslashit( $dir ) . $new_file, mime_content_type( $file ) );
			WP_CLI::log( sprintf( 'Generated %s', wp_basename( $new_file ) ) );
		}

		WP_CLI::success( 'Image widths created.' );
	}

}
