<?php
/**
 * TinyBit Core Utilities
 *
 * @package TBC
 */

namespace TBC;

use WP_CLI;
use WP_Error;
use WP_Query;

/**
 * TinyBit Core Utilities.
 */
class CLI {

	/**
	 * Whether or not the header has been rendered.
	 *
	 * @var boolean
	 */
	private static $rendered_header;

	/**
	 * Whether or not the footer has been rendered.
	 *
	 * @var boolean
	 */
	private static $rendered_footer;

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
				clearstatcache( false, $full_file );
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
			if ( ! file_exists( $file ) ) {
				WP_CLI::warning( sprintf( 'Skipping missing file: %s', $file ) );
				continue;
			}
			$orig_kb = round( ( filesize( $file ) / 1000 ), 2 );
			$ret     = self::compress_image_inline_with_tinypng( $file );
			if ( is_wp_error( $ret ) ) {
				WP_CLI::warning( $ret->get_error_message() );
				continue;
			}
			clearstatcache( false, $file );
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
	 * Audit posts for their og:image and schema primary image.
	 *
	 * ## OPTIONS
	 *
	 * <sitemap>
	 * : Sitemap to pull URLs from.
	 *
	 * [--format=<format>]
	 * : Output format for the results.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - html
	 * ---
	 *
	 * @subcommand audit-head-meta
	 */
	public function audit_head_meta( $args, $assoc_args ) {
		$results = [];

		list( $sitemap ) = $args;

		$response = \WP_CLI\Utils\http_request( 'GET', $sitemap );
		if ( 200 !== $response->status_code ) {
			WP_CLI::error( 'Unable to fetch sitemap' );
		}

		preg_match_all( '#<loc>(.+)</loc>#Us', $response->body, $matches );
		$urls = ! empty( $matches[1] ) ? $matches[1] : [];

		foreach ( $urls as $url ) {
			$response = \WP_CLI\Utils\http_request( 'GET', $url );
			$output   = $response->body;

			$og_image = '';
			if ( preg_match( '#<meta property="og:image" content="([^"]+)"#', $output, $matches ) ) {
				$og_image = $matches[1];
			}
			$schema_image = '';
			if ( preg_match(
				'#<script type="application/ld\+json" class="yoast-schema-graph">(.+)</script>#',
				$output,
				$matches
			) ) {
				$schema = json_decode( $matches[1], true );
				if ( ! empty( $schema['@graph'] ) ) {
					foreach ( $schema['@graph'] as $piece ) {
						if ( 'ImageObject' === $piece['@type'] ) {
							$schema_image = $piece['url'];
							break;
						}
					}
				}
			}

			$results[] = [
				'url'          => $url,
				'og_image'     => $og_image,
				'schema_image' => $schema_image,
			];
		}

		$headers = [];
		if ( ! empty( $results ) ) {
			$headers = array_keys( $results[0] );
		}

		if ( 'html' === $assoc_args['format'] ) {
			$output = '<table><thead><tr>';
			foreach ( $headers as $header ) {
				$output .= '<th>' . $header . '</th>';
			}
			$output .= '</tr></thead>';
			$output .= '<tbody>';
			foreach ( $results as $result ) {
				$output .= '<tr>';
				foreach ( $result as $key => $value ) {
					if ( false !== stripos( $key, '_image' ) && ! empty( $value ) ) {
						$output .= '<td><img loading="lazy" width="300" src="' . $value . '"></td>';
					} elseif ( false !== stripos( $key, 'url' ) && ! empty( $value ) ) {
						$output .= '<td><a target="_blank" href="' . $value . '">' . $value . ' </a></td>';
					} else {
						$output .= '<td>' . $value . '</td>';
					}
				}
				$output .= '</tr>';
			}
			$output .= '</tbody></table>';
			echo $output;
		} else {
			WP_CLI\Utils\format_items( $assoc_args['format'], $results, $headers );
		}
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
