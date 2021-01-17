let chart;
let chartData = [];
let chartLoadInProgress = false;
let minPeriod = 'hh';
let validPeriods = ['24h', '72h', '168h', '30d', '90d', '1y'];
let defaultPeriod = (validPeriods.indexOf(localStorage.getItem('chartPeriod')) > -1) ? localStorage.getItem('chartPeriod') : '24h';
let periodGridCounts = {'24h': 24, '72h': 24, '168h': 14, '30d': 30, '90d': 45, '1y': 12 };

function buildBlockSizeChartData(data) {
    return data.map(e => { return {
        'date': new Date(e.block_time * 1000),
        'difficulty': e.difficulty,
        'blockSize': e.block_size,
        'height': e.height
    }})
}

am4core.ready(function() {
    let blockSizeChart = am4core.create("block-size-chart", am4charts.XYChart);
    blockSizeChart.colors.step = 3;

    // Create axes
    let dateAxis = blockSizeChart.xAxes.push(new am4charts.DateAxis());

    let difficultyAxis = blockSizeChart.yAxes.push(new am4charts.ValueAxis());
    let difficultySeries = blockSizeChart.series.push(new am4charts.LineSeries());
    difficultySeries.dataFields.valueY = "difficulty";
    difficultySeries.dataFields.dateX = "date";
    difficultySeries.strokeWidth = 2;
    difficultySeries.yAxis = difficultyAxis;
    difficultySeries.name = "Difficulty";
    difficultySeries.tooltipText = "{valueY}";

    let sizeAxis = blockSizeChart.yAxes.push(new am4charts.ValueAxis());
    let sizeSeries = blockSizeChart.series.push(new am4charts.LineSeries());
    sizeSeries.dataFields.valueY = "blockSize";
    sizeSeries.dataFields.dateX = "date";
    sizeSeries.strokeWidth = 2;
    sizeSeries.yAxis = sizeAxis;
    sizeSeries.name = "Block Size (bytes)";
    sizeSeries.tooltipText = "{valueY} B";
    sizeAxis.renderer.opposite = true;

    blockSizeChart.legend = new am4charts.Legend();
    blockSizeChart.cursor = new am4charts.XYCursor();
    blockSizeChart.exporting.menu = new am4core.ExportMenu();

    blockSizeChart.dataSource.url = "/api/stats/blocks/24";
    blockSizeChart.events.on("beforedatavalidated", function(ev) {
        if(ev.target.data.length > 0 && ev.target.data[0].data !== undefined) {
            blockSizeChart.data = buildBlockSizeChartData(ev.target.data[0].data);
            $(".block-size-chart-box .load-progress").hide();
        }
    });
});

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

    $('a[data-period="' + defaultPeriod + '"]').addClass('active').siblings().removeClass('active');
});
