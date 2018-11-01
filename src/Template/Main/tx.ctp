<?php $this->assign('title', 'Transaction ' . $tx->Hash) ?>

<?php $this->start('script'); ?>
<script type="text/javascript">
    $(document).ready(function() {
        if (location.hash && (location.hash.indexOf('input-') > -1 || location.hash.indexOf('output-') > -1)) {
            $(location.hash).addClass('highlighted');
        }
    });
</script>
<?php $this->end(); ?>

<?php echo $this->element('header') ?>

<div class="tx-head">
    <h3>LBRY Transaction</h3>
    <h4><?php echo $tx->Hash ?></h4>
</div>

<div class="tx-time">
    <div class="created-time">
        <h3 title="Represents the time this transaction was created on the explorer">Time Created</h3>
        <div><?php echo $tx->Created->format('j M Y H:i:s') . ' UTC '; ?></div>
    </div>

    <div class="conf-time">
        <h3 title="The time the first confirmation of this transaction happened on the blockchain">Block Time</h3>
        <div><?php echo ($tx->TransactionTime == null || strlen(trim($tx->TransactionTime)) == 0) ? '<em>Not yet confirmed</em>' :
            \DateTime::createFromFormat('U', $tx->TransactionTime)->format('j M Y H:i:s') . ' UTC' ?>

            <?php if ($tx->TransactionTime > $tx->Created->getTimestamp()):
                $diffSeconds = $tx->TransactionTime - $tx->Created->getTimestamp();
                if ($diffSeconds <= 60) {
                    echo sprintf(' (+%s second%s)', $diffSeconds, $diffSeconds == 1 ? '' : 's');
                } else {
                    $diffMinutes = ceil($diffSeconds / 60);
                    echo sprintf(' (+%s minute%s)', $diffMinutes, $diffMinutes == 1 ? '' : 's');
                }
            ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="clear"></div>
</div>

<div class="tx-summary">
    <div class="box p25">
        <div class="title">Amount (LBC)</div>
        <div class="value"><?php echo $this->Amount->format($tx->Value) ?></div>
    </div>

    <div class="box p15">
        <div class="title">Block Height</div>
        <?php if (!isset($tx->BlockHash) || strlen(trim($tx->BlockHash)) === 0): ?>
        <div class="value" title="Unconfirmed">Unconf.</div>
        <?php else: ?>
        <div class="value" title="<?php echo $tx->BlockHash ?>"><a href="/blocks/<?php echo $block->Height ?>"><?php echo $block->Height ?></a></div>
        <?php endif; ?>
    </div>

    <div class="box p15">
        <div class="title">Confirmations</div>
        <div class="value"><?php echo $confirmations ?></div>
    </div>

    <div class="box p15">
        <div class="title">Size (bytes)</div>
        <div class="value"><?php echo number_format($tx->TransactionSize, 0, '', ',') ?></div>
    </div>

    <div class="box p15">
        <div class="title">Inputs</div>
        <div class="value"><?php echo $tx->InputCount ?></div>
    </div>

    <div class="box p15 last">
        <div class="title">Outputs</div>
        <div class="value"><?php echo $tx->OutputCount ?></div>
    </div>

    <div class="clear"></div>
</div>

