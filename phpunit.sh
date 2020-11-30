#!/usr/bin/env bash
set -e
php --version

echo "Starting tests" >&1
curl -L https://codeclimate.com/downloads/test-reporter/test-reporter-latest-linux-amd64 > ./cc-test-reporter
chmod +x ./cc-test-reporter
export XDEBUG_MODE=coverage
./cc-test-reporter before-build
./vendor/bin/phpcs --standard=psr2 --ignore="vendor,.tmp" -n .
./vendor/bin/phpstan analyse --level=4 src
./vendor/bin/phpunit --coverage-clover ./build/logs/clover.xml
./cc-test-reporter after-build --exit-code 0 --debug

echo "Tests Finished" >&1
