<?php
/**
 * Manages frontend functionality.
 *
 * @package TBC
 */

namespace TBC;

/**
 * Manages frontend functionality.
 */
class Frontend {

	/**
	 * Filters the content early.
	 *
	 * @param string $content Existing post content.
	 * @return string
	 */
	public static function filter_the_content_early( $content ) {

		if ( is_single() ) {
			$bits = explode( PHP_EOL . PHP_EOL, $content );
			foreach ( $bits as $i => &$bit ) {
				if ( $i > 4 ) {
					break;
				}
				$bit = Utils::force_element_attribute( $content, 'img', 'loading', 'eager' );
			}
			$content = implode( PHP_EOL . PHP_EOL, $bits );
		}

		return $content;
	}

}
