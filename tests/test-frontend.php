<?php
/**
 * Tests frontend pieces to ensure they work as expected.
 *
 * @package TBC
 */

namespace TBC\Tests;

/**
 * Tests frontend pieces to ensure they work as expected.
 */
class Test_Frontend extends \WP_UnitTestCase {

	/**
	 * Ensure early content filters work as expected.
	 */
	public function test_filter_the_content_early() {
		$original = <<<EOT
Aenean eu leo quam. Pellentesque ornare sem lacinia quam venenatis vestibulum. Integer posuere erat a ante venenatis dapibus posuere velit aliquet. Etiam porta sem malesuada magna mollis euismod.

<img src="https://example.com" height="240" width="300">

Aenean eu leo quam. Pellentesque ornare sem lacinia quam venenatis vestibulum. Integer posuere erat a ante venenatis dapibus posuere velit aliquet. Etiam porta sem malesuada magna mollis euismod.

Aenean eu leo quam. Pellentesque ornare sem lacinia quam venenatis vestibulum. Integer posuere erat a ante venenatis dapibus posuere velit aliquet. Etiam porta sem malesuada magna mollis euismod.
EOT;
		$expected = <<<EOT
<p>Aenean eu leo quam. Pellentesque ornare sem lacinia quam venenatis vestibulum. Integer posuere erat a ante venenatis dapibus posuere velit aliquet. Etiam porta sem malesuada magna mollis euismod.</p>
<p><img fetchpriority="high" decoding="async" loading="eager" src="https://example.com" height="240" width="300"></p>
<p>Aenean eu leo quam. Pellentesque ornare sem lacinia quam venenatis vestibulum. Integer posuere erat a ante venenatis dapibus posuere velit aliquet. Etiam porta sem malesuada magna mollis euismod.</p>
<p>Aenean eu leo quam. Pellentesque ornare sem lacinia quam venenatis vestibulum. Integer posuere erat a ante venenatis dapibus posuere velit aliquet. Etiam porta sem malesuada magna mollis euismod.</p>
EOT;
		$post_id  = $this->factory->post->create(
			[
				'post_content' => $original,
			]
		);
		$this->go_to( get_permalink( $post_id ) );
		$this->assertEquals(
			trim( $expected ),
			trim( apply_filters( 'the_content', $original ) )
		);
	}
}
