#!/bin/sh
cd /home/lbry/explorer.lbry.io/cron
php -d extension=pthreads.so blockstuff.php
