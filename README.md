# WP GitHub Theme Updater

[![Build Status](https://travis-ci.org/inc2734/wp-github-theme-updater.svg?branch=master)](https://travis-ci.org/inc2734/wp-github-theme-updater)
[![Latest Stable Version](https://poser.pugx.org/inc2734/wp-github-theme-updater/v/stable)](https://packagist.org/packages/inc2734/wp-github-theme-updater)
[![License](https://poser.pugx.org/inc2734/wp-github-theme-updater/license)](https://packagist.org/packages/inc2734/wp-github-theme-updater)

## Install
```
$ composer require inc2734/wp-github-theme-updater
```

## How to use
```
<?php
// When Using composer auto loader
$updater = new Inc2734\WP_GitHub_Theme_Updater\Bootstrap( get_template(), 'user-name', 'repository' );
```

## Filter hooks
### inc2734_github_theme_updater_zip_url

Customize downloaded api url.

```
add_filter(
  'inc2734_github_theme_updater_zip_url',
  function( $url, $user_name, $repository, $tag_name ) {
    return $url;
  },
  10,
  4
);
```

### inc2734_github_theme_updater_request_url

Customize requested api url.

```
add_filter(
  'inc2734_github_theme_updater_request_url',
  function( $url, $user_name, $repository ) {
    return $url;
  },
  10,
  3
);
```
