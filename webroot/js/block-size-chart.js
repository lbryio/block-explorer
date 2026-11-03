var chart;
var chartData = [];
var chartLoadInProgress = false;
var minPeriod = 'hh';
var validPeriods = ['24h', '72h', '168h', '30d', '90d', '1y'];
var defaultPeriod = (validPeriods.indexOf(localStorage.getItem('chartPeriod')) > -1) ? localStorage.getItem('chartPeriod') : '24h';
var periodGridCounts = {'24h': 24, '72h': 24, '168h': 14, '30d': 30, '90d': 45, '1y': 12 };
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
                    return (Math.round((value / 1000) * 100)/100).toFixed(2) + ' KB';
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
            autoGridCount: false,
            minorGridEnabled: true,
            minorGridAlpha: 0.04,
            axisColor: '#dadada',
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
                bulletSize: 5,
                useLineColorForBulletBorder: true,
                lineColor: '#1e88e5',
                hideBulletsCount: 101,
                balloonText: '[[AvgBlockSize]] KB',
                switchable: false,
                balloonFunction: function(item, graph) {
                    var result = graph.balloonText;
                    return result.replace('[[AvgBlockSize]]', (Math.round((item.dataContext.AvgBlockSize / 1000) * 100)/100).toFixed(2));
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
                bulletSize: 5,
                useLineColorForBulletBorder: true,
                lineColor: '#00e676',
                balloonText: '$[[AvgUSD]]',
                balloonFunction: function(item, graph) {
                    var result = graph.balloonText;
                    if (!item.dataContext.AvgUSD) {
                        return '';
                    }
                    return result.replace('[[AvgUSD]]', item.dataContext.AvgUSD.toFixed(2));
                },
                hideBulletsCount: 101,
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
        chartScrollbar: {
            scrollbarHeight: 36,
            color: '#888888',
            gridColor: '#bbbbbb'
        },
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
                        return g.balloonText.replace('[[AvgBlockSize]]', (Math.round((item.dataContext.AvgBlockSize / 1000) * 100)/100).toFixed(2) );
                    }
                    if (g.id === 'g-price' && item.dataContext.AvgUSD) {
                        return g.balloonText.replace('[[AvgUSD]]', item.dataContext.AvgUSD.toFixed(2));
                    }
                }

                return formatted;
            }
        },
        export: {
            enabled: true,
            fileName: 'lbry-block-size-chart',
            position: 'bottom-right',
            divId: 'chart-export'
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
            chartLoadInProgress = true;
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

                // save selcted period to localStorage
                localStorage.setItem('chartPeriod', dataPeriod);

                if (chart) {
                    var isHourly = (dataPeriod.indexOf('h') > -1);
                    var gridCount = periodGridCounts[dataPeriod];
                    chart.categoryAxis.minPeriod = isHourly ? 'hh' : 'DD';
                    chart.categoryAxis.dateFormats[4].format = isHourly ? 'DD MMM' : 'DD';
                    chart.chartCursor.categoryBalloonDateFormat = isHourly ? 'D MMM HH:NN ' : 'D MMM YYYY';
                    chart.categoryAxis.gridCount = gridCount;
                    chart.chartScrollbar.gridCount = gridCount;
                    chart.dataProvider = chartData;
                    chart.validateNow();
                    chart.validateData();
                }
            }
        },
        complete: function() {
            chartLoadInProgress = false;
            loadProgress.css({ display: 'none' });
        }
    });
};

$(document).ready(function() {
    $('.block-size-data-links a').on('click', function(evt) {
        evt.preventDefault();
        if (chartLoadInProgress) {
            return;
        }

        var link = $(this);
        if (link.hasClass('active')) {
            return;
        }

        link.addClass('active').siblings().removeClass('active');
        var period = link.attr('data-period');
        loadChartData(period);
    });

    $('a[data-period="' + defaultPeriod + '"]').addClass('active').siblings().removeClass('active');
});