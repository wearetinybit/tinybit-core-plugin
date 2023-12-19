<?php
/**
 * Manages media library functionality.
 *
 * @package TBC
 */

namespace TBC;

/**
 * Manages media library functionality.
 */
class Media {

	/**
	 * Modifies the quality of original images during the upload process.
	 *
	 * @param array   $metadata      Existing attachment metadata.
	 * @param integer $attachment_id ID for the attachment.
	 * @return array
	 */
	public static function filter_wp_generate_attachment_metadata( $metadata, $attachment_id ) {
		self::compress_attachment_original_image( $attachment_id );
		return $metadata;
	}

	/**
	 * Rescales the full-size image for a given attachment ID.
	 *
	 * @param integer $attachment_id ID for the attachment.
	 * @param integer $quality       Compression quality to use.
	 */
	public static function compress_attachment_original_image( $attachment_id, $quality = 70 ) {
		$file = get_attached_file( $attachment_id );
		$type = get_post_mime_type( $attachment_id );

		if ( ! in_array( $type, [ 'image/jpg', 'image/jpeg' ], true ) ) {
			return;
		}

		$gd_filter = function () {
			return array( 'WP_Image_Editor_GD' );
		};

		add_filter(
			'wp_image_editors',
			$gd_filter
		);

		$editor = wp_get_image_editor( $file );
		if ( ! is_wp_error( $editor ) ) {
			$result = $editor->set_quality( $quality );
			if ( ! is_wp_error( $result ) ) {
				$editor->save( $file );
			}
		}

		remove_filter(
			'wp_image_editors',
			$gd_filter
		);
	}
}
