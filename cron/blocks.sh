#!/bin/sh
cd /var/www/lbry.block.ng
bin/cake block parsenewblocks
rm tmp/lock/parsenewblocks
bin/cake block parsetxs
rm tmp/lock/parsetxs

