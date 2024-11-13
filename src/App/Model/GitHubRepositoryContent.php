<?php
/**
 * @package wp-github-theme-updater
 * @author inc2734
 * @license GPL-2.0+
 */

namespace Inc2734\WP_GitHub_Theme_Updater\App\Model;

use WP_Error;
use Inc2734\WP_GitHub_Theme_Updater\App\Model\Requester;

class GitHubRepositoryContent {

	/**
	 * Theme name.
	 *
	 * @var string
	 */
	protected $theme_name;

	/**
	 * User name.
	 *
	 * @var string
	 */
	protected $user_name;

	/**
	 * Repository.
	 *
	 * @var string
	 */
	protected $repository;

	/**
	 * Transient name.
	 *
	 * @var string
	 */
	protected $transient_name;

	/**
	 * Constructor.
	 *
	 * @param string $theme_name Theme name.
	 * @param string $user_name  User name.
	 * @param string $repository Repository.
	 */
	public function __construct( $theme_name, $user_name, $repository ) {
		$this->theme_name     = $theme_name;
		$this->user_name      = $user_name;
		$this->repository     = $repository;
		$this->transient_name = sprintf( 'wp_github_theme_updater_repository_data_%1$s', $this->theme_name );
	}

	/**
	 * Get GitHub repository content.
	 *
	 * @param string|null $version Version.
	 * @return string
	 */
	public function get( $version = null ) {
		$transient = get_transient( $this->transient_name );
		if ( ! is_array( $transient ) ) {
			$transient = array();
		}

		if ( ! $version && ! empty( $transient['latest'] ) ) {
			return $transient['latest'];
		} elseif ( ! empty( $transient[ $version ] ) ) {
			return $transient[ $version ];
		}

		$response = $this->_request( $version );
		$response = $this->_retrieve( $response );

		if ( ! is_wp_error( $response ) ) {
			if ( ! $version ) {
				$transient['latest'] = $response;
			} else {
				$transient[ $version ] = $response;
			}
			set_transient( $this->transient_name, $transient, 60 * 5 );
		} else {
			$this->delete_transient();
		}

		return $response;
	}

	/**
	 * Delete transient.
	 */
	public function delete_transient() {
		delete_transient( $this->transient_name );
	}

	/**
	 * Get HTTP headers.
	 *
	 * @see https://developer.wordpress.org/reference/functions/get_file_data/
	 * @see https://developer.wordpress.org/reference/functions/wp_get_theme/
	 *
	 * @param string|null $version Version.
	 * @return array
	 */
	public function get_headers( $version = null ) {
		$headers = array();

		$content = $this->get( $version );

		$target_headers = array(
			'RequiresWP'   => 'Requires at least',
			'RequiresPHP'  => 'Requires PHP',
			'Tested up to' => 'Tested up to',
		);

		if ( null !== $content ) {
			$content = substr( $content, 0, 8 * KB_IN_BYTES );
			$content = str_replace( "\r", "\n", $content );
		}

		foreach ( $target_headers as $field => $regex ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $content, $match ) && $match[1] ) {
				$headers[ $field ] = _cleanup_header_comment( $match[1] );
			} else {
				$headers[ $field ] = '';
			}
		}

		// phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
		return apply_filters(
			sprintf(
				'inc2734_github_theme_updater_repository_content_headers_%1$s/%2$s',
				$this->user_name,
				$this->repository
			),
			$headers,
			$this->user_name,
			$this->repository,
			$version
		);
		// phpcs:enable
	}

	/**
	 * Retrieve.
	 *
	 * @param array|WP_Error $response HTTP response.
	 * @return string
	 */
	protected function _retrieve( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_code = $response_code ? $response_code : 503;
		if ( 200 !== (int) $response_code ) {
			return new WP_Error(
				$response_code,
				sprintf(
					'[%1$s] Failed to get GitHub repository content. HTTP status is "%2$s"',
					$this->theme_name,
					$response_code
				)
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! is_object( $body ) ) {
			return new WP_Error(
				$response_code,
				sprintf(
					'[%1$s] Failed to get GitHub repository content',
					$this->theme_name
				)
			);
		}

		if ( ! isset( $body->content ) ) {
			return null;
		}

		return base64_decode( $body->content ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}

	/**
	 * Requesst.
	 *
	 * @param string|null $version Version.
	 * @return array|WP_Error
	 */
	protected function _request( $version = null ) {
		$current = wp_get_theme( $this->theme_name );

		$url = ! $version
			? sprintf(
				'https://api.github.com/repos/%1$s/%2$s/contents/%3$s',
				$this->user_name,
				$this->repository,
				preg_replace( '|^([^/]*?/)|', '', $current->get( 'Stylesheet' ) ) . '/style.css'
			)
			: sprintf(
				'https://api.github.com/repos/%1$s/%2$s/contents/%3$s?ref=%4$s',
				$this->user_name,
				$this->repository,
				preg_replace( '|^([^/]*?/)|', '', $current->get( 'Stylesheet' ) ) . '/style.css',
				$version
			);

		// phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
		$url = apply_filters(
			sprintf(
				'inc2734_github_theme_updater_repository_content_url_%1$s/%2$s',
				$this->user_name,
				$this->repository
			),
			$url,
			$this->user_name,
			$this->repository,
			$this->theme_name,
			$version
		);
		// phpcs:enable

		return Requester::request( $url );
	}
}
