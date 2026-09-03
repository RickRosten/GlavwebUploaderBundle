#!/bin/bash
set -x
set -e

composer install -n --working-dir=/usr/src/bundle \
&& php /usr/src/bundle/vendor/bin/phpunit --configuration /usr/src/bundle/phpunit.xml.dist \
|| echo 'FAILED'
