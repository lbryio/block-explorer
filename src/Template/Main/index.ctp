<?php $this->assign('title', 'Home') ?>

<?php $this->start('script'); ?>
<script type="text/javascript">
    var updateInterval = 120000;

    var updateStatus = function() {
        $.ajax({
            url: '/api/v1/status',
            type: 'get',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var status = response.status;
                    var stats = $('.stats');
                    stats.find('.box:eq(0) > .value').text(status.height);
                    stats.find('.box:eq(1) > .value').text(status.difficulty);
                    stats.find('.box:eq(2) > .value').text(status.hashrate);
                    stats.find('.box:eq(3) > .value').text(status.price);
                }
            }
        });
    };

    var updateRecentBlocks = function() {
        var tbody = $('.recent-blocks .table tbody');

        $.ajax({
            url: '/api/v1/recentblocks',
            type: 'get',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var blocks = response.blocks;
                    for (var i = blocks.length - 1; i >= 0; i--) {
                        var block = blocks[i];
                        var prevRow = tbody.find('tr[data-height="' + block.Height + '"]');
                        if (prevRow.length > 0) {
                            continue;
                        }

                        var blockTime = moment(block.BlockTime * 1000);
                        tbody.prepend(
                            $('<tr></tr>').attr({'data-height': block.Height, 'data-time': block.BlockTime}).append(
                                $('<td></td>').append(
                                    $('<a></a>').attr({'href': '/blocks/' + block.Height}).text(block.Height)
                                )
                            ).append(
                                $('<td></td>').text(blockTime.fromNow())
                            ).append(
                                $('<td></td>').attr({'class': 'right'}).text((block.BlockSize / 1024).toFixed(2) + 'KB')
                            ).append(
                                $('<td></td>').attr({'class': 'right'}).text(block.TransactionCount)
                            ).append(
                                $('<td></td>').attr({'class': 'right'}).text(block.Difficulty)
                            ).append(
                                $('<td></td>').attr({'class': 'last-cell'}).text(blockTime.utc().format('D MMM YYYY HH:mm:ss') + ' UTC')
                            )
                        );

                        // Remove the last row
                        tbody.find('tr:last').remove();
                    }
                }
            },
            complete: function() {
                tbody.find('tr').each(function() {
                    var row = $(this);
                    var blockTime = moment(row.attr('data-time') * 1000);
                    row.find('td:eq(1)').text(blockTime.fromNow());
                });
            }
        });
    };

    $(document).ready(function() {
        setInterval(updateStatus, updateInterval);
        setInterval(updateRecentBlocks, updateInterval);
    });
</script>
<?php $this->end(); ?>

<div class="home-container">
    <div class="home-container-cell">
        <div class="main">
            <div class="title">LBRY Block Explorer</div>
            <form method="get" action="/find">
                <input class="search-input" name="q" type="text" placeholder="Enter a block height or hash, transaction hash or address" />
                <div class="ctls"><button class="btn btn-search">Search</button> <a href="/realtime">Realtime</a></div>
            </form>
        </div>

        <div class="stats">
            <div class="box">
                <div class="title">Block Height</div>
                <div class="value"><?php echo $recentBlocks[0]->Height ?></div>
            </div>

            <div class="box">
                <div class="title">Difficulty</div>
                <div class="value" title="<?php echo $recentBlocks[0]->Difficulty ?>"><?php echo number_format($recentBlocks[0]->Difficulty, 2, '.', '') ?></div>
            </div>

            <div class="box">
                <div class="title">Network</div>
                <div class="value"><?php echo $hashRate ?></div>
            </div>

            <div class="box last">
                <div class="title">Price</div>
                <div class="value"><?php echo $lbcUsdPrice ?></div>
            </div>

            <div class="clear"></div>
        </div>

        <div class="recent-blocks">
            <h3>Recent Blocks</h3>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th class="left w125">Height</th>
                        <th class="left w125">Age</th>
                        <th class="right w150">Block Size</th>
                        <th class="right w150">Transactions</th>
                        <th class="right w150">Difficulty</th>
                        <th class="left w250 last-cell">Timestamp</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($recentBlocks as $block): ?>
                    <tr data-height="<?php echo $block->Height ?>" data-time="<?php echo $block->BlockTime ?>">
                        <td><a href="/blocks/<?php echo $block->Height ?>"><?php echo $block->Height ?></a></td>
                        <td><?php echo \Carbon\Carbon::createFromTimestamp($block->BlockTime)->diffForHumans(); ?></td>
                        <td class="right"><?php echo round($block->BlockSize / 1024, 2) . 'KB' ?></td>
                        <td class="right"><?php echo $block->TransactionCount ?></td>
                        <td class="right"><?php echo number_format($block->Difficulty, 2, '.', '') ?></td>
                        <td class="last-cell"><?php echo DateTime::createFromFormat('U', $block->BlockTime)->format('j M Y H:i:s') . ' UTC' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>