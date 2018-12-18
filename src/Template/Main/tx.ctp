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
    <h4><?php echo $tx->hash ?></h4>
</div>

<div class="tx-time">
    <div class="created-time">
        <h3 title="Represents the time this transaction was created on the explorer">Time Created</h3>
        <div><?php echo $tx->created_at->format('j M Y H:i:s') . ' UTC '; ?></div>
    </div>

    <div class="conf-time">
        <h3 title="The time the first confirmation of this transaction happened on the blockchain">Block Time</h3>
        <div><?php echo ($tx->transaction_time == null || strlen(trim($tx->transaction_time)) == 0) ? '<em>Not yet confirmed</em>' :
            \DateTime::createFromFormat('U', $tx->transaction_time)->format('j M Y H:i:s') . ' UTC' ?>

            <?php if ($tx->transaction_time > $tx->created_at->getTimestamp()):
                $diffSeconds = $tx->transaction_time - $tx->created_at->getTimestamp();
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
        <div class="value"><?php echo $this->Amount->format($tx->value()) ?></div>
    </div>

    <div class="box p15">
        <div class="title">Block Height</div>
        <?php if (!isset($tx->block_hash_id) || strlen(trim($tx->block_hash_id)) === 0): ?>
        <div class="value" title="Unconfirmed">Unconf.</div>
        <?php else: ?>
        <div class="value" title="<?php echo $tx->block_hash_id ?>"><a href="/blocks/<?php echo $block->height ?>"><?php echo $block->height ?></a></div>
        <?php endif; ?>
    </div>

    <div class="box p15">
        <div class="title">Confirmations</div>
        <div class="value"><?php echo $confirmations ?></div>
    </div>

    <div class="box p15">
        <div class="title">Size (bytes)</div>
        <div class="value"><?php echo number_format($tx->transaction_size, 0, '', ',') ?></div>
    </div>

    <div class="box p15">
        <div class="title">Inputs</div>
        <div class="value"><?php echo $tx->input_count ?></div>
    </div>

    <div class="box p15 last">
        <div class="title">Outputs</div>
        <div class="value"><?php echo $tx->output_count ?></div>
    </div>

    <div class="clear"></div>
</div>

<div class="tx-details">
    <h3>Details</h3>
    <div class="tx-details-layout">
        <div class="inputs">
            <div class="subtitle"><?php echo $tx->input_count ?> input<?php echo $tx->input_count === 1 ? '' : 's'; ?></div>

            <?php
                    $setAddressIds = [];
                    foreach ($inputs as $in):
            ?>
            <div id="input-<?php echo $in->id ?>" class="input <?php if (isset($in->input_addresses) && count($in->input_addresses) > 0 && $in->input_addresses[0]->address == $sourceAddress): ?>is-source<?php endif; ?>">
                <?php if ($in->is_coinbase): ?>
                    <div>Block Reward (New Coins)</div>
                <?php else: ?>
                    <?php if (strlen(trim($in->value)) == 0): ?>
                    <div>Incomplete data</div>
                    <?php else:
                            $addr = $in->input_addresses[0];

                            if (!isset($setAddressIds[$addr->address])):
                                $setAddressIds[$addr->address] = 1; ?>
                    <a id="<?php echo $addr->address ?>"></a>
                    <?php   endif; ?>
                    <div><span class="value"><?php echo $this->Amount->format($in->value) ?> LBC</span> from</div>
                    <div class="address"><a href="/address/<?php echo $addr->address ?>"><?php echo $addr->address ?></a>
                    (<a class="output-link" href="/tx/<?php echo $in->prevout_hash ?>#output-<?php echo $in->prevout_n ?>">output</a>)
                    
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
            <div class="subtitle"><?php echo $tx->output_count ?> output<?php echo $tx->output_count === 1 ? '' : 's'; ?>

            <?php if ($fee > 0): ?>
            <span class="fee"><span class="label">Fee</span> <span class="value"><?php echo $this->Amount->format($fee) ?> LBC</span></span>
            <?php endif; ?>
            </div>

            <?php
            foreach ($outputs as $out): ?>
            <div id="output-<?php echo $out->vout ?>" class="output <?php if (isset($out->output_addresses) && count($out->output_addresses) > 0 && $out->output_addresses[0]->address == $sourceAddress): ?>is-source<?php endif; ?>">
                <div class="labels">
                    <?php if($out->Claim && ($out->IsClaim or $out->IsSupportClaim or $out->IsUpdateClaim)): ?><a class="view-claim" href="<?php echo $out->Claim->getExplorerLink() ?>">View</a><?php endif; ?>
                    <?php if($out->IsSupportClaim): ?><div class="support">SUPPORT</div><?php endif; ?>
                    <?php if($out->IsUpdateClaim): ?><div class="update">UPDATE</div><?php endif; ?>
                    <?php if($out->IsClaim): ?><div class="claim">CLAIM</div><?php endif; ?>
                </div>

                <?php if (strlen(trim($out->value)) == 0): ?>
                <div>Incomplete data</div>
                <?php else:
                        $addr = $out->output_addresses[0];

                        if (!isset($setAddressIds[$addr->address])):
                                $setAddressIds[$addr->address] = 1; ?>
                <a id="<?php echo $addr->address ?>"></a>
                <?php   endif; ?>
                <div><span class="value"><?php echo $this->Amount->format($out->value) ?> LBC</span> to</div>
                <div class="address"><a href="/address/<?php echo $addr->address ?>"><?php echo $addr->address ?></a>

                <?php if ($out->is_spent): ?>(<a href="/tx/<?php echo $out->spend_input->transaction_hash ?>#input-<?php echo $out->spend_input->id ?>">spent</a>)<?php else: ?>(unspent)<?php endif; ?>

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