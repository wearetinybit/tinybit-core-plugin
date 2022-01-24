<?php
/**
 * Cloudflare-related code.
 *
 * @package TBC
 */

namespace TBC\Integrations;

/**
 * Cloudflare-related code.
 */
class Cloudflare {

	/**
	 * Filters the set of URLs to purge when something is updated.
	 *
	 * @param array $urls Existing URLs to purge.
	 * @return array
	 */
	public static function filter_cloudflare_purge_by_url( $urls ) {
		return $urls;
	}

}
