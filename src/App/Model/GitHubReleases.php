<?php
/**
 * @package wp-github-theme-updater
 * @author inc2734
 * @license GPL-2.0+
 */

namespace Inc2734\WP_GitHub_Theme_Updater\App\Model;

use WP_Error;
use Inc2734\WP_GitHub_Theme_Updater\App\Model\Requester;

class GitHubReleases {

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
		$this->transient_name = sprintf( 'wp_github_theme_updater_%1$s', $this->theme_name );
	}

	/**
	 * Get response of GitHub API.
	 *
	 * @param string|null $version Version.
	 * @return array|WP_Error
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
	 * Retrive.
	 *
	 * @param array|WP_Error $response HTTP response.
	 * @return array|WP_Error
	 */
	protected function _retrieve( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( '' === $response_code ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( 200 === (int) $response_code ) {
			$body->package = $body->tag_name ? $this->_get_zip_url( $body ) : false;
			return $body;
		}

		$message = null !== $body && property_exists( $body, 'message' )
			? $body->message
			: __( 'Failed to get update response.', 'inc2734-wp-github-theme-updater' );

		$error_message = sprintf(
			/* Translators: 1: Theme name, 2: Error message  */
			__( '[%1$s] %2$s', 'inc2734-wp-github-theme-updater' ),
			$this->theme_name,
			$message
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Inc2734_WP_GitHub_Theme_Updater error. [' . $response_code . '] ' . $error_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return new WP_Error(
			$response_code,
			$error_message
		);
	}

	/**
	 * Request.
	 *
	 * @param string|null $version Version.
	 * @return array|WP_Error
	 */
	protected function _request( $version = null ) {
		$url = ! $version
			? sprintf(
				'https://api.github.com/repos/%1$s/%2$s/releases/latest',
				$this->user_name,
				$this->repository
			)
			: sprintf(
				'https://api.github.com/repos/%1$s/%2$s/releases/tags/%3$s',
				$this->user_name,
				$this->repository,
				$version
			);

		// phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
		$url = apply_filters(
			sprintf(
				'inc2734_github_theme_updater_request_url_%1$s/%2$s',
				$this->user_name,
				$this->repository
			),
			$url,
			$this->user_name,
			$this->repository,
			$version
		);
		// phpcs:enable

		return Requester::request( $url );
	}

	/**
	 * Get remote zip URL.
	 *
	 * @param stdClass $response Responser of GitHub API.
	 * @return string|false
	 */
	protected function _get_zip_url( $response ) {
		$url = false;

		if ( ! empty( $response->assets ) && is_array( $response->assets ) ) {
			if ( ! empty( $response->assets[0] ) && is_object( $response->assets[0] ) ) {
				if ( ! empty( $response->assets[0]->browser_download_url ) ) {
					$url = $response->assets[0]->browser_download_url;
				}
			}
		}

		$tag_name = isset( $response->tag_name ) ? $response->tag_name : null;

		if ( ! $url && $tag_name ) {
			$url = sprintf(
				'https://github.com/%1$s/%2$s/releases/download/%3$s/%2$s.zip',
				$this->user_name,
				$this->repository,
				$tag_name
			);
		}

		// phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
		$url = apply_filters(
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
		// phpcs:enable

		if ( ! $url ) {
			error_log( 'Inc2734_WP_GitHub_Theme_Updater error. zip url not found.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return false;
		}

		$http_status_code = $this->_get_http_status_code( $url );
		if ( ! in_array( (int) $http_status_code, array( 200, 302 ), true ) ) {
			error_log( 'Inc2734_WP_GitHub_Theme_Updater error. zip url not found. ' . $http_status_code . ' ' . $url ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return false;
		}

		return $url;
	}

	/**
	 * Return http status code from $url.
	 *
	 * @param string $url URL.
	 * @return int
	 */
	protected function _get_http_status_code( $url ) {
		$response = Requester::request( $url );
		return wp_remote_retrieve_response_code( $response );
	}
}
