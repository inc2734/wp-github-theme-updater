<?php
class GitHub_Theme_Updater_Release_Test extends WP_UnitTestCase {

	/**
	 * @test
	 */
	public function request() {
		add_filter(
			'inc2734_github_theme_updater_request_url_inc2734/dummy-twentyseventeen',
			function( $url ) {
				return 'https://snow-monkey.2inc.org/github-api/response.json';
			}
		);

		$github_releases = new Inc2734\WP_GitHub_Theme_Updater\App\Model\GitHubReleases( 'twentyseventeen', 'inc2734', 'dummy-twentyseventeen' );
		$response = $github_releases->get();
		$this->assertTrue( 0 === strpos( $response->package, 'https://snow-monkey.2inc.org' ) );
	}

	/**
	 * @test
	 */
	public function get_http_status_code() {
		$github_releases = new Inc2734\WP_GitHub_Theme_Updater\App\Model\GitHubReleases( 'hello.php', 'inc2734', 'dummy-hello-dolly' );
		$response = $github_releases->get_http_status_code( 'https://snow-monkey.2inc.org/github-api/response.json' );
		$this->assertEquals( 200, $response );
	}

	/**
	 * @test
	 */
	public function get_zip_url() {
		$github_releases  = new Inc2734\WP_GitHub_Theme_Updater\App\Model\GitHubReleases( 'twentyseventeen', 'inc2734', 'dummy-twentyseventeen' );

		add_filter(
			'inc2734_github_theme_updater_zip_url_inc2734/dummy-twentyseventeen',
			function( $url ) {
				return 'https://github.com/inc2734/dummy-twentyseventeen/archive/1000001.zip?v=1';
			}
		);

		$response = $github_releases->get();
		$this->assertEquals( 'https://github.com/inc2734/dummy-twentyseventeen/archive/1000001.zip?v=1', $response->package );
	}

	/**
	 * @test
	 */
	public function get_zip_url_falls_back_to_archive() {
		$github_releases = new class( 'twentyseventeen', 'inc2734', 'dummy-twentyseventeen' ) extends Inc2734\WP_GitHub_Theme_Updater\App\Model\GitHubReleases {
			public function get_http_status_code( $url ) {
				return false !== strpos( $url, '/releases/download/' ) ? 404 : 200;
			}

			public function get_zip_url( $response ) {
				return $this->_get_zip_url( $response );
			}
		};

		$url = $github_releases->get_zip_url(
			(object) array(
				'tag_name' => '1.2.3',
				'assets'   => array(),
			)
		);

		$this->assertSame( 'https://github.com/inc2734/dummy-twentyseventeen/archive/1.2.3.zip', $url );
	}
}
