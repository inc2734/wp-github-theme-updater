<?php
/**
 * @package wp-github-theme-updater
 * @author inc2734
 * @license GPL-2.0+
 */

namespace Inc2734\WP_GitHub_Theme_Updater\App\Model;

class Requester {

	/**
	 * Performs an HTTP request using the GET method and returns its response.
	 * To maintain backward compatibility, it is also necessary to deal with cases where the second and third arguments are missing.
	 *
	 * @param string $url URL to retrieve.
	 * @param string|null $user_name GitHub user name.
	 * @param string|null $repository GitHub repository name.
	 * @return array|WP_Error
	 */
	public static function request( $url, $user_name = null, $repository = null ) {
		$args = static::_generate_args( $url, $user_name, $repository );

		return wp_remote_get( $url, $args );
	}

	/**
	 * Performs an HTTP request using the HEAD method and returns its response.
	 *
	 * @param string $url URL to retrieve.
	 * @param string $user_name GitHub user name.
	 * @param string $repository GitHub repository name.
	 * @return array|WP_Error
	 */
	public static function head( $url, $user_name, $repository ) {
		$args = static::_generate_args( $url, $user_name, $repository );

		return wp_remote_head( $url, $args );
	}

	/**
	 * Generate request arguments.
	 *
	 * @param string $url URL to retrieve.
	 * @param string $user_name GitHub user name.
	 * @param string $repository GitHub repository name.
	 * @return array
	 */
	protected static function _generate_args( $url, $user_name, $repository ) {
		global $wp_version;

		return apply_filters(
			'inc2734_github_theme_updater_requester_args',
			array(
				'user-agent' => 'WordPress/' . $wp_version,
				'timeout'    => 30,
				'headers'    => array(
					'Accept-Encoding' => '',
				),
			),
			$url,
			$user_name,
			$repository
		);
	}
}
