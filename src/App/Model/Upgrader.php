<?php
/**
 * @package wp-github-theme-updater
 * @author inc2734
 * @license GPL-2.0+
 */

namespace Inc2734\WP_GitHub_Theme_Updater\App\Model;

use WP_Error;

class Upgrader {

	/**
	 * The theme name
	 *
	 * @var string
	 */
	protected $theme_name;

	/**
	 * Constructor.
	 *
	 * @param string $theme_name Theme name.
	 */
	public function __construct( $theme_name ) {
		$this->theme_name = $theme_name;
	}

	/**
	 * Filters the installation response before the installation has started.
	 *
	 * @param bool|WP_Error $bool       Installation response.
	 * @param array         $hook_extra Extra arguments passed to hooked filters.
	 * @return bool|WP_Error.
	 */
	public function pre_install( $bool, $hook_extra ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.boolFound
		if ( ! isset( $hook_extra['theme'] ) || $this->theme_name !== $hook_extra['theme'] ) {
			return $bool;
		}

		global $wp_filesystem;

		$theme_dir = trailingslashit( get_theme_root( $this->theme_name ) ) . $this->theme_name;
		if ( ! $wp_filesystem->is_writable( $theme_dir ) ) {
			return new WP_Error();
		}

		return $bool;
	}

	/**
	 * Filters whether to return the package.
	 *
	 * @param bool $reply Whether to bail without returning the package.
	 * @param string $package The package file name.
	 * @param WP_Upgrader $upgrader The WP_Upgrader instance.
	 * @param array $hook_extra Extra arguments passed to hooked filters.
	 * @return bool
	 */
	public function upgrader_pre_download( $reply, $package, $upgrader, $hook_extra ) {
		if ( ! empty( $hook_extra['theme'] ) && $this->theme_name === $hook_extra['theme'] ) {
			$upgrader->strings['downloading_package'] = __( 'Downloading update&#8230;', 'inc2734-wp-github-theme-updater' );
		}
		return $reply;
	}

	/**
	 * Filters the source file location for the upgrade package.
	 *
	 * @param string      $source        File source location. e.g. /wp-content/upgrade/snow-monkey-EjvQyV/snow-monkey/.
	 * @param string      $remote_source Remote file source location. e.g. /wp-content/upgrade/snow-monkey-EjvQyV.
	 * @param WP_Upgrader $install       WP_Upgrader instance.
	 * @param array       $hook_extra    Extra arguments passed to hooked filters.
	 * @return $source|WP_Error. e.g. /wp-content/upgrade/snow-monkey/.
	 */
	public function source_selection( $source, $remote_source, $install, $hook_extra ) {
		if ( ! isset( $hook_extra['theme'] ) || $this->theme_name !== $hook_extra['theme'] ) {
			return $source;
		}

		global $wp_filesystem;

		$slash_count = substr_count( $this->theme_name, '/' );
		if ( $slash_count ) {
			add_action( 'switch_theme', array( $this, '_re_activate' ), 10, 3 );
		}

		$subdir_name = untrailingslashit( str_replace( trailingslashit( $remote_source ), '', $source ) );
		if ( ! empty( $subdir_name ) && $subdir_name !== $this->theme_name ) {
			$from_path = untrailingslashit( $source );
			$to_path   = trailingslashit( $remote_source ) . $this->theme_name;
			if ( true === $wp_filesystem->move( $from_path, $to_path ) ) {
				return $to_path;
			}

			return new WP_Error();
		}

		return $source;
	}
}
