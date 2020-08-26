<?php
/**
 * @package wp-github-theme-updater
 * @author inc2734
 * @license GPL-2.0+
 */

namespace Inc2734\WP_GitHub_Theme_Updater;

use WP_Error;
use stdClass;
use Inc2734\WP_GitHub_Theme_Updater\App\Model\Fields;
use Inc2734\WP_GitHub_Theme_Updater\App\Model\GitHubReleases;
use Inc2734\WP_GitHub_Theme_Updater\App\Model\GitHubRepositoryContent;

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
	 * @var Fields
	 */
	protected $fields;

	/**
	 * @var GitHubReleases
	 */
	protected $github_releases;

	/**
	 * @var GitHubRepositoryContent
	 */
	protected $github_repository_content;

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
		$this->fields     = new Fields( $fields );

		load_textdomain( 'inc2734-wp-github-theme-updater', __DIR__ . '/languages/' . get_locale() . '.mo' );

		$upgrader = new App\Model\Upgrader( $theme_name );
		$this->github_releases = new GitHubReleases( $theme_name, $user_name, $repository );
		$this->github_repository_content = new GitHubRepositoryContent( $theme_name, $user_name, $repository );

		add_filter( 'pre_set_site_transient_update_themes', [ $this, '_pre_set_site_transient_update_themes' ] );
		add_filter( 'upgrader_pre_install', [ $upgrader, 'pre_install' ], 10, 2 );
		add_filter( 'upgrader_source_selection', [ $upgrader, 'source_selection' ], 10, 4 );
		add_action( 'upgrader_process_complete', [ $this, '_upgrader_process_complete' ], 10, 2 );
	}

	/**
	 * Overwrite site_transient_update_themes from GitHub API
	 *
	 * @see https://make.wordpress.org/core/2020/07/30/recommended-usage-of-the-updates-api-to-support-the-auto-updates-ui-for-plugins-and-themes-in-wordpress-5-5/
	 *
	 * @param false|array $transient
	 * @return false|array
	 */
	public function _pre_set_site_transient_update_themes( $transient ) {
		$response = $this->github_releases->get();
		if ( is_wp_error( $response ) ) {
			error_log( $response->get_error_message() );
			return $transient;
		}

		if ( ! isset( $response->tag_name ) ) {
			return $transient;
		}

		if ( ! $response->package ) {
			return $transient;
		}

		$remote = $this->github_repository_content->get_headers();
		$update = [
			'theme'        => $this->theme_name,
			'new_version'  => $response->tag_name,
			'url'          => $this->fields->get( 'homepage' ),
			'package'      => $response->package,
			'tested'       => $this->fields->get( 'tested' ) ? $this->fields->get( 'tested' ) : $remote['Tested up to'],
			'requires'     => $this->fields->get( 'requires' ) ? $this->fields->get( 'require' ) : $remote['RequiresWP'],
			'requires_php' => $this->fields->get( 'requires_php' ) ? $this->fields->get( 'requires_php' ) : $remote['RequiresPHP'],
		];

		$update = apply_filters(
			sprintf(
				'inc2734_github_theme_updater_transient_response_%1$s/%2$s',
				$this->user_name,
				$this->repository
			),
			$update
		);

		$current = wp_get_theme( $this->theme_name );
		if ( ! $this->_should_update( $current['Version'], $response->tag_name ) ) {
			if ( false === $transient ) {
				$transient = new stdClass();
				$transient->no_update = [];
			}
			$transient->no_update[ $this->theme_name ] = $update;
		} else {
			if ( false === $transient ) {
				$transient = new stdClass();
				$transient->response = [];
			}
			$transient->response[ $this->theme_name ] = $update;
		}

		return $transient;
	}

	/**
	 * Fires when the upgrader process is complete.
	 *
	 * @param WP_Upgrader $upgrader_object
	 * @param array $hook_extra
	 */
	public function _upgrader_process_complete( $upgrader_object, $hook_extra ) {
		if ( 'update' === $hook_extra['action'] && 'theme' === $hook_extra['type'] ) {
			foreach ( $hook_extra['themes'] as $theme ) {
				if ( $theme === $this->theme_name ) {
					$this->github_releases->delete_transient();
					$this->github_repository_content->delete_transient();
				}
			}
		}
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
