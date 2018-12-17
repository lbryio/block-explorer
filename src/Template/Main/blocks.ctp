<?php echo $this->element('header') ?>

<?php if(isset($block)): ?>
    <?php $this->start('script'); ?>
    <script type="text/javascript">
        var resizeCards = function() {
            var bSummary = $('.block-summary');
            var bTransactions = $('.block-transactions');
            if (bTransactions.outerHeight() < bSummary.outerHeight()) {
                bTransactions.outerHeight(bSummary.outerHeight());
            }
        };

        $(document).ready(function() {
            resizeCards();
        });

        window.onload = function() {
            resizeCards();
        };
    </script>
    <?php $this->end(); ?>

    <?php $this->assign('title', 'Block Height ' . $block->height) ?>

    <div class="block-head">
        <h3>LBRY Block <?php echo $block->height ?></h3>
        <h4><?php echo $block->hash ?></h4>
    </div>

    <div class="block-nav">
        <?php if ($block->height > 0): ?>
        <a class="btn btn-prev" href="/blocks/<?php echo ($block->height - 1); ?>">&laquo; Previous Block</a>
        <?php endif; ?>

        <a class="btn btn-next" href="/blocks/<?php echo ($block->height + 1); ?>">Next Block &raquo;</a>

        <div class="clear"></div>
    </div>

    <div class="block-info">
        <div class="block-summary">
            <h3>Overview</h3>

            <div class="label half-width">Block Size (bytes)</div>
            <div class="label half-width">Block Time</div>

            <div class="value half-width"><?php echo number_format($block->block_size, 0, '', ',') ?></div>
            <div class="value half-width"><?php echo \DateTime::createFromFormat('U', $block->block_time)->format('j M Y H:i:s') . ' UTC' ?></div>

            <div class="clear spacer"></div>

            <div class="label half-width">Bits</div>
            <div class="label half-width">Confirmations</div>

            <div class="value half-width"><?php echo $block->bits ?></div>
            <div class="value half-width"><?php echo number_format($block->confirmations, 0, '', ',') ?></div>

            <div class="clear spacer"></div>

            <div class="label half-width">Difficulty</div>
            <div class="label half-width">Nonce</div>

            <div class="value half-width"><?php echo $this->Amount->format($block->difficulty, '') ?></div>
            <div class="value half-width"><?php echo $block->nonce ?></div>

            <div class="clear spacer"></div>

            <div class="label">Chainwork</div> <div class="value"><?php echo $block->chainwork ?></div>

            <div class="spacer"></div>

            <div class="label">MerkleRoot</div> <div class="value"><?php echo $block->merkle_root ?></div>

            <div class="spacer"></div>

            <div class="label">NameClaimRoot</div> <div class="value"><?php echo $block->name_claim_root ?></div>

            <div class="spacer"></div>

            <div class="label">Target</div> <div class="value"><?php echo '$block->Target' ?></div>

            <div class="spacer"></div>

            <div class="label">Version</div> <div class="value"><?php echo $block->version ?></div>
        </div>

        <div class="block-transactions">
            <h3><?php echo count($blockTxs); ?> Transaction<?php echo (count($blockTxs) == 1 ? '' : 's'); ?></h3>

            <div class="transactions-list">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Hash</th>
                            <th class="w100 right">Inputs</th>
                            <th class="w100 right">Outputs</th>
                            <th class="w200 right">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($blockTxs) == 0): ?>
                        <tr>
                            <td class="nodata" colspan="4">There are no transactions to display at this time.</td>
                        </tr>
                        <?php endif; ?>

                        <?php foreach ($blockTxs as $tx): ?>
                        <tr>
                            <td class="w300"><div><a href="/tx/<?php echo $tx->hash ?>"><?php echo $tx->hash ?></a></div></td>
                            <td class="right"><?php echo $tx->input_count ?></td>
                            <td class="right"><?php echo $tx->output_count ?></td>
                            <td class="right"><div title="<?php echo $tx->value() ?> LBC"><?php echo $this->Amount->formatCurrency($tx->value()) ?> LBC</div></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="clear"></div>
<?php else:

        $this->assign('title', 'Blocks');

        $this->start('css');
        echo $this->Html->css('/amcharts/plugins/export/export.css');
        $this->end();

        $this->start('script'); ?>
<script type="text/javascript" src="/amcharts/amcharts.js"></script>
<script type="text/javascript" src="/amcharts/serial.js"></script>
<script type="text/javascript" src="/amcharts/plugins/export/export.min.js"></script>
<script type="text/javascript" src="/js/block-size-chart.js"></script>
<?php   $this->end(); ?>

<div class="block-head">
    <h3>LBRY Blocks</h3>
</div>

<div class="block-size-chart-container">
    <div class="load-progress inc"></div>
    <h3>Block Size Chart</h3>
    <div class="block-size-data-links">
        <a href="#" title="24 hours" data-period="24h">24h</a>
        <a href="#" title="72 hours" data-period="72h">72h</a>
        <a href="#" title="1 week" data-period="168h">1w</a>
        <a href="#" title="30 days" data-period="30d">30d</a>
        <a href="#" title="90 days" data-period="90d">90d</a>
        <a href="#" title="1 year" data-period="1y">1y</a>
    </div>
    <div id="block-size-chart" class="chart"></div>
    <div id="chart-export" class="btn-chart-export"></div>
</div>

<div class="all-blocks">
    <h3>All Blocks</h3>
    <div class="results-meta">
        <?php if ($numRecords > 0):
        $begin = ($currentPage - 1) * $pageLimit + 1;
        ?>
        Showing <?php echo number_format($begin, 0, '', ',') ?> - <?php echo number_format(min($numRecords, ($begin + $pageLimit) - 1), 0, '', ','); ?> of <?php echo number_format($numRecords, 0, '', ','); ?> block<?php echo $numRecords == 1 ? '' : 's' ?>
        <?php endif; ?>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th class="w100">Height</th>
                <th class="w150 left pad-left">Difficulty</th>
                <th class="w100 right">Confirmations</th>
                <th class="w100 right">TX Count</th>
                <th class="w100 right">Block Size</th>
                <th class="w100 right pad-left">Nonce</th>
                <th class="w150 left pad-left">Block Time</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($blocks as $block): ?>
            <tr>
                <td class="right"><a href="/blocks/<?php echo $block->height ?>"><?php echo $block->Height ?></a></td>
                <td class="pad-left"><?php echo number_format($block->d0ifficulty, 8, '.', '') ?></td>
                <td class="right"><?php echo number_format((($currentBlock->height - $block->height) + 1), 0, '', ',') ?></td>
                <td class="right"><?php echo count(json_decode($block->transaction_hashes)) ?></td>
                <td class="right"><?php echo round($block->block_size / 1024, 2) . 'KB' ?></td>
                <td class="right pad-left"><?php echo $block->nonce ?></td>
                <td class="pad-left"><?php echo \DateTime::createFromFormat('U', $block->block_time)->format('d M Y H:i:s') ?> UTC</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php echo $this->element('pagination') ?>
<?php endif; ?>