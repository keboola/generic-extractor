#!/usr/bin/env bash
set -e 

php --version

echo "Starting tests" >&1
./vendor/bin/phpcs --standard=psr2 --ignore=vendor -n .
# ./vendor/bin/phpstan analyse /code/src --level=4

./vendor/bin/phpunit --coverage-clover build/logs/clover.xml --whitelist=src/

CODECLIMATE_REPO_TOKEN=5a55b7a7af3a005157057ec58e0855756b866d86e77ca3a86bbf464938daf8c9
./vendor/bin/test-reporter

echo "Tests Finished" >&1