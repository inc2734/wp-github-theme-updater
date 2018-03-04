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
$updater = new Inc2734\WP_GitHub_Theme_Updater\GitHub_Theme_Updater( get_template(), 'user-name', 'repository' );
```