<div class="tx-details">
    <h3>Details</h3>
    <div class="tx-details-layout">
        <div class="inputs">
            <div class="subtitle"><?php echo $tx->InputCount ?> input<?php echo $tx->InputCount === 1 ? '' : 's'; ?></div>

            <?php
                    $setAddressIds = [];
                    foreach ($inputs as $in):
            ?>
            <div id="input-<?php echo $in->Id ?>" class="input <?php if (isset($in['InputAddresses']) && count($in['InputAddresses']) > 0 && $in['InputAddresses'][0]->Address == $sourceAddress): ?>is-source<?php endif; ?>">
                <?php if ($in['IsCoinbase']): ?>
                    <div>Block Reward (New Coins)</div>
                <?php else: ?>
                    <?php if (strlen(trim($in->Value)) == 0): ?>
                    <div>Incomplete data</div>
                    <?php else:
                            $addr = $in['InputAddresses'][0];

                            if (!isset($setAddressIds[$addr->Address])):
                                $setAddressIds[$addr->Address] = 1; ?>
                    <a id="<?php echo $addr->Address ?>"></a>
                    <?php   endif; ?>
                    <div><span class="value"><?php echo $this->Amount->format($in['Value']) ?> LBC</span> from</div>
                    <div class="address"><a href="/address/<?php echo $addr->Address ?>"><?php echo $addr->Address ?></a>
                    (<a class="output-link" href="/tx/<?php echo $in->PrevoutHash ?>#output-<?php echo $in->PrevoutN ?>">output</a>)
                    <?php  if (isset($addr->Tag) && strlen(trim($addr->Tag)) > 0): ?>
                    <div class="tag">
                        <?php if (strlen(trim($addr->TagUrl)) > 0): ?><a href="<?php echo $addr->TagUrl ?>" target="_blank" rel="nofollow"><?php echo $addr->Tag ?></a><?php else: echo $addr->Tag; endif; ?>
                    </div>
                    <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="divider">
            <img src="/img/right-arrow.png" alt="" />
        </div>

        <div class="outputs">
            <div class="subtitle"><?php echo $tx->OutputCount ?> output<?php echo $tx->OutputCount === 1 ? '' : 's'; ?>

            <?php if ($fee > 0): ?>
            <span class="fee"><span class="label">Fee</span> <span class="value"><?php echo $this->Amount->format($fee) ?> LBC</span></span>
            <?php endif; ?>
            </div>

            <?php
            foreach ($outputs as $out): ?>
            <div id="output-<?php echo $out->Vout ?>" class="output <?php if (isset($out['OutputAddresses']) && count($out['OutputAddresses']) > 0 && $out['OutputAddresses'][0]->Address == $sourceAddress): ?>is-source<?php endif; ?>">
                <div class="labels">
                    <?php if($out->Claim && ($out->IsClaim or $out->IsSupportClaim or $out->IsUpdateClaim)): ?><a class="view-claim" href="<?php echo $out->Claim->getExplorerLink() ?>">View</a><?php endif; ?>
                    <?php if($out->IsSupportClaim): ?><div class="support">SUPPORT</div><?php endif; ?>
                    <?php if($out->IsUpdateClaim): ?><div class="update">UPDATE</div><?php endif; ?>
                    <?php if($out->IsClaim): ?><div class="claim">CLAIM</div><?php endif; ?>
                </div>

                <?php if (strlen(trim($out['Value'])) == 0): ?>
                <div>Incomplete data</div>
                <?php else:
                        $addr = $out['OutputAddresses'][0];

                        if (!isset($setAddressIds[$addr->Address])):
                                $setAddressIds[$addr->Address] = 1; ?>
                <a id="<?php echo $addr->Address ?>"></a>
                <?php   endif; ?>
                <div><span class="value"><?php echo $this->Amount->format($out['Value']) ?> LBC</span> to</div>
                <div class="address"><a href="/address/<?php echo $addr->Address ?>"><?php echo $addr->Address ?></a>

                <?php if ($out->IsSpent): ?>(<a href="/tx/<?php echo $out->SpendInput->TransactionHash ?>#input-<?php echo $out->SpendInput->Id ?>">spent</a>)<?php else: ?>(unspent)<?php endif; ?>

                <?php if (isset($addr->Tag) && strlen(trim($addr->Tag)) > 0): ?>
                <div class="tag">
                    <?php if (strlen(trim($addr->TagUrl)) > 0): ?><a href="<?php echo $addr->TagUrl ?>" target="_blank" rel="nofollow"><?php echo $addr->Tag ?></a><?php else: echo $addr->Tag; endif; ?>
                </div>
                <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>