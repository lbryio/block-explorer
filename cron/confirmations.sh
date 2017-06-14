#!/bin/sh
cd /var/www/lbry.block.ng/cron
php -d extension=pthreads.so blockstuff.php
