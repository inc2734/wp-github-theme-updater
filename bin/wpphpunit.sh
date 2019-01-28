#!/usr/bin/env bash

set -e;

WP_TESTS_DIR=${WP_TESTS_DIR-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-/tmp/wordpress/}

themedir=$(pwd)

cd $themedir

if [ -e $themedir/bin/install-wp-tests.sh ]; then
  echo 'DROP DATABASE IF EXISTS wordpress_test;' | mysql -u root

  if [ -e ${WP_CORE_DIR} ]; then
    rm -fr ${WP_CORE_DIR}
  fi

  if [ -e ${WP_TESTS_DIR} ]; then
    rm -fr ${WP_TESTS_DIR}
  fi

  bash "$themedir/bin/install-wp-tests.sh" wordpress_test root '' localhost latest;
  phpunit --configuration= ${themedir}/phpunit.xml.dist
else
  echo "$themedir/bin/install-wp-tests.sh not found."
fi;
