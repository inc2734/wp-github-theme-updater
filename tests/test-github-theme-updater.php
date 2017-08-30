<?php
class GitHub_Theme_Updater_Test extends WP_UnitTestCase {

	public function setup() {
		parent::setup();
	}

	public function test_success_transmission() {
		$updater = new Inc2734\WP_GitHub_Theme_Updater\GitHub_Theme_Updater( 'twentyseventeen', 'inc2734', 'dummy-twentyseventeen' );
		$transient = apply_filters( 'pre_set_site_transient_update_themes', false );
		$expected  = new stdClass();
		$expected->response = [
			'twentyseventeen' => [
				'theme'       => 'twentyseventeen',
				'new_version' => 1000000,
				'url'         => '',
				'package'     => 'https://github.com/inc2734/dummy-twentyseventeen/archive/1000000.zip',
			],
		];
		$this->assertEquals( $expected, $transient );
	}

	public function test_fail_transmission() {
		$updater = new Inc2734\WP_GitHub_Theme_Updater\GitHub_Theme_Updater( 'twentyseventeen', 'inc2734', 'dummy-norepo' );
		$transient = apply_filters( 'pre_set_site_transient_update_themes', false );
		$this->assertFalse( $transient );
	}

	public function test_upgrader_source_selection() {
		$updater = new Inc2734\WP_GitHub_Theme_Updater\GitHub_Theme_Updater( 'twentyseventeen', 'inc2734', 'dummy-twentyseventeen' );
		$newsource = $updater->_upgrader_source_selection( 'twentyseventeen-foo', 'twentyseventeen', false );
		$this->assertEquals( WP_CONTENT_DIR . '/themes/twentyseventeen', $newsource );

		$updater = new Inc2734\WP_GitHub_Theme_Updater\GitHub_Theme_Updater( 'foo/resources', 'inc2734', 'dummy-twentyseventeen' );
		$newsource = $updater->_upgrader_source_selection( 'foo/resources', 'foo-bar/resources', false );
		$this->assertEquals( WP_CONTENT_DIR . '/themes/foo', $newsource );

		$updater = new Inc2734\WP_GitHub_Theme_Updater\GitHub_Theme_Updater( 'foo-bar/resources', 'inc2734', 'dummy-twentyseventeen' );
		$newsource = $updater->_upgrader_source_selection( 'foo-bar/resources', 'foo/resources', false );
		$this->assertEquals( WP_CONTENT_DIR . '/themes/foo-bar', $newsource );
	}
}
