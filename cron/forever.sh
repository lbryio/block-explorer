#!/bin/sh
pkill -f forevermempool
rm -f /home/lbry/explorer.lbry.io/tmp/lock/forevermempool 2>/dev/null
cd /home/lbry/explorer.lbry.io
bin/cake block forevermempool &
