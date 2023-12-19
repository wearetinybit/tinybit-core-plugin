<?php
/**
 * Tests default functions to ensure they work as expected.
 *
 * @package TBC
 */

namespace TBC\Tests;

/**
 * Tests default functions to ensure they work as expected.
 */
class Test_Functions extends \WP_UnitTestCase {

	/**
	 * Ensure the tbc_parse_register_class_hooks() parses hook registration.
	 */
	public function test_tbc_parse_register_class_hooks() {
		$class          = 'TBC\Integrations\Cloudflare';
		$expected_hooks = [
			[ 'filter', 'cloudflare_purge_by_url', $class, 'filter_cloudflare_purge_by_url', 10, 1 ],
		];
		$this->assertEquals(
			$expected_hooks,
			tbc_parse_register_class_hooks(
				$class,
				[
					[ 'cloudflare_purge_by_url' ],
				]
			)
		);
	}
}
