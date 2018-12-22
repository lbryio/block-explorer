#!/bin/bash

set -e

PHPBIN=php7.2

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"


if [ ! -e "config/app.php" ]; then
 cp "$DIR/config/app.default.php" "$DIR/config/app.php"
fi

if ! which $PHPBIN 2>/dev/null; then
   PHPBIN=php
fi

#Composer update
composer update

#$PHPBIN composer.phar install

$PHPBIN --server localhost:8000 --docroot "$DIR/webroot" "$DIR/webroot/index.php"