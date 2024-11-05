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
	 * Constructor.
	 *
	 * @param string $theme_name Theme name.
	 * @param string $user_name User name.
	 * @param string $repository Repository.
	 * @param array  $fields Theme data fields.
	 */
	public function __construct( $theme_name, $user_name, $repository, array $fields = array() ) {
		$this->theme_name = $theme_name;
		$this->user_name  = $user_name;
		$this->repository = $repository;
		$this->fields     = new Fields( $fields );

		$upgrader                        = new App\Model\Upgrader( $theme_name );
		$this->github_releases           = new GitHubReleases( $theme_name, $user_name, $repository );
		$this->github_repository_content = new GitHubRepositoryContent( $theme_name, $user_name, $repository );

		add_filter( 'init', array( $this, '_init' ) );
		add_filter( 'pre_set_site_transient_update_themes', array( $this, '_pre_set_site_transient_update_themes' ) );
		add_filter( 'upgrader_pre_install', array( $upgrader, 'pre_install' ), 10, 2 );
		add_filter( 'upgrader_pre_download', array( $upgrader, 'upgrader_pre_download' ), 10, 4 );
		add_filter( 'upgrader_source_selection', array( $upgrader, 'source_selection' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, '_upgrader_process_complete' ), 10, 2 );
	}

	/**
	 * Load textdomain.
	 */
	public function _init() {
		load_textdomain( 'inc2734-wp-github-theme-updater', __DIR__ . '/languages/' . get_locale() . '.mo' );
	}

	/**
	 * Overwrite site_transient_update_themes from GitHub API.
	 *
	 * @see https://make.wordpress.org/core/2020/07/30/recommended-usage-of-the-updates-api-to-support-the-auto-updates-ui-for-plugins-and-themes-in-wordpress-5-5/
	 *
	 * @throws \RuntimeException Invalid response.
	 *
	 * @param false|array $transient New value of site transient.
	 * @return false|array
	 */
	public function _pre_set_site_transient_update_themes( $transient ) {
		$response = $this->github_releases->get();
		try {
			if ( is_wp_error( $response ) ) {
				throw new \RuntimeException( $response->get_error_message() );
			}
		} catch ( \Exception $e ) {
			error_log( $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return $transient;
		}

		if ( ! isset( $response->tag_name ) ) {
			return $transient;
		}

		if ( ! $response->package ) {
			return $transient;
		}

		$remote = $this->github_repository_content->get_headers( $response->tag_name );
		$update = array(
			'theme'        => $this->theme_name,
			'new_version'  => $response->tag_name,
			'url'          => $this->fields->get( 'homepage' ),
			'package'      => $response->package,
			'tested'       => $this->fields->get( 'tested' ) ? $this->fields->get( 'tested' ) : $remote['Tested up to'],
			'requires'     => $this->fields->get( 'requires' ) ? $this->fields->get( 'require' ) : $remote['RequiresWP'],
			'requires_php' => $this->fields->get( 'requires_php' ) ? $this->fields->get( 'requires_php' ) : $remote['RequiresPHP'],
		);

		// phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
		$update = apply_filters(
			sprintf(
				'inc2734_github_theme_updater_transient_response_%1$s/%2$s',
				$this->user_name,
				$this->repository
			),
			$update
		);
		// phpcs:enable

		$current = wp_get_theme( $this->theme_name );
		if ( ! $this->_should_update( $current['Version'], $response->tag_name ) ) {
			if ( false === $transient || null === $transient ) {
				$transient = new stdClass();
			}
			if ( empty( $transient->no_update ) ) {
				$transient->no_update = array();
			}
			$transient->no_update[ $this->theme_name ] = $update;
		} else {
			if ( false === $transient || null === $transient ) {
				$transient           = new stdClass();
				$transient->response = array();
			}
			$transient->response[ $this->theme_name ] = $update;
		}

		return $transient;
	}

	/**
	 * Fires when the upgrader process is complete.
	 *
	 * @param WP_Upgrader $upgrader_object WP_Upgrader instance. In other contexts this might be a Theme_Upgrader, Plugin_Upgrader, Core_Upgrade, or Language_Pack_Upgrader instance.
	 * @param array       $hook_extra Array of bulk item update data.
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
	 * @param string   $new_name Theme slug (NOT INCLUDING SUB DIRECTORY !) or Theme name.
	 * @param WP_Theme $new_theme New WP_Theme object.
	 * @param WP_Theme $old_theme Old WP_Theme object.
	 */
	public function _re_activate( $new_name, $new_theme, $old_theme ) {
		remove_action( 'switch_theme', array( $this, '_re_activate' ), 10 );
		if ( ! $old_theme->errors() && $new_theme->errors() ) {
			switch_theme( untrailingslashit( $old_theme->get_stylesheet() ) );
		}
	}

	/**
	 * Sanitize version.
	 *
	 * @param string $version Version.
	 * @return string
	 */
	protected function _sanitize_version( $version ) {
		$version = preg_replace( '/^v(.*)$/', '$1', $version );
		return $version;
	}

	/**
	 * If remote version is newer, return true.
	 *
	 * @param string $current_version Current version.
	 * @param string $remote_version Remove version.
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
