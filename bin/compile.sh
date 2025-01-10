#!/bin/sh

cd `echo $( dirname -- "$0"; )`

PHP_BIN="${1:-php}"
PHP_BIN_PATH=$(which "$PHP_BIN")
[ -z "$PHP_BIN_PATH" ] && echo "$PHP_BIN not found, if installed please specify valid name as first argument" && exit 1

$PHP_BIN -d phar.readonly=0 ./../app/index.php compile-phar
