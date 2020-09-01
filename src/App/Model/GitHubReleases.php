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

	protected $theme_name;

	protected $user_name;

	protected $repository;

	protected $transient_name;

	public function __construct( $theme_name, $user_name, $repository ) {
		$this->theme_name  = $theme_name;
		$this->user_name   = $user_name;
		$this->repository  = $repository;
		$this->transient_name = sprintf( 'wp_github_theme_updater_%1$s', $this->theme_name );
	}

	public function get() {
		$transient = get_transient( $this->transient_name );
		if ( false !== $transient ) {
			return $transient;
		}

		$response = $this->_request();
		$response = $this->_retrieve( $response );

		set_transient( $this->transient_name, $response, 60 * 5 );
		return $response;
	}

	public function delete_transient() {
		delete_transient( $this->transient_name );
	}

	protected function _retrieve( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( '' === $response_code ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( 200 == $response_code ) {
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
			error_log( 'Inc2734_WP_GitHub_Theme_Updater error. [' . $response_code . '] ' . $error_message );
		}

		return new WP_Error(
			$response_code,
			$error_message
		);
	}

	protected function _request() {
		$url = sprintf(
			'https://api.github.com/repos/%1$s/%2$s/releases/latest',
			$this->user_name,
			$this->repository
		);

		$url = apply_filters(
			sprintf(
				'inc2734_github_theme_updater_request_url_%1$s/%2$s',
				$this->user_name,
				$this->repository
			),
			$url,
			$this->user_name,
			$this->repository
		);

		return Requester::request( $url );
	}

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

			$http_status_code = $this->_get_http_status_code( $url );
			if ( ! in_array( $http_status_code, [ 200, 302 ] ) ) {
				$url = sprintf(
					'https://github.com/%1$s/%2$s/archive/%3$s.zip',
					$this->user_name,
					$this->repository,
					$tag_name
				);
			}
		}

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

		if ( ! $url ) {
			error_log( 'Inc2734_WP_GitHub_Theme_Updater error. zip url not found.' );
			return false;
		}

		$http_status_code = $this->_get_http_status_code( $url );
		if ( ! in_array( $http_status_code, [ 200, 302 ] ) ) {
			error_log( 'Inc2734_WP_GitHub_Theme_Updater error. zip url not found. ' . $http_status_code . ' ' . $url );
			return false;
		}

		return $url;
	}

	/**
	 * Return http status code from $url
	 *
	 * @param string $url
	 * @return int
	 */
	protected function _get_http_status_code( $url ) {
		$response = Requester::request( $url );
		return wp_remote_retrieve_response_code( $response );
	}
}
