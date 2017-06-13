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

    <?php $this->assign('title', 'Block Height ' . $block->Height) ?>

    <div class="block-head">
        <h3>LBRY Block <?php echo $block->Height ?></h3>
        <h4><?php echo $block->Hash ?></h4>
    </div>

    <div class="block-nav">
        <?php if (strlen(trim($block->PreviousBlockHash)) > 0): ?>
        <a class="btn btn-prev" href="/blocks/<?php echo ($block->Height - 1); ?>">&laquo; Previous Block</a>
        <?php endif; ?>

        <?php if (strlen(trim($block->NextBlockHash)) > 0): ?>
        <a class="btn btn-next" href="/blocks/<?php echo ($block->Height + 1); ?>">Next Block &raquo;</a>
        <?php endif; ?>

        <div class="clear"></div>
    </div>

    <div class="block-info">
        <div class="block-summary">
            <h3>Overview</h3>

            <div class="label half-width">Block Size (bytes)</div>
            <div class="label half-width">Block Time</div>

            <div class="value half-width"><?php echo number_format($block->BlockSize, 0, '', ',') ?></div>
            <div class="value half-width"><?php echo \DateTime::createFromFormat('U', $block->BlockTime)->format('j M Y H:i:s') . ' UTC' ?></div>

            <div class="clear spacer"></div>

            <div class="label half-width">Bits</div>
            <div class="label half-width">Confirmations</div>

            <div class="value half-width"><?php echo $block->Bits ?></div>
            <div class="value half-width"><?php echo number_format($block->Confirmations, 0, '', ',') ?></div>

            <div class="clear spacer"></div>

            <div class="label half-width">Difficulty</div>
            <div class="label half-width">Nonce</div>

            <div class="value half-width"><?php echo $this->Amount->format($block->Difficulty) ?></div>
            <div class="value half-width"><?php echo $block->Nonce ?></div>

            <div class="clear spacer"></div>

            <div class="label">Chainwork</div> <div class="value"><?php echo $block->Chainwork ?></div>

            <div class="spacer"></div>

            <div class="label">MerkleRoot</div> <div class="value"><?php echo $block->MerkleRoot ?></div>

            <div class="spacer"></div>

            <div class="label">NameClaimRoot</div> <div class="value"><?php echo $block->NameClaimRoot ?></div>

            <div class="spacer"></div>

            <div class="label">Target</div> <div class="value"><?php echo $block->Target ?></div>

            <div class="spacer"></div>

            <div class="label">Version</div> <div class="value"><?php echo $block->Version ?></div>
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
                            <td class="w300"><div><a href="/tx/<?php echo $tx->Hash ?>"><?php echo $tx->Hash ?></a></div></td>
                            <td class="right"><?php echo $tx->InputCount ?></td>
                            <td class="right"><?php echo $tx->OutputCount ?></td>
                            <td class="right"><div title="<?php echo $tx->Value ?> LBC"><?php echo $this->Amount->formatCurrency($tx->Value) ?> LBC</div></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="clear"></div>
<?php else: ?>

<?php endif; ?>