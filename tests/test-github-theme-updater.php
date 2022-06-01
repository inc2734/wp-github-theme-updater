<?php
class GitHub_Theme_Updater_Test extends WP_UnitTestCase {

	private $_upgrade_dir;

	public function __construct() {
		parent::__construct();

		$this->_upgrade_dir = untrailingslashit( sys_get_temp_dir() ) . '/upgrade';
		$this->_theme_root  = untrailingslashit( sys_get_temp_dir() ) . '/themes';
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

		if ( ! file_exists( $this->_theme_root ) ) {
			mkdir( $this->_theme_root );
			mkdir( $this->_theme_root . '/twentyseventeen' );
		}
		add_filter( 'theme_root', [ $this, '_set_theme_root' ] );
	}

	public function tearDown() {
		parent::tearDown();

		if ( file_exists( $this->_upgrade_dir ) ) {
			system( 'rm -rf ' . $this->_upgrade_dir );
		}

		if ( file_exists( $this->_theme_root ) ) {
			system( 'rm -rf ' . $this->_theme_root );
		}
		remove_filter( 'theme_root', [ $this, '_set_theme_root' ] );
	}

	public function _set_theme_root() {
		return $this->_theme_root;
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
				'package'      => 'https://github.com/inc2734/dummy-twentyseventeen/releases/download/1000000/dummy-twentyseventeen-1000000.zip',
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

		rename( get_theme_root() . '/twentyseventeen', get_theme_root() . '/twentyseventeen-org' );
		$result = $upgrader->pre_install( true, [ 'theme' => 'twentyseventeen' ] );
		$this->assertTrue( is_wp_error( $result ) );
		rename( get_theme_root() . '/twentyseventeen-org', get_theme_root() . '/twentyseventeen' );
	}

	/**
	 * @test
	 */
	public function upgrader_source_selection() {
		mkdir( $this->_upgrade_dir . '/twentyseventeen-xxx' );
		mkdir( $this->_upgrade_dir . '/twentyseventeen-xxx/twentyseventeen2' );

		$updater  = new Inc2734\WP_GitHub_Theme_Updater\Bootstrap( 'twentyseventeen', 'inc2734', 'dummy-twentyseventeen' );
		$upgrader = new Inc2734\WP_GitHub_Theme_Updater\App\Model\Upgrader( 'twentyseventeen' );

		$newsource = $upgrader->source_selection(
			$this->_upgrade_dir . '/twentyseventeen-xxx/twentyseventeen2',
			$this->_upgrade_dir . '/twentyseventeen-xxx',
			false,
			[ 'theme' => 'twentyseventeen' ]
		);
		$this->assertEquals( $this->_upgrade_dir . '/twentyseventeen-xxx/twentyseventeen', $newsource );

		$newsource = $upgrader->source_selection(
			$this->_upgrade_dir . '/twentyseventeen-xxx/twentyseventeen',
			$this->_upgrade_dir . '/twentyseventeen-xxx',
			false,
			[ 'theme' => 'twentyseventeen' ]
		);
		$this->assertEquals( $this->_upgrade_dir . '/twentyseventeen-xxx/twentyseventeen', $newsource );
	}
}
