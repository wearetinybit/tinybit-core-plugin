<?php
/**
 * Various utility functions.
 *
 * @package TBC
 */

namespace TBC;

/**
 * Various utility functions.
 */
class Utils {

	/**
	 * Forces a specific value for a specific HTML element attribute.
	 *
	 * @param string $content   Content to process.
	 * @param string $element   Element to inspect.
	 * @param string $attribute Attribute to enforce.
	 * @param string $value     Attribute value to enforce.
	 * @return content
	 */
	public static function force_element_attribute( $content, $element, $attribute, $value ) {
		$content = preg_replace_callback(
			'#<' . $element . '([^>]*)>#',
			function ( $match ) use ( $element, $attribute, $value ) {
				$full = $match[0];
				$full = preg_replace(
					'#(<' . $element . '[^>]+' . $attribute . '=["\'])([^"\']+)#',
					'$1' . $value,
					$full,
					-1,
					$count
				);
				if ( ! $count ) {
					$full = str_replace(
						'<' . $element . ' ',
						'<' . $element . ' ' . $attribute . '="' . $value . '" ',
						$full
					);
				}
				return $full;
			},
			$content
		);
		return $content;
	}
}
