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

            <div class="value half-width"><?php echo $this->Amount->format($block->Difficulty, '') ?></div>
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
<?php else:

        $this->assign('title', 'Blocks');

        $this->start('script'); ?>
<script type="text/javascript" src="/amcharts/amcharts.js"></script>
<script type="text/javascript" src="/amcharts/serial.js"></script>
<script type="text/javascript">
    var chart;
    var chartData = [];
    var minPeriod = 'hh';
    var defaultPeriod = '24h';
    AmCharts.ready(function() {
        chart = AmCharts.makeChart('block-size-chart', {
            type: 'serial',
            theme: 'light',
            mouseWheelZoomEnabled: true,
            categoryField: 'date',
            synchronizeGrid: true,
            dataProvider: chartData,
            valueAxes: [
                {
                    id: 'v-block-size',
                    axisColor: '#1e88e5',
                    axisThickness: 2,
                    labelFunction: function(value) {
                        return (Math.round((value / 1000) * 100)/100) + ' KB';
                    }
                },
                {
                    id: 'v-price',
                    axisColor: '#00e676',
                    offset: 75,
                    gridAlpha: 0,
                    axisThickness: 2,
                    labelFunction: function(value) {
                        return '$' + value.toFixed(2);
                    }
                }
            ],
            categoryAxis: {
                parseDates: true,
                minPeriod: minPeriod, // DD for daily
                minorGridEnabled: true,
                axisColor: '#DADADA',
                twoLineMode: true,
                dateFormats: [{
                    period: 'fff',
                    format: 'JJ:NN:SS'
                }, {
                    period: 'ss',
                    format: 'JJ:NN:SS'
                }, {
                    period: 'mm',
                    format: 'JJ:NN'
                }, {
                    period: 'hh',
                    format: 'JJ:NN'
                }, {
                    period: 'DD',
                    format: 'DD'
                }, {
                    period: 'WW',
                    format: 'DD MMM'
                }, {
                    period: 'MM',
                    format: 'MMM'
                }, {
                    period: 'YYYY',
                    format: 'YYYY'
                }]
            },
            graphs: [
                {
                    id: 'g-block-size',
                    valueAxis: 'v-block-size', // we have to indicate which value axis should be used
                    title: 'Avg Block Size',
                    valueField: 'AvgBlockSize',
                    bullet: 'round',
                    bulletBorderThickness: 1,
                    bulletBorderAlpha: 1,
                    bulletColor: '#ffffff',
                    bulletSize: 6,
                    useLineColorForBulletBorder: true,
                    lineColor: '#1e88e5',
                    hideBulletsCount: 30,
                    balloonText: '[[AvgBlockSize]] KB',
                    switchable: false,
                    balloonFunction: function(item, graph) {
                        var result = graph.balloonText;
                        return result.replace('[[AvgBlockSize]]', Math.round((item.dataContext.AvgBlockSize / 1000) * 100)/100);
                    }
                },
                {
                    id: 'g-price',
                    valueAxis: 'v-price',
                    title: 'Average Price',
                    valueField: 'AvgUSD',
                    bullet: 'round',
                    bulletBorderThickness: 1,
                    bulletBorderAlpha: 1,
                    bulletColor: '#ffffff',
                    bulletSize: 6,
                    useLineColorForBulletBorder: true,
                    lineColor: '#00e676',
                    balloonText: '$[[AvgUSD]]',
                    hideBulletsCount: 30,
                    labelFunction: function(value) {
                        return '$' + value;
                    },
                }
            ],
            chartCursor: {
                cursorAlpha: 0.1,
                fullWidth: true,
                valueLineBalloonEnabled: true,
                categoryBalloonColor: '#333333',
                cursorColor: '#1e88e5',
                categoryBalloonDateFormat: minPeriod === 'hh' ? 'D MMM HH:NN ' : 'D MMM'
            },
            chartScrollbar: {},
            legend: {
                marginLeft: 110,
                useGraphSettings: true,
                valueAlign: 'right',
                valueWidth: 60,
                spacing: 64,
                valueFunction: function(item, formatted) {
                    if (item.dataContext) {
                        var g = item.graph;
                        if (g.id === 'g-block-size' && item.dataContext.AvgBlockSize > 0) {
                            return g.balloonText.replace('[[AvgBlockSize]]', Math.round((item.dataContext.AvgBlockSize / 1000) * 100)/100);
                        }
                        if (g.id === 'g-price' && item.dataContext.AvgUSD) {
                            return g.balloonText.replace('[[AvgUSD]]', item.dataContext.AvgUSD);
                        }
                    }

                    return formatted;
                }
            }
        });

        loadChartData(defaultPeriod);
    });

    var loadChartData = function(dataPeriod) {
        var loadProgress = $('.block-size-chart-container .load-progress');
        // clear previous chart data
        $.ajax({
            url: '/api/v1/charts/blocksize/' + dataPeriod,
            type: 'get',
            dataType: 'json',
            beforeSend: function() {
                loadProgress.css({ display: 'block' });
            },
            success: function(response) {
                if (response.success) {
                    chartData = [];
                    var data = response.data;
                    for (var period in data) {
                        if (data.hasOwnProperty(period)) {
                            chartData.push({
                                date: Date.parse(period),
                                AvgBlockSize: data[period].AvgBlockSize,
                                AvgUSD: data[period].AvgUSD
                            });
                        }
                    }

                    //var dataSet = chart.mainDataSet;
                    if (chart) {
                        var isHourly = (dataPeriod.indexOf('h') > -1);
                        chart.categoryAxis.minPeriod = isHourly ? 'hh' : 'DD';
                        chart.categoryAxis.dateFormats[4].format = isHourly ? 'DD MMM' : 'DD';
                        chart.dataProvider = chartData;
                        chart.validateNow();
                        chart.validateData();
                    }
                }
            },
            complete: function() {
                loadProgress.css({ display: 'none' });
            }
        });
    };

    $(document).ready(function() {
        $('.block-size-data-links a').on('click', function(evt) {
            evt.preventDefault();
            var link = $(this);
            if (link.hasClass('active')) {
                return;
            }

            link.addClass('active').siblings().removeClass('active');
            var period = link.attr('data-period');
            loadChartData(period);
        });
    });
