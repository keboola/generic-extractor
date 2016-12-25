#!/bin/bash
set -e 

pecl config-set php_ini /usr/local/etc/php.ini \
	&& yes | pecl install xdebug \
	&& echo "zend_extension=$(find /usr/local/lib/php/extensions/ -name xdebug.so)" > /usr/local/etc/php/conf.d/xdebug.ini \

echo "Starting tests" >&1
php --version \
	&& composer --version

php /home/vendor/bin/phpunit --coverage-clover build/logs/clover.xml --whitelist=src/
CODECLIMATE_REPO_TOKEN=5a55b7a7af3a005157057ec58e0855756b866d86e77ca3a86bbf464938daf8c9
/home/vendor/bin/test-reporter

echo "Tests Finished" >&1
