<?php
use Inc2734\WP_GitHub_Theme_Updater\Bootstrap;

class GitHub_Theme_Updater_Bootstrap_Test extends WP_UnitTestCase {

	/**
	 * @test
	 */
	public function should_update() {
		$this->assertTrue(
			Bootstrap::should_update(
				array(
					'wp_version'  => 6.8,
					'php_version' => 8.2,
				),
				array(
					'version' => 1.0,
				),
				array(
					'version'      => 2.0,
					'requires_wp'  => '',
					'requires_php' => '',
				),
			)
		);

		$this->assertFalse(
			Bootstrap::should_update(
				array(
					'wp_version'  => 6.8,
					'php_version' => 8.2,
				),
				array(
					'version' => 1.0,
				),
				array(
					'version'      => 0.9,
					'requires_wp'  => '',
					'requires_php' => '',
				),
			)
		);

		$this->assertFalse(
			Bootstrap::should_update(
				array(
					'wp_version'  => 6.8,
					'php_version' => 8.2,
				),
				array(
					'version' => 1.0,
				),
				array(
					'version'      => 2.0,
					'requires_wp'  => 6.9,
					'requires_php' => '',
				),
			)
		);

		$this->assertFalse(
			Bootstrap::should_update(
				array(
					'wp_version'  => 6.8,
					'php_version' => 8.2,
				),
				array(
					'version' => 1.0,
				),
				array(
					'version'      => 2.0,
					'requires_wp'  => '',
					'requires_php' => 8.3,
				),
			)
		);

		$this->assertTrue(
			Bootstrap::should_update(
				array(
					'wp_version'  => 6.8,
					'php_version' => 8.2,
				),
				array(
					'version' => 1.0,
				),
				array(
					'version'      => 2.0,
					'requires_wp'  => 6.8,
					'requires_php' => 8.2,
				),
			)
		);

		$this->assertTrue(
			Bootstrap::should_update(
				array(
					'wp_version'  => 6.8,
					'php_version' => 8.2,
				),
				array(
					'version' => 1.0,
				),
				array(
					'version'      => 2.0,
					'requires_wp'  => 6.8,
					'requires_php' => '',
				),
			)
		);

		$this->assertTrue(
			Bootstrap::should_update(
				array(
					'wp_version'  => 6.8,
					'php_version' => 8.2,
				),
				array(
					'version' => 1.0,
				),
				array(
					'version'      => 2.0,
					'requires_wp'  => '',
					'requires_php' => 8.2,
				),
			)
		);

		$this->assertFalse(
			Bootstrap::should_update(
				array(
					'wp_version'  => 6.8,
					'php_version' => 8.2,
				),
				array(
					'version' => 1.0,
				),
				array(
					'version'      => 2.0,
					'requires_wp'  => 6.9,
					'requires_php' => 8.2,
				),
			)
		);
	}
}