</script>
<?php   $this->end(); ?>

<div class="block-head">
    <h3>LBRY Blocks</h3>
</div>

<div class="block-size-chart-container">
    <div class="load-progress inc"></div>
    <h3>Block Size Chart</h3>
    <div class="block-size-data-links">
        <a href="#" title="24 hours" data-period="24h" class="active">24h</a>
        <a href="#" title="72 hours" data-period="72h">72h</a>
        <a href="#" title="1 week" data-period="168h">1w</a>
        <a href="#" title="30 days" data-period="30d">30d</a>
        <a href="#" title="90 days" data-period="90d">90d</a>
        <a href="#" title="1 year" data-period="1y">1y</a>
    </div>
    <div id="block-size-chart" class="chart"></div>
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
                <td class="right"><a href="/blocks/<?php echo $block->Height ?>"><?php echo $block->Height ?></a></td>
                <td class="pad-left"><?php echo number_format($block->Difficulty, 8, '.', '') ?></td>
                <td class="right"><?php echo number_format($block->Confirmations, 0, '', ',') ?></td>
                <td class="right"><?php echo count(json_decode($block->TransactionHashes)) ?></td>
                <td class="right"><?php echo round($block->BlockSize / 1024, 2) . 'KB' ?></td>
                <td class="right pad-left"><?php echo $block->Nonce ?></td>
                <td class="pad-left"><?php echo \DateTime::createFromFormat('U', $block->BlockTime)->format('j M Y H:i:s') ?> UTC</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php echo $this->element('pagination') ?>
<?php endif; ?>