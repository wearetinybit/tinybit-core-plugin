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
	public function audit_head_meta( $_, $assoc_args ) {
		$results = [];

		$get_flag_value = function( $assoc_args, $flag, $default = null ) {
			return isset( $assoc_args[ $flag ] ) ? $assoc_args[ $flag ] : $default;
		};

		$q = [
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'no_found_rows'  => true,
		];
		foreach ( ( new WP_Query( $q ) )->posts as $i => $post ) {

			$url_parts = wp_parse_url( get_permalink( $post->ID ) );

			if ( isset( $url_parts['host'] ) ) {
				if ( isset( $url_parts['scheme'] ) && 'https' === strtolower( $url_parts['scheme'] ) ) {
					$_SERVER['HTTPS'] = 'on';
				}

				$_SERVER['HTTP_HOST'] = $url_parts['host'];
				if ( isset( $url_parts['port'] ) ) {
					$_SERVER['HTTP_HOST'] .= ':' . $url_parts['port'];
				}

				$_SERVER['SERVER_NAME'] = $url_parts['host'];
			}

			$f = function( $key ) use ( $url_parts, $get_flag_value ) {
				return $get_flag_value( $url_parts, $key, '' );
			};

			$_SERVER['REQUEST_URI']  = $f( 'path' ) . ( isset( $url_parts['query'] ) ? '?' . $url_parts['query'] : '' );
			$_SERVER['SERVER_PORT']  = $get_flag_value( $url_parts, 'port', '80' );
			$_SERVER['QUERY_STRING'] = $f( 'query' );

			$output = self::load_wordpress_with_template();

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
				'id'           => $post->ID,
				'title'        => $post->post_title,
				'og_image'     => $og_image,
				'schema_image' => $schema_image,
			];

			if ( $i && 0 === $i % 100 ) {
				\WP_CLI\Utils\wp_clear_object_cache();
			}
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

	/**
	 * Runs through the entirety of the WP bootstrap process
	 */
	private static function load_wordpress_with_template() {

		// Clear Yoast SEO meta.
		if ( function_exists( 'YoastSEO' ) ) {
			$memoizer = YoastSEO()->classes->get( \Yoast\WP\SEO\Memoizers\Meta_Tags_Context_Memoizer::class );
			$memoizer->clear( 'current_page' );
		}

		// Set up main_query main WordPress query.
		wp();

		if ( ! defined( 'WP_USE_THEMES' ) ) {
			define( 'WP_USE_THEMES', true );
		}

		return self::get_rendered_template();
	}

	/**
	 * Returns the rendered template.
	 *
	 * @return string
	 */
	protected static function get_rendered_template() {
		ob_start();
		self::load_template();
		return ob_get_clean();
	}

	/**
	 * Copy-pasta of wp-includes/template-loader.php
	 */
	protected static function load_template() {
		// Template is normally loaded in global scope, so we need to replicate.
		foreach ( $GLOBALS as $key => $value ) {
			global ${$key}; // phpcs:ignore
			// PHPCompatibility.PHP.ForbiddenGlobalVariableVariable.NonBareVariableFound -- Syntax is updated to compatible with php 5 and 7.
		}

		do_action( 'template_redirect' );

		$template = false;
		// phpcs:disable Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
		// phpcs:disable WordPress.CodeAnalysis.AssignmentInCondition.Found
		if ( is_404() && $template = get_404_template() ) :
		elseif ( is_search() && $template = get_search_template() ) :
		elseif ( is_front_page() && $template = get_front_page_template() ) :
		elseif ( is_home() && $template = get_home_template() ) :
		elseif ( is_post_type_archive() && $template = get_post_type_archive_template() ) :
		elseif ( is_tax() && $template = get_taxonomy_template() ) :
		elseif ( is_attachment() && $template = get_attachment_template() ) :
			remove_filter( 'the_content', 'prepend_attachment' );
		elseif ( is_single() && $template = get_single_template() ) :
		elseif ( is_page() && $template = get_page_template() ) :
		elseif ( is_category() && $template = get_category_template() ) :
		elseif ( is_tag() && $template = get_tag_template() ) :
		elseif ( is_author() && $template = get_author_template() ) :
		elseif ( is_date() && $template = get_date_template() ) :
		elseif ( is_archive() && $template = get_archive_template() ) :
		elseif ( is_comments_popup() && $template = get_comments_popup_template() ) :
		elseif ( is_paged() && $template = get_paged_template() ) :
		else :
			$template = get_index_template();
		endif;
		/**
		 * Filter the path of the current template before including it.
		 *
		 * @since 3.0.0
		 *
		 * @param string $template The path of the template to include.
		 */

		if ( $template = apply_filters( 'template_include', $template ) ) {
			$template_contents = file_get_contents( $template );
			$included_header   = false;
			$included_footer   = false;
			if ( false !== stripos( $template_contents, 'get_header();' ) ) {
				if ( ! isset( self::$rendered_header ) ) {
					// get_header() will render the first time but not subsequent.
					self::$rendered_header = true;
				} else {
					do_action( 'get_header', null );
					locate_template( 'header.php', true, false );
				}
				$included_header = true;
			}
			include( $template );
			if ( false !== stripos( $template_contents, 'get_footer();' ) ) {
				if ( ! isset( self::$rendered_footer ) ) {
					// get_footer() will render the first time but not subsequent.
					self::$rendered_footer = true;
				} else {
					do_action( 'get_footer', null );
					locate_template( 'footer.php', true, false );
				}
				$included_footer = true;
			}
			if ( $included_header && $included_footer ) {
				global $wp_scripts, $wp_styles;
				$wp_scripts->done = [];
				$wp_styles->done  = [];
			}
		}
		// phpcs:enable Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
		// phpcs:enable WordPress.CodeAnalysis.AssignmentInCondition.Found

		return;
	}

}
