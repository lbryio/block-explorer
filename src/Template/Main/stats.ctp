<?php $this->assign('title', 'Stats &amp; Rich List') ?>

<?php $this->start('script'); ?>
<script type="text/javascript">

</script>
<?php $this->end(); ?>

<?php echo $this->element('header') ?>

<div class="stats-head">
    <h3>LBRY Stats</h3>
</div>

<div class="stats-main">

    <div class="richlist">
        <h3>LBRY Rich List (Top 500)</h3>
        <table class="table">
            <thead>
                <tr>
                    <th class="w50 right">Rank</th>
                    <th class="w300 left">Address</th>
                    <th class="w150 right">Balance (LBC)</th>
                    <th class="w150 right">Balance (USD)</th>
                    <th class="w200 left med-pad-left">First Seen</th>
                    <th class="w200 center">% Top 500</th>
                </tr>
            </thead>

            <tbody>
                <?php $rank = 0; foreach ($richList as $item): $rank++; ?>
                <tr>
                    <td class="right topvalign"><?php echo $rank ?></td>
                    <td class="topvalign"><a href="/address/<?php echo $item->Address ?>" target="_blank"><?php echo $item->Address ?></a>
                    <?php  if (isset($item->Tag) && strlen(trim($item->Tag)) > 0): ?>
                    <div class="tag">
                        <?php if (strlen(trim($item->TagUrl)) > 0): ?><a href="<?php echo $item->TagUrl ?>" target="_blank" rel="nofollow"><?php echo $tiem->Tag ?></a><?php else: echo $item->Tag; endif; ?>
                    </div>
                    <?php endif; ?></td>
                    <td class="right topvalign"><?php echo number_format($item->Balance, 8, '.', ',') ?></td>
                    <td class="right topvalign">$<?php echo number_format(bcmul($item->Balance, $rate, 8), 2, '.', ',') ?></td>
                    <td class="med-pad-left topvalign"><?php echo $item->FirstSeen->format('d M Y H:i:s') . ' UTC'; ?></td>
                    <td class="w150 center top500-percent-cell"><div class="top500-percent" style="width: <?php echo $item->MinMaxPercent ?>%"></div><div class="text"><?php echo number_format($item->Top500Percent, 5, '.', '') ?>%</div></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="clear"></div>

</div>
