<?php
class GitHub_Theme_Updater_Test extends WP_UnitTestCase {

	private $_upgrade_dir;

	public function __construct() {
		parent::__construct();

		$this->_upgrade_dir = untrailingslashit( WP_CONTENT_DIR ) . '/upgrade';
	}

	public function setup() {
		parent::setup();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			include_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php' );
			include_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php' );
			$wp_filesystem = new WP_Filesystem_Direct( [] );
		}

		if ( ! file_exists( $this->_upgrade_dir ) ) {
			mkdir( $this->_upgrade_dir );
		}
	}

	public function tearDown() {
		parent::tearDown();

		if ( file_exists( $this->_upgrade_dir ) ) {
			system( 'rm -rf ' . $this->_upgrade_dir );
		}
	}

	/**
	 * @test
	 */
	public function success_transmission() {
		$updater = new Inc2734\WP_GitHub_Theme_Updater\Bootstrap( 'twentyseventeen', 'inc2734', 'dummy-twentyseventeen' );
		$transient = apply_filters( 'pre_set_site_transient_update_themes', false );
		$expected  = new stdClass();
		$expected->response = [
			'twentyseventeen' => [
				'theme'        => 'twentyseventeen',
				'new_version'  => '1000000',
				'url'          => false,
				'package'      => 'https://github.com/inc2734/dummy-twentyseventeen/archive/1000000.zip',
				'requires'     => '5.5',
				'requires_php' => '5.6',
				'tested'       => '',
			],
		];
		$this->assertEquals( $expected, $transient );
	}

	/**
	 * @test
	 */
	public function fail_transmission() {
		$updater = new Inc2734\WP_GitHub_Theme_Updater\Bootstrap( 'twentyseventeen', 'inc2734', 'dummy-norepo' );
		$transient = apply_filters( 'pre_set_site_transient_update_themes', false );
		$this->assertFalse( $transient );
	}

	/**
	 * @test
	 */
	public function upgrader_pre_install() {
		$updater  = new Inc2734\WP_GitHub_Theme_Updater\Bootstrap( 'twentyseventeen', 'inc2734', 'dummy-twentyseventeen' );
		$upgrader = new Inc2734\WP_GitHub_Theme_Updater\App\Model\Upgrader( 'twentyseventeen' );

		$result = $upgrader->pre_install( true, [ 'theme' => 'twentysixteen' ] );
		$this->assertTrue( $result );

		$result = $upgrader->pre_install( true, [ 'theme' => 'twentyseventeen' ] );
		$this->assertTrue( $result );

		rename( WP_CONTENT_DIR . '/themes/twentyseventeen', WP_CONTENT_DIR . '/themes/twentyseventeen-org' );
		$result = $upgrader->pre_install( true, [ 'theme' => 'twentyseventeen' ] );
		$this->assertTrue( is_wp_error( $result ) );
		rename( WP_CONTENT_DIR . '/themes/twentyseventeen-org', WP_CONTENT_DIR . '/themes/twentyseventeen' );
	}

	/**
	 * @test
	 */
	public function upgrader_source_selection() {
		mkdir( $this->_upgrade_dir . '/twentyseventeen-xxx' );

		$updater  = new Inc2734\WP_GitHub_Theme_Updater\Bootstrap( 'twentyseventeen', 'inc2734', 'dummy-twentyseventeen' );
		$upgrader = new Inc2734\WP_GitHub_Theme_Updater\App\Model\Upgrader( 'twentyseventeen' );

		$newsource = $upgrader->source_selection(
			$this->_upgrade_dir . '/twentyseventeen-xxx',
			$this->_upgrade_dir . '/twentyseventeen-xxx',
			false,
			[ 'theme' => 'twentysixteen' ]
		);
		$this->assertEquals( $this->_upgrade_dir . '/twentyseventeen-xxx', $newsource );

		$newsource = $upgrader->source_selection(
			$this->_upgrade_dir . '/twentyseventeen-xxx',
			$this->_upgrade_dir . '/twentyseventeen-xxx',
			false,
			[ 'theme' => 'twentyseventeen' ]
		);
		$this->assertEquals( $this->_upgrade_dir . '/twentyseventeen/', $newsource );
	}

	/**
	 * @test
	 */
	public function upgrader_source_selection__subdir() {
		mkdir( $this->_upgrade_dir . '/foo' );
		mkdir( $this->_upgrade_dir . '/foo/resources-xxx' );

		$updater  = new Inc2734\WP_GitHub_Theme_Updater\Bootstrap( 'foo/resources', 'inc2734', 'dummy-twentyseventeen' );
		$upgrader = new Inc2734\WP_GitHub_Theme_Updater\App\Model\Upgrader( 'foo/resources' );

		$newsource = $upgrader->source_selection(
			$this->_upgrade_dir . '/foo/resources-xxx',
			$this->_upgrade_dir . '/foo/resources-xxx',
			false,
			[ 'theme' => 'twentysixteen' ]
		);
		$this->assertEquals( $this->_upgrade_dir . '/foo/resources-xxx', $newsource );

		$newsource = $upgrader->source_selection(
			$this->_upgrade_dir . '/foo/resources-xxx',
			$this->_upgrade_dir . '/foo/resources-xxx',
			false,
			[ 'theme' => 'foo/resources' ]
		);
		$this->assertEquals( $this->_upgrade_dir . '/foo/resources/', $newsource );
	}
}
