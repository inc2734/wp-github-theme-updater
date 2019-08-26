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
	 * @param string $theme_name
	 */
	public function __construct( $theme_name ) {
		$this->theme_name = $theme_name;
	}

	/**
	 * Correspondence when the theme can not be updated
	 *
	 * @param bool $bool
	 * @param array $hook_extra
	 * @return bool|WP_Error.
	 */
	public function pre_install( $bool, $hook_extra ) {
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
	 * Expand the theme
	 *
	 * @param string $source
	 * @param string $remote_source
	 * @param WP_Upgrader $install
	 * @param array $args['hook_extra']
	 * @return $source|WP_Error.
	 */
	public function source_selection( $source, $remote_source, $install, $hook_extra ) {
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

		return new WP_Error();
	}
}
