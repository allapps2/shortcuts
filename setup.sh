#!/bin/sh

# cd to current file directory
cd `echo $( dirname -- "$0"; )`

LIB_DIR=$(pwd)

PHP_BIN="${1:-php}"
PHP_BIN_PATH=$(which "$PHP_BIN")
[ -z "$PHP_BIN_PATH" ] && echo "$PHP_BIN not found, please specify valid name as first argument" && exit 1

DST_FILE=/usr/local/bin/short

printf "#!%s\n<?php\nrequire('%s/app/index.php');\n" "$PHP_BIN_PATH" "$LIB_DIR" > $DST_FILE || exit 1
chmod +x $DST_FILE

echo "Now you can use 'short' in directory with shortcuts.php"
