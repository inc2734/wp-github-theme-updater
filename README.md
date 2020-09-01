# WP GitHub Theme Updater

[![Build Status](https://travis-ci.com/inc2734/wp-github-theme-updater.svg?branch=master)](https://travis-ci.com/inc2734/wp-github-theme-updater)
[![Latest Stable Version](https://poser.pugx.org/inc2734/wp-github-theme-updater/v/stable)](https://packagist.org/packages/inc2734/wp-github-theme-updater)
[![License](https://poser.pugx.org/inc2734/wp-github-theme-updater/license)](https://packagist.org/packages/inc2734/wp-github-theme-updater)

## Install
```
$ composer require inc2734/wp-github-theme-updater
```

## How to use
```
<?php
$updater = new Inc2734\WP_GitHub_Theme_Updater\Bootstrap(
  get_template(),
  'user-name',
  'repository'
);
```

## Filter hooks
### inc2734_github_theme_updater_zip_url_<$user_name>/<$repository>

Customize downloaded package url.

```
add_filter(
  'inc2734_github_theme_updater_zip_url_inc2734/snow-monkey',
  function( $url, $user_name, $repository, $tag_name ) {
    return $url;
  },
  10,
  4
);
```

### inc2734_github_theme_updater_request_url_<$user_name>/<$repository>

Customize requested api url.

```
add_filter(
  'inc2734_github_theme_updater_request_url_inc2734/snow-monkey',
  function( $url, $user_name, $repository ) {
    return $url;
  },
  10,
  3
);
```

### inc2734_github_theme_updater_transient_response_<$user_name>/<$repository>

Customize transient response

```
add_filter(
  'inc2734_github_theme_updater_transient_response_inc2734/snow-monkey',
  function( $transient_response ) {
    return $transient_response;
  }
);
```

### inc2734_github_theme_updater_repository_content_url_<$user_name>/<$repository>

Customize contents api url.

```
add_filter(
  'inc2734_github_theme_updater_repository_content_url_inc2734/snow-monkey',
  function( $url, $user_name, $repository, $theme_name ) {
    return $url;
  },
  10,
  4
);
```

### inc2734_github_theme_updater_repository_content_headers_<$user_name>/<$repository>

Customize fields contents_api.

```
add_filter(
  'inc2734_github_theme_updater_repository_content_headers_inc2734/snow-monkey',
  function( $headers ) {
    return $headers;
  }
);
```

### inc2734_github_theme_updater_zip_url

**Obsolete from v2.0.0**

Customize downloaded api url.

```
add_filter(
  'inc2734_github_theme_updater_zip_url',
  function( $url, $user_name, $repository, $tag_name ) {
    if ( 'inc2734' === $user_name && 'snow-monkey-blocks' === $repository ) {
      return 'https://example.com/my-custom-updater-zip-url';
    }
    return $url;
  },
  10,
  4
);
```

### inc2734_github_theme_updater_request_url

**Obsolete from v2.0.0**

Customize requested api url.

```
add_filter(
  'inc2734_github_theme_updater_request_url',
  function( $url, $user_name, $repository ) {
    if ( 'inc2734' === $user_name && 'snow-monkey' === $repository ) {
      return 'https://example.com/my-custom-updater-request-url';
    }
    return $url;
  },
  10,
  3
);
```
