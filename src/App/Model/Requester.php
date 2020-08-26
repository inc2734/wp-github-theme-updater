<?php
/**
 * @package wp-github-theme-updater
 * @author inc2734
 * @license GPL-2.0+
 */

namespace Inc2734\WP_GitHub_Theme_Updater\App\Model;

class Requester {

	public static function request( $url ) {
		global $wp_version;

		return wp_remote_get(
			$url,
			[
				'user-agent' => 'WordPress/' . $wp_version,
				'timeout'    => 30,
				'headers'    => [
					'Accept-Encoding' => '',
				],
			]
		);
	}
}
