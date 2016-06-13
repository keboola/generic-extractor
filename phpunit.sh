#!/usr/bin/env bash

composer selfupdate
composer install -n

./vendor/bin/phpunit "$@"