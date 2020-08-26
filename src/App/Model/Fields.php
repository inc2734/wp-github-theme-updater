<?php
/**
 * @package wp-github-theme-updater
 * @author inc2734
 * @license GPL-2.0+
 */

namespace Inc2734\WP_GitHub_Theme_Updater\App\Model;

/**
 * @see https://github.com/WordPress/WordPress/blob/master/wp-admin/includes/theme.php#L402-L424#L67-L95
 */
class Fields {

	/**
	 * Whether to return the theme full description.
	 *
	 * @var string|boolean
	 */
	public $description = false;

	/**
	 * Whether to return the theme readme sections: description, installation,
   * FAQ, screenshots, other notes, and changelog.
   *
	 * @var array|boolean
	 *   @var string description
	 *   @var string installation
	 *   @var string faq
	 *   @var string screenshots
	 *   @var string changelog
	 *   @var string reviews
	 *   @var string other_notes
	 */
	public $sections = false;

	/**
	 * Whether to return the rating in percent and total number of ratings.
	 *
	 * @var int|boolean
	 */
	public $rating = false;

	/**
	 * Whether to return the number of rating for each star (1-5).
	 *
	 * @var array|boolean
	 */
	public $ratings = false;

	/**
	 * Whether to return the download count.
	 *
	 * @var int|boolean
	 */
	public $downloaded = false;

	/**
	 * Whether to return the download link for the package.
	 *
	 * @var string|boolean
	 */
	public $download_link = false;

	/**
	 * Whether to return the date of the last update.
	 *
	 * @var string|boolean
	 */
	public $last_updated = false;

	/**
	 * Whether to return the assigned tags.
	 *
	 * @var array|boolean
	 */
	public $tags = false;

	/**
	 * Whether to return the theme homepage link.
	 *
	 * @var string|boolean
	 */
	public $homepage = false;

	/**
	 * Whether to return the screenshots.
	 *
	 * @var array|boolean
	 */
	public $screenshots = false;

	/**
	 * Number of screenshots to return.
	 *
	 * @var int
	 */
	public $screenshot_count = 1;

	/**
	 * Whether to return the URL of the first screenshot.
	 *
	 * @var string|boolean
	 */
	public $screenshot_url = false;

	/**
	 * Whether to return the screenshots via Photon.
	 *
	 * @var array|boolean
	 */
	public $photon_screenshots = false;

	/**
	 * Whether to return the slug of the parent theme.
	 *
	 * @var string|boolean
	 */
	public $template = false;

	/**
	 * Whether to return the slug, name and homepage of the parent theme.
	 *
	 * @var string|boolean
	 */
	public $parent = false;

	/**
	 * Whether to return the list of all available versions.
	 *
	 * @var array|boolean
	 */
	public $versions = false;

	/**
	 * Whether to return theme's URL.
	 *
	 * @var url|boolean
	 */
	public $theme_url = false;

	/**
	 * Whether to return nicename or nicename and display name.
	 *
	 * @var url|boolean
	 */
	public $extended_author = false;

	/**
	 * @param array $fields
	 */
	public function __construct( array $fields ) {
		foreach ( $fields as $field => $value ) {
			if ( property_exists( $this, $field ) ) {
				$this->$field = $value;
			}
		}
	}

	/**
	 * Return specific property
	 *
	 * @param string $field
	 * @return mixed
	 */
	public function get( $field ) {
		if ( property_exists( $this, $field ) ) {
			return $this->$field;
		}
		return false;
	}
}
