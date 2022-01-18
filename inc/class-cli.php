<?php
/**
 * TinyBit Core Utilities
 *
 * @package TBC
 */

namespace TBC;

use WP_CLI;
use WP_Error;

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
	 * [--quality=<quality>]
	 * : Compression quality.
	 * ---
	 * default: 70
	 * ---
	 *
	 * [--compress]
	 * : Run the image through additional compression.
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
			$editor->set_quality( $assoc_args['quality'] );
			$dir       = dirname( $file );
			$ext       = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
			$new_file  = wp_basename( $file, ".$ext" );
			$new_size  = $editor->get_size();
			$new_file  = preg_replace(
				'#(-[\d]+x[\d]+)?$#',
				'',
				$new_file
			);
			$new_file  = preg_replace(
				'#(\@[\d]x?)?$#',
				'',
				$new_file
			);
			$new_file .= '-' . (int) $new_size['width'] . 'x' . (int) $new_size['height'];
			$editor->save( trailingslashit( $dir ) . $new_file, mime_content_type( $file ) );
			$full_file = trailingslashit( $dir ) . $new_file . '.' . $ext;
			if ( ! empty( $assoc_args['compress'] ) ) {
				$orig_kb = round( ( filesize( $full_file ) / 1000 ), 2 );
				$ret     = self::compress_image_inline_with_tinypng( $full_file );
				if ( is_wp_error( $ret ) ) {
					WP_CLI::error( $ret );
				}
				$new_kb = round( ( filesize( $full_file ) / 1000 ), 2 );
				WP_CLI::log( sprintf( 'Generated %s (%dkb->%dkb)', $new_file . '.' . $ext, $orig_kb, $new_kb ) );
			} else {
				$new_kb = round( ( filesize( $full_file ) / 1000 ), 2 );
				WP_CLI::log( sprintf( 'Generated %s (%dkb)', $new_file . '.' . $ext, $new_kb ) );
			}
		}

		WP_CLI::success( 'Image widths created.' );
	}

	/**
	 * Compresses images inline using TinyPNG.
	 *
	 * ## OPTIONS
	 *
	 * <files>...
	 * : Original image files.
	 *
	 * @subcommand compress-images
	 */
	public function compress_images( $args ) {
		foreach ( $args as $file ) {
			$orig_kb = round( ( filesize( $file ) / 1000 ), 2 );
			$ret     = self::compress_image_inline_with_tinypng( $file );
			if ( is_wp_error( $ret ) ) {
				WP_CLI::error( $ret );
			}
			$new_kb = round( ( filesize( $file ) / 1000 ), 2 );
			WP_CLI::log(
				sprintf(
					'Compressed %s from %dkb to %dkb',
					wp_basename( $file ),
					$orig_kb,
					$new_kb
				)
			);
		}
		WP_CLI::success( 'Images compressed.' );
	}

	/**
	 * Compresses an image in-place using TinyPNG.
	 *
	 * @param string $file Image file path.
	 * @return true|WP_Error
	 */
	private static function compress_image_inline_with_tinypng( $file ) {

		if ( ! defined( 'TBC_TINYPNG_API_KEY' ) ) {
			return new WP_Error( 'missing-api-key', 'Constant TBC_TINYPNG_API_KEY is not defined.' );
		}

		$response = wp_remote_post(
			'https://api.tinify.com/shrink',
			[
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode( 'api:' . TBC_TINYPNG_API_KEY ),
					'Content-Type'  => 'multipart/form-data',
				],
				'body'    => file_get_contents( $file ),
			]
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		if ( 200 === $status || 201 === $status ) {
			$body           = json_decode( $body, true );
			$image_response = wp_remote_get( $body['output']['url'] );
			if ( is_wp_error( $image_response ) ) {
				return $image_response;
			}
			$image_status = wp_remote_retrieve_response_code( $response );
			if ( 200 === $image_status || 201 === $image_status ) {
				file_put_contents( $file, wp_remote_retrieve_body( $image_response ) );
			} else {
				return new WP_Error(
					'download-http-error',
					sprintf( 'Unexpected error downloading compressed image (HTTP %d)', $image_status )
				);
			}
		} else {
			return new WP_Error(
				'compress-http-error',
				sprintf( 'Unexpected error compressing image (HTTP %d)', $status )
			);
		}

		return true;
	}

}
