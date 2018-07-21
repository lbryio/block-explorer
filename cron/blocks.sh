#!/bin/sh
cd /home/lbry/explorer.lbry.io
bin/cake block parsenewblocks
rm tmp/lock/parsenewblocks 2>/dev/null
bin/cake block parsetxs
rm tmp/lock/parsetxs 2>/dev/null

