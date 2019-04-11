<?php
/**
 * @package wp-github-theme-updater
 * @author inc2734
 * @license GPL-2.0+
 */

namespace Inc2734\WP_GitHub_Theme_Updater;

class Bootstrap {

	/**
	 * The theme name
	 *
	 * @var string
	 */
	protected $theme_name;

	/**
	 * GitHub user name
	 *
	 * @var string
	 */
	protected $user_name;

	/**
	 * GitHub repository name
	 *
	 * @var string
	 */
	protected $repository;

	/**
	 * Theme data fields
	 *
	 * @var array
	 */
	protected $fields = [];

	/**
	 * @param string $theme_name
	 * @param string $user_name
	 * @param string $repository
	 * @param array $fields Theme data fields
	 */
	public function __construct( $theme_name, $user_name, $repository, array $fields = [] ) {
		$this->theme_name = $theme_name;
		$this->user_name  = $user_name;
		$this->repository = $repository;
		$this->fields     = $fields;

		$upgrader = new App\Model\Upgrader( $theme_name );

		add_filter( 'pre_set_site_transient_update_themes', [ $this, '_pre_set_site_transient_update_themes' ] );
		add_filter( 'upgrader_pre_install', [ $upgrader, 'pre_install' ], 10, 2 );
		add_filter( 'upgrader_source_selection', [ $upgrader, 'source_selection' ], 10, 4 );
	}

	/**
	 * Overwirte site_transient_update_themes from GitHub API
	 *
	 * @param false|array $transient
	 * @return false|array
	 */
	public function _pre_set_site_transient_update_themes( $transient ) {
		$current  = wp_get_theme( $this->theme_name );
		$api_data = $this->_get_transient_api_data();

		if ( is_wp_error( $api_data ) ) {
			$this->_set_notice_error_about_github_api();
			return $transient;
		}

		if ( ! isset( $api_data->tag_name ) ) {
			return $transient;
		}

		if ( ! $this->_should_update( $current['Version'], $api_data->tag_name ) ) {
			return $transient;
		}

		$package = $this->_get_zip_url( $api_data );
		$http_status_code = $this->_get_http_status_code( $package );
		if ( ! $package || ! in_array( $http_status_code, [ 200, 302 ] ) ) {
			error_log( 'Inc2734_WP_GitHub_Theme_Updater error. zip url not found. ' . $http_status_code . ' ' . $package );
			return $transient;
		}

		$transient->response[ $this->theme_name ] = [
			'theme'       => $this->theme_name,
			'new_version' => $api_data->tag_name,
			'url'         => ( ! empty( $this->fields['homepage'] ) ) ? $this->fields['homepage'] : '',
			'package'     => $package,
		];

		return $transient;
	}

	/**
	 * If theme position is themes/my-theme/sub-dir/style.css, don't re-activate the theme.
	 * So, I hooked switch_theme hook point and re-activate the theme.
	 *
	 * @param string $new_name Theme slug (NOT INCLUDING SUB DIRECTORY !) or Theme name.
	 * @param WP_Theme $new_theme
	 * @param WP_Theme $old_theme
	 * @return void
	 */
	public function _re_activate( $new_name, $new_theme, $old_theme ) {
		remove_action( 'switch_theme', [ $this, '_re_activate' ], 10 );
		if ( ! $old_theme->errors() && $new_theme->errors() ) {
			switch_theme( untrailingslashit( $old_theme->get_stylesheet() ) );
		}
	}

	/**
	 * Set notice error about GitHub API using admin_notice hook
	 *
	 * @return void
	 */
	protected function _set_notice_error_about_github_api() {
		$api_data = $this->_get_transient_api_data();
		if ( ! is_wp_error( $api_data ) ) {
			return;
		}

		add_action(
			'admin_notices',
			function() use ( $api_data ) {
				?>
				<div class="notice notice-error">
					<p>
						<?php echo esc_html( $api_data->get_error_message() ); ?>
					</p>
				</div>
				<?php
			}
		);
	}

	/**
	 * Return URL of new zip file
	 *
	 * @param object $remote Data from GitHub API
	 * @return string
	 */
	protected function _get_zip_url( $remote ) {
		$url = false;

		if ( ! empty( $remote->assets ) && is_array( $remote->assets ) ) {
			if ( ! empty( $remote->assets[0] ) && is_object( $remote->assets[0] ) ) {
				if ( ! empty( $remote->assets[0]->browser_download_url ) ) {
					$url = $remote->assets[0]->browser_download_url;
				}
			}
		}

		$tag_name = isset( $remote->tag_name ) ? $remote->tag_name : null;

		if ( ! $url && $tag_name ) {
			$url = sprintf(
				'https://github.com/%1$s/%2$s/archive/%3$s.zip',
				$this->user_name,
				$this->repository,
				$tag_name
			);
		}

		return apply_filters(
			sprintf(
				'inc2734_github_theme_updater_zip_url_%1$s/%2$s',
				$this->user_name,
				$this->repository
			),
			$url,
			$this->user_name,
			$this->repository,
			$tag_name
		);
	}

	/**
	 * Return the data from the Transient API or GitHub API.
	 *
	 * @return object|WP_Error
	 */
	protected function _get_transient_api_data() {
		$transient_name = sprintf( 'wp_github_theme_updater_%1$s', $this->theme_name );
		$transient = get_transient( $transient_name );

		if ( false !== $transient ) {
			return $transient;
		}

		$api_data = $this->_get_github_api_data();
		set_transient( $transient_name, $api_data, 60 * 5 );
		return $api_data;
	}

	/**
	 * Return the data from the GitHub API.
	 *
	 * @return object|WP_Error
	 */
	protected function _get_github_api_data() {
		$response = $this->_request_github_api();
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( 200 == $response_code ) {
			return $body;
		}

		return new \WP_Error(
			$response_code,
			'Inc2734_WP_GitHub_Theme_Updater error. ' . $body->message
		);
	}

	/**
	 * Get request to GitHub API
	 *
	 * @return json|WP_Error
	 */
	protected function _request_github_api() {
		global $wp_version;

		$url = sprintf(
			'https://api.github.com/repos/%1$s/%2$s/releases/latest',
			$this->user_name,
			$this->repository
		);

		return wp_remote_get(
			apply_filters(
				sprintf(
					'inc2734_github_theme_updater_request_url_%1$s/%2$s',
					$this->user_name,
					$this->repository
				),
				$url,
				$this->user_name,
				$this->repository
			),
			[
				'user-agent' => 'WordPress/' . $wp_version,
				'headers'    => [
					'Accept-Encoding' => '',
				],
			]
		);
	}

	/**
	 * Sanitize version
	 *
	 * @param string $version
	 * @return string
	 */
	protected function _sanitize_version( $version ) {
		$version = preg_replace( '/^v(.*)$/', '$1', $version );
		return $version;
	}

	/**
	 * If remote version is newer, return true
	 *
	 * @param string $current_version
	 * @param string $remote_version
	 * @return bool
	 */
	protected function _should_update( $current_version, $remote_version ) {
		return version_compare(
			$this->_sanitize_version( $current_version ),
			$this->_sanitize_version( $remote_version ),
			'<'
		);
	}

	/**
	 * Return http status code from $url
	 *
	 * @param string $url
	 * @return int
	 */
	protected function _get_http_status_code( $url ) {
		global $wp_version;

		$response = wp_remote_head(
			$url,
			[
				'user-agent' => 'WordPress/' . $wp_version,
				'headers'    => [
					'Accept-Encoding' => '',
				],
			]
		);

		return wp_remote_retrieve_response_code( $response );
	}
}
