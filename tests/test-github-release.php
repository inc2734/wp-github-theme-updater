<?php
class GitHub_Plugin_Updater_Release_Test extends WP_UnitTestCase {

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
	public function get_zip_url() {
		$github_releases  = new Inc2734\WP_GitHub_Theme_Updater\App\Model\GitHubReleases( 'twentyseventeen', 'inc2734', 'dummy-twentyseventeen' );

		add_filter(
			'inc2734_github_theme_updater_zip_url_inc2734/dummy-twentyseventeen',
			function( $url ) {
				return 'https://snow-monkey.2inc.org/dummy-twentyseventeen.zip';
			}
		);

		$response = $github_releases->get();
		$this->assertEquals( 'https://snow-monkey.2inc.org/dummy-twentyseventeen.zip', $response->package );
	}
}
