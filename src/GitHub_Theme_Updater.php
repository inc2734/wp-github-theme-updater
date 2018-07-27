<?php
/**
 * @package wp-github-theme-updater
 * @author inc2734
 * @license GPL-2.0+
 */

namespace Inc2734\WP_GitHub_Theme_Updater;

class GitHub_Theme_Updater {

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
	 * Cache of GitHub API data
	 *
	 * @var object
	 */
	protected $api_data;

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

		add_filter( 'pre_set_site_transient_update_themes', [ $this, '_pre_set_site_transient_update_themes' ] );
		add_filter( 'upgrader_pre_install', [ $this, '_upgrader_pre_install' ], 10, 2 );
		add_filter( 'upgrader_source_selection', [ $this, '_upgrader_source_selection' ], 10, 4 );
	}

	/**
	 * Overwirte site_transient_update_themes from GitHub API
	 *
	 * @param false|array $transient
	 * @return false|array
	 */
	public function _pre_set_site_transient_update_themes( $transient ) {
		$current = wp_get_theme( $this->theme_name );

		if ( is_null( $this->api_data ) ) {
			$transient_name = sprintf( 'wp_github_theme_updater_%1$s', $this->theme_name );
			if ( false === get_transient( $transient_name ) ) {
				$api_data = $this->_get_github_api_data();
				set_transient( $transient_name, $api_data, 60 * 5 );
				$this->api_data = $api_data;
			} else {
				$this->api_data = get_transient( $transient_name );
			}
		}

		if ( is_wp_error( $this->api_data ) ) {
			$this->_set_notice_error_about_github_api();
			return $transient;
		}

		if ( ! $this->_should_update( $current['Version'], $this->api_data->tag_name ) ) {
			return $transient;
		}

		$package = $this->_get_zip_url( $this->api_data );

		$transient->response[ $this->theme_name ] = [
			'theme'       => $this->theme_name,
			'new_version' => $this->api_data->tag_name,
			'url'         => ( ! empty( $this->fields['homepage'] ) ) ? $this->fields['homepage'] : '',
			'package'     => $package,
		];

		return $transient;
	}

	/**
	 * Correspondence when the theme can not be updated
	 *
	 * @param bool $bool
	 * @param array $hook_extra
	 * @return bool|WP_Error.
	 */
	public function _upgrader_pre_install( $bool, $hook_extra ) {
		if ( ! isset( $hook_extra['theme'] ) || $this->theme_name !== $hook_extra['theme'] ) {
			return $bool;
		}

		global $wp_filesystem;

		$theme_dir = trailingslashit( get_theme_root( $this->theme_name ) ) . $this->theme_name;
		if ( ! $wp_filesystem->is_writable( $theme_dir ) ) {
			return new \WP_Error();
		}

		return $bool;
	}

	/**
	 * Expand the theme
	 *
	 * @param string $source
	 * @param string $remote_source
	 * @param WP_Upgrader $install
	 * @param array $args['hook_extra']
	 * @return $source|WP_Error.
	 */
	public function _upgrader_source_selection( $source, $remote_source, $install, $hook_extra ) {
		if ( ! isset( $hook_extra['theme'] ) || $this->theme_name !== $hook_extra['theme'] ) {
			return $source;
		}

		global $wp_filesystem;

		$slash_count = substr_count( $this->theme_name, '/' );
		if ( $slash_count ) {
			add_action( 'switch_theme', [ $this, '_re_activate' ], 10, 3 );
		}

		$source_theme_dir = untrailingslashit( WP_CONTENT_DIR ) . '/upgrade';
		if ( $wp_filesystem->is_writable( $source_theme_dir ) && $wp_filesystem->is_writable( $source ) ) {
			$newsource = trailingslashit( $source_theme_dir ) . trailingslashit( $this->theme_name );
			if ( $wp_filesystem->move( $source, $newsource, true ) ) {
				return $newsource;
			}
		}

		return new \WP_Error();
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
		if ( ! is_wp_error( $this->api_data ) ) {
			return;
		}

		add_action( 'admin_notices', function() {
			?>
			<div class="notice notice-error">
				<p>
					<?php echo esc_html( $this->api_data->get_error_message() ); ?>
				</p>
			</div>
			<?php
		} );
	}

	/**
	 * Return URL of new zip file
	 *
	 * @param object $remote Data from GitHub API
	 * @return string
	 */
	protected function _get_zip_url( $remote ) {
		if ( ! empty( $remote->assets ) && is_array( $remote->assets ) ) {
			if ( ! empty( $remote->assets[0] ) && is_object( $remote->assets[0] ) ) {
				if ( ! empty( $remote->assets[0]->browser_download_url ) ) {
					return $remote->assets[0]->browser_download_url;
				}
			}
		}

		return sprintf(
			'https://github.com/%1$s/%2$s/archive/%3$s.zip',
			$this->user_name,
			$this->repository,
			$remote->tag_name
		);
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
		$url = sprintf(
			'https://api.github.com/repos/%1$s/%2$s/releases/latest',
			$this->user_name,
			$this->repository
		);

		return wp_remote_get( $url, [
			'headers' => [
				'Accept-Encoding' => '',
			],
		] );
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
}
