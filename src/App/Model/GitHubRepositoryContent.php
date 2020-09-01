<?php
/**
 * @package wp-github-theme-updater
 * @author inc2734
 * @license GPL-2.0+
 */

namespace Inc2734\WP_GitHub_Theme_Updater\App\Model;

use Inc2734\WP_GitHub_Theme_Updater\App\Model\Requester;

class GitHubRepositoryContent {

	protected $theme_name;

	protected $user_name;

	protected $repository;

	protected $transient_name;

	public function __construct( $theme_name, $user_name, $repository ) {
		$this->theme_name  = $theme_name;
		$this->user_name   = $user_name;
		$this->repository  = $repository;
		$this->transient_name = sprintf( 'wp_github_theme_updater_repository_data_%1$s', $this->theme_name );
	}

	public function get() {
		$transient = get_transient( $this->transient_name );
		if ( false !== $transient ) {
			return $transient;
		}

		$response = $this->_request();
		$response = $this->_retrieve( $response );

		set_transient( $this->transient_name, $response, 0 );
		return $response;
	}

	public function delete_transient() {
		delete_transient( $this->transient_name );
	}

	/**
	 * @see https://developer.wordpress.org/reference/functions/get_file_data/
	 * @see https://developer.wordpress.org/reference/functions/wp_get_theme/
	 */
	public function get_headers() {
		$headers = [];

		$content = $this->get();

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

		return apply_filters(
			sprintf(
				'inc2734_github_theme_updater_repository_content_headers_%1$s/%2$s',
				$this->user_name,
				$this->repository
			),
			$headers
		);
	}

	protected function _retrieve( $response ) {
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! isset( $body->content ) ) {
			return null;
		}

		return base64_decode( $body->content );
	}

	protected function _request() {
		$current = wp_get_theme( $this->theme_name );

		$url = sprintf(
			'https://api.github.com/repos/%1$s/%2$s/contents/%3$s',
			$this->user_name,
			$this->repository,
			preg_replace( '|^([^/]*?/)|', '', $current->get( 'Stylesheet' ) ) . '/style.css'
		);

		$url = apply_filters(
			sprintf(
				'inc2734_github_theme_updater_repository_content_url_%1$s/%2$s',
				$this->user_name,
				$this->repository
			),
			$url,
			$this->user_name,
			$this->repository,
			$this->theme_name
		);

		return Requester::request( $url );
	}
}
