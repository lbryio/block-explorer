<?php $this->assign('title', 'Stats &amp; Rich List') ?>

<?php echo $this->element('header') ?>

<?php $this->start('script'); ?>
<script src="https://www.amcharts.com/lib/3/amcharts.js"></script>
<script src="https://www.amcharts.com/lib/3/serial.js"></script>
<script src="https://www.amcharts.com/lib/3/plugins/export/export.min.js"></script>
<script src="https://www.amcharts.com/lib/3/plugins/responsive/responsive.min.js" type="text/javascript"></script>
<script type="text/javascript" src="/js/mining-inflation-chart.js"></script>
<?php $this->end(); ?>

<?php
    $this->start('css');
    echo $this->Html->css('/css/mining-inflation-chart.css');
    echo $this->Html->css('https://www.amcharts.com/lib/3/plugins/export/export.css');
    $this->end();
 ?>
<div class="stats-head">
    <h3>LBRY Stats</h3>
</div>

<div class="stats-main">  

    <div class="mining-inflation-chart-container">
        <div class="load-progress inc"></div>
        <h3>Mining Inflation Chart</h3>
        <div id="mining-inflation-chart" class="chart"></div>
        <div id="chart-export" class="btn-chart-export"></div>
    </div>
    
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
                    <?php if(in_array($item->Address, $lbryAddresses)): ?>
                    <span class="lbry-address">
                        <img src="/img/lbry.png" height="18px" width="18px" title="Address owned by LBRY Inc."/>
                    </span>
                    <?php endif; ?>
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
