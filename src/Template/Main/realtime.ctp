<?php $this->assign('title', 'Realtime Explorer') ?>

<?php $this->start('script'); ?>
<script type="text/javascript">
    var updateBlocksInterval = 120000;
    var updateTxInterval = 30000;

    var updateRealtimeBlocks = function() {
        var tbody = $('.realtime-blocks .table tbody');

        $.ajax({
            url: '/api/v1/realtime/blocks',
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
                                    $('<a></a>').attr({'href': ('/blocks/' + block.Height), 'target': '_blank'}).text(block.Height)
                                )
                            ).append(
                                $('<td></td>').text(blockTime.fromNow())
                            ).append(
                                $('<td></td>').attr({'class': 'right'}).text(block.TransactionCount)
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

    var updateRealtimeTransactions = function() {
        var tbody = $('.realtime-tx .table tbody');

        $.ajax({
            url: '/api/v1/realtime/tx',
            type: 'get',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var txs = response.txs;
                    for (var i = txs.length - 1; i >= 0; i--) {
                        var tx = txs[i];
                        var prevRow = tbody.find('tr[data-hash="' + tx.Hash + '"]');
                        if (prevRow.length > 0) {
                            continue;
                        }

                        var txTime = moment(tx.TxTime * 1000);
                        tbody.prepend(
                            $('<tr></tr>').attr({'data-hash': tx.Hash, 'data-time': tx.TxTime}).append(
                                $('<td></td>').attr({'class': 'w200'}).append(
                                    $('<div></div>').append(
                                        $('<a></a>').attr({'href': ('/tx/' + tx.Hash), 'target': '_blank'}).text(tx.Hash)
                                    )
                                )
                            ).append(
                                $('<td></td>').text(txTime.fromNow())
                            ).append(
                                $('<td></td>').attr({'class': 'right'}).text(tx.InputCount)
                            ).append(
                                $('<td></td>').attr({'class': 'right'}).text(tx.OutputCount)
                            ).append(
                                $('<td></td>').attr({'class': 'right'}).text(Number(tx.Value).toFixed(8) + ' LBC')
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
        setInterval(updateRealtimeBlocks, updateBlocksInterval);
        setInterval(updateRealtimeTransactions, updateTxInterval);
    });
</script>
<?php $this->end(); ?>

<?php echo $this->element('header') ?>

<div class="realtime-head">
    <h3>Realtime Explorer</h3>
</div>

<div class="realtime-main">
    <div class="realtime-blocks">
        <h3>Recent Blocks</h3>
        <table class="table">
            <thead>
                <tr>
                    <th class="left">Height</th>
                    <th class="left">Age</th>
                    <th class="right"># TXs</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($blocks as $block): ?>
                <tr data-height="<?php echo $block->height ?>" data-time="<?php echo $block->block_time ?>">
                    <td><a href="/blocks/<?php echo $block->height ?>" target="_blank"><?php echo $block->height ?></a></td>
                    <td><?php echo \Carbon\Carbon::createFromTimestamp($block->block_time)->diffForHumans(); ?></td>
                    <td class="right"><?php echo $block->transaction_count ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="realtime-tx">
        <h3>Recent Transactions</h3>
        <table class="table">
            <thead>
                <tr>
                    <th class="w200 left">Hash</th>
                    <th class="left">Time</th>
                    <th class="w100 right">Inputs</th>
                    <th class="w100 right">Outputs</th>
                    <th class="w200 right">Amount</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($txs as $tx): ?>
                <tr data-hash="<?php echo $tx->Hash ?>" data-time="<?php echo $tx->TxTime ?>">
                    <td class="w200"><div><a href="/tx/<?php echo $tx->Hash ?>" target="_blank"><?php echo $tx->Hash ?></a></div></td>
                    <td><?php echo \Carbon\Carbon::createFromTimestamp($tx->TxTime)->diffForHumans(); ?></td>
                    <td class="right"><?php echo $tx->input_count ?></td>
                    <td class="right"><?php echo $tx->output_count ?></td>
                    <td class="right"><?php echo number_format($tx->value(), 8, '.', '') ?> LBC</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="clear"></div>

</div>
