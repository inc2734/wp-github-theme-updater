<?php
class GitHub_Theme_Updater_Test extends WP_UnitTestCase {

	private $_upgrade_dir;
	private $_theme_root;

	public function __construct() {
		parent::__construct();

		$this->_upgrade_dir = untrailingslashit( sys_get_temp_dir() ) . '/upgrade';
		$this->_theme_root  = untrailingslashit( sys_get_temp_dir() ) . '/themes';
	}

	public function set_up() {
		parent::set_up();

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
		}

		if ( ! file_exists( $this->_theme_root . '/twentyseventeen' ) ) {
			mkdir( $this->_theme_root . '/twentyseventeen' );
		}

		$wp_filesystem->put_contents(
			$this->_theme_root . '/twentyseventeen/style.css',
			"/*\nTheme Name: Twenty Seventeen\nVersion: 1.0.0\nRequires at least: 5.9\nRequires PHP: 7.4\n*/",
			0644
		);

		add_filter( 'theme_root', [ $this, '_set_theme_root' ] );
		wp_clean_themes_cache();
	}

	public function tear_down() {
		parent::tear_down();

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
				'new_version'  => '1000001',
				'url'          => false,
				'package'      => 'https://github.com/inc2734/dummy-twentyseventeen/releases/download/1000001/dummy-twentyseventeen.zip',
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
	public function no_update_when_latest_version_is_installed() {
		$current = wp_get_theme( 'twentyseventeen' );
		$updater = $this->get_updater(
			(object) array(
				'tag_name' => $current->get( 'Version' ),
				'package'  => false,
			)
		);

		$transient = $updater->_pre_set_site_transient_update_themes( false );

		$this->assertObjectHasProperty( 'no_update', $transient );
		$this->assertArrayHasKey( 'twentyseventeen', $transient->no_update );
	}

	/**
	 * @test
	 */
	public function no_update_when_installed_version_is_newer() {
		$updater = $this->get_updater(
			(object) array(
				'tag_name' => '0.0.1',
				'package'  => false,
			)
		);

		$transient = $updater->_pre_set_site_transient_update_themes( false );

		$this->assertObjectHasProperty( 'no_update', $transient );
		$this->assertArrayHasKey( 'twentyseventeen', $transient->no_update );
	}

	/**
	 * @test
	 */
	public function no_response_when_update_package_is_unavailable() {
		$updater = $this->get_updater(
			(object) array(
				'tag_name' => '1000000',
				'package'  => false,
			)
		);

		$transient = $updater->_pre_set_site_transient_update_themes( false );

		$this->assertFalse( $transient );
	}

	/**
	 * @test
	 */
	public function no_update_when_filtered_version_is_not_newer() {
		$filter_name = 'inc2734_github_theme_updater_transient_response_inc2734/dummy-twentyseventeen';
		$filter      = function( $update ) {
			$update['new_version'] = '0.0.1';
			return $update;
		};

		add_filter( $filter_name, $filter );

		$updater = $this->get_updater(
			(object) array(
				'tag_name' => '1000000',
				'package'  => 'https://example.com/theme.zip',
			)
		);

		$transient = $updater->_pre_set_site_transient_update_themes( false );

		remove_filter( $filter_name, $filter );

		$this->assertObjectHasProperty( 'no_update', $transient );
		$this->assertArrayHasKey( 'twentyseventeen', $transient->no_update );
		$this->assertSame( '0.0.1', $transient->no_update['twentyseventeen']['new_version'] );
	}

	/**
	 * @test
	 */
	public function no_response_when_filtered_version_is_newer_but_package_is_unavailable() {
		$current     = wp_get_theme( 'twentyseventeen' );
		$filter_name = 'inc2734_github_theme_updater_transient_response_inc2734/dummy-twentyseventeen';
		$filter      = function( $update ) {
			$update['new_version'] = '1000000';
			return $update;
		};

		add_filter( $filter_name, $filter );

		$updater = $this->get_updater(
			(object) array(
				'tag_name' => $current->get( 'Version' ),
				'package'  => false,
			)
		);

		$transient = $updater->_pre_set_site_transient_update_themes( false );

		remove_filter( $filter_name, $filter );

		$this->assertFalse( $transient );
	}

	/**
	 * @test
	 */
	public function custom_update_fields_are_applied() {
		$updater = $this->get_updater(
			(object) array(
				'tag_name' => '1000000',
				'package'  => 'https://example.com/theme.zip',
			),
			array(
				'tested'       => '99.0',
				'requires'     => '1.0',
				'requires_php' => '5.0',
			)
		);

		$transient = $updater->_pre_set_site_transient_update_themes( false );
		$update    = $transient->response['twentyseventeen'];

		$this->assertSame( '99.0', $update['tested'] );
		$this->assertSame( '1.0', $update['requires'] );
		$this->assertSame( '5.0', $update['requires_php'] );
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

	/**
	 * @test
	 */
	public function upgrader_process_complete() {
		$updater        = new Inc2734\WP_GitHub_Theme_Updater\Bootstrap( 'twentyseventeen', 'inc2734', 'dummy-twentyseventeen' );
		$hook_extra_list = array(
			array( 'theme' => 'twentyseventeen' ),
			array( 'themes' => array( 'twentyseventeen' ) ),
		);

		foreach ( $hook_extra_list as $hook_extra ) {
			set_transient( 'wp_github_theme_updater_twentyseventeen', 'release' );
			set_transient( 'wp_github_theme_updater_repository_data_twentyseventeen', 'repository' );

			$updater->_upgrader_process_complete(
				null,
				array_merge(
					array(
						'action' => 'update',
						'type'   => 'theme',
					),
					$hook_extra
				)
			);

			$this->assertFalse( get_transient( 'wp_github_theme_updater_twentyseventeen' ) );
			$this->assertFalse( get_transient( 'wp_github_theme_updater_repository_data_twentyseventeen' ) );
		}
	}

	/**
	 * @test
	 */
	public function upgrader_process_complete_ignores_other_themes() {
		$updater = new Inc2734\WP_GitHub_Theme_Updater\Bootstrap( 'twentyseventeen', 'inc2734', 'dummy-twentyseventeen' );

		set_transient( 'wp_github_theme_updater_twentyseventeen', 'release' );
		set_transient( 'wp_github_theme_updater_repository_data_twentyseventeen', 'repository' );

		$updater->_upgrader_process_complete(
			null,
			array(
				'action' => 'update',
				'type'   => 'theme',
				'theme'  => 'twentysixteen',
			)
		);

		$this->assertSame( 'release', get_transient( 'wp_github_theme_updater_twentyseventeen' ) );
		$this->assertSame( 'repository', get_transient( 'wp_github_theme_updater_repository_data_twentyseventeen' ) );

		delete_transient( 'wp_github_theme_updater_twentyseventeen' );
		delete_transient( 'wp_github_theme_updater_repository_data_twentyseventeen' );
	}

	/**
	 * Return an updater with stubbed GitHub responses.
	 *
	 * @param object $response GitHub release response.
	 * @param array  $fields Theme data fields.
	 * @return Inc2734\WP_GitHub_Theme_Updater\Bootstrap
	 */
	private function get_updater( $response, array $fields = array() ) {
		$updater = new Inc2734\WP_GitHub_Theme_Updater\Bootstrap( 'twentyseventeen', 'inc2734', 'dummy-twentyseventeen', $fields );

		$github_releases = new class( $response ) {
			private $response;

			public function __construct( $response ) {
				$this->response = $response;
			}

			public function get() {
				return $this->response;
			}
		};

		$github_repository_content = new class() {
			public function get_headers( $version = null ) {
				return array(
					'Tested up to' => '',
					'RequiresPHP'  => '',
					'RequiresWP'   => '',
				);
			}
		};

		$this->set_protected_property( $updater, 'github_releases', $github_releases );
		$this->set_protected_property( $updater, 'github_repository_content', $github_repository_content );

		return $updater;
	}

	/**
	 * Set a protected property.
	 *
	 * @param object $object Object to update.
	 * @param string $name Property name.
	 * @param mixed  $value Property value.
	 */
	private function set_protected_property( $object, $name, $value ) {
		$property = new ReflectionProperty( $object, $name );
		$property->setAccessible( true );
		$property->setValue( $object, $value );
	}
}
