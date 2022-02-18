<?php
/**
 * Tests the TBC\Utils class to verify everything works.
 *
 * @package TBC
 */

namespace TBC\Tests;

use TBC\Utils;

/**
 * Tests the TBC\Utils class to verify everything works.
 *
 * @coversDefaultClass TBC\Utils
 */
class Test_Utils extends \WP_UnitTestcase {

	/**
	 * Ensures force_element_attribute works as expected.
	 */
	public function test_force_element_attribute() {
		// Attribute doesn't yet exist.
		$this->assertEquals(
			'<p><img loading="eager" src="https://curbly.test/wp-content/uploads/2020/12/cash-tree-feature-3476-edit_web.jpg" alt="Cash forest - the perfect holiday gift!" width="1000" height="750" class="align size-full wp-image-13791"></p>',
			Utils::force_element_attribute(
				'<p><img src="https://curbly.test/wp-content/uploads/2020/12/cash-tree-feature-3476-edit_web.jpg" alt="Cash forest - the perfect holiday gift!" width="1000" height="750" class="align size-full wp-image-13791"></p>',
				'img',
				'loading',
				'eager'
			)
		);
		$this->assertEquals(
			'<p><img src="https://curbly.test/wp-content/uploads/2020/12/cash-tree-feature-3476-edit_web.jpg" loading="eager" alt="Cash forest - the perfect holiday gift!" width="1000" height="750" class="align size-full wp-image-13791"></p>',
			Utils::force_element_attribute(
				'<p><img src="https://curbly.test/wp-content/uploads/2020/12/cash-tree-feature-3476-edit_web.jpg" loading="lazy" alt="Cash forest - the perfect holiday gift!" width="1000" height="750" class="align size-full wp-image-13791"></p>',
				'img',
				'loading',
				'eager'
			)
		);
	}
}
