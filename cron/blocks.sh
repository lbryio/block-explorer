#!/bin/sh
cd /home/lbry/explorer.lbry.io
bin/cake block parsenewblocks
rm tmp/lock/parsenewblocks
bin/cake block parsetxs
rm tmp/lock/parsetxs

