<?php
/**
 * @package wp-github-theme-updater
 * @author inc2734
 * @license GPL-2.0+
 */

namespace Inc2734\WP_GitHub_Theme_Updater\App\Model;

class Requester {

	/**
	 * Request.
	 *
	 * @param string $url A url of the request destination.
	 * @param string $user_name GitHub user name.
	 * @param string $repository GitHub repository name.
	 * @return array|WP_Error
	 */
	public static function request( $url, $user_name, $repository ) {
		global $wp_version;

		$args = apply_filters(
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

		return wp_remote_get( $url, $args );
	}
}
