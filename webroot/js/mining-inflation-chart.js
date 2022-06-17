function getReward(blockHeight) {
 if (blockHeight == 0) {
   return 400000000;
 }
 else if (blockHeight <= 5100) {
   return 1;
 }
 else if (blockHeight <= 55000) {
   return 1 + Math.floor((blockHeight - 5001) / 100);
 }
 else {
	var level = Math.floor((blockHeight - 55001) / 32);
	var reduction = Math.floor((Math.floor(Math.sqrt((8 * level) + 1)) - 1) / 2);
	while(!(withinLevelBounds(reduction, level))) {
		if(Math.floor((reduction * reduction + reduction) / 2) > level) {
            reduction--;
        }
        else {
            reduction++;
        }
	}
	return Math.max(0, 500 - reduction);
 }
}

function withinLevelBounds(reduction, level) {
    if(Math.floor((reduction * reduction + reduction) / 2) > level) {
        return false;
	}
    reduction += 1;
    if(Math.floor((reduction * reduction + reduction) / 2) <= level) {
        return false;
	}
    return true;
}

function getAverageBlockTime(blocks) {
	var numBlocks = blocks.length;
	var windowSize = 100;
	var sum = 0;
	for(i = numBlocks - windowSize; i < numBlocks; i++) {
		sum += blocks[i].block_time - blocks[i-1].block_time;
	}
	return sum / windowSize;
}

function buildChartData(blockData) {
	var chartData = [];
    var supply = 0;
	var reward = 0;
	var averageBlockTime = getAverageBlockTime(blockData);
	var blockTime = 0;
	var lastBlock = 4071017; // Last block with reward
	var skip = 100;
	var blocksPerYear = Math.floor((3600*24*365) / averageBlockTime);
	var historicalSupply = {};
	var lastYearSupply = 0;
	var lastYearBlock = 0;
	var inflationRate = 0;
	for(var i = 0; i < lastBlock; i++) {
		reward = getReward(i);
		supply += reward;
		historicalSupply[i + 1] = supply;
		if(i == 0) { // Reward for 1st block set to 0 for scale
			reward = 0;
		}
		if(i < blockData.length) {
			// Historical Data
			var b = blockData[i];
			blockTime = b.block_time;
		}
		else {
			// Future blocks
			skip = 1000;
			blockTime += averageBlockTime;
		}
		// Inflation Rate
		if(i + 1 - blocksPerYear <= 0) {
			lastYearBlock = 1;
		}
		else {
			lastYearBlock = i + 1 - blocksPerYear;
		}
		lastYearSupply = historicalSupply[lastYearBlock];
		inflationRate = ((supply - lastYearSupply) / lastYearSupply) * 100;
		if(i % skip == 0) { // Only push 1/<skip> of all blocks to optimize data loading
			chartData.push({
				date: new Date(blockTime * 1000),
				AvailableSupply: supply,
				RewardLBC: reward,
				InflationRate: inflationRate,
				BlockId: i + 1
			});
		}
	}
	return chartData;
}

function loadChartData() {
	var api_url = "https://chainquery.odysee.tv/api/sql?query=";
	var query = "SELECT height, block_time FROM block WHERE confirmations > 0 ORDER BY height";
	var url = api_url + query;
  var loadProgress = $('.mining-inflation-chart-container .load-progress');
  $.ajax({
    url: url,
    type: 'get',
    dataType: 'json',
    beforeSend: function() {
      chartLoadInProgress = true;
      loadProgress.css({ display: 'block' });
    },
		success: function(response) {
		if(response.success) {
		chartData = buildChartData(response.data);
      if(chart) {
      	chart.dataProvider = chartData;
        chart.validateNow();
        chart.validateData();
      }
  	}
  	else {
  		console.log("Could not fetch block data.");
  	}
	},
    complete: function() {
      chartLoadInProgress = false;
      loadProgress.css({ display: 'none' });
    }
});
}

var chart;
var chartData = [];
var chartLoadInProgress = false;
AmCharts.ready(function() {
chart = AmCharts.makeChart('mining-inflation-chart', {
type: 'serial',
theme: 'light',
mouseWheelZoomEnabled: true,
height: '100%',
categoryField: 'date',
synchronizeGrid: true,
dataProvider: chartData,
responsive: {
       enabled: true,
},
valueAxes: [
{
	id: 'v-supply',
	axisColor: '#1e88e5',
	axisThickness: 2,
	position: 'left',
	labelFunction: function(value) {
		return (Math.round((value / 1000000) * 1000000)/1000000).toFixed(2);
    }
},
{
	id: 'v-reward',
	axisColor: '#0b7a06',
	axisThickness: 2,
	position: 'left',
	offset: 75,
},
{
	id: 'v-inflation-rate',
	axisColor: '#ff9900',
	axisThickness: 2,
	position: 'right',
	labelFunction: function(value) {
		return value.toFixed(2);
    }
},
],
categoryAxis: {
parseDates: true,
autoGridCount: false,
minorGridEnabled: true,
minorGridAlpha: 0.04,
axisColor: '#dadada',
twoLineMode: true
},
graphs: [
{
	id: 'g-supply',
	valueAxis: 'v-supply', // we have to indicate which value axis should be used
	title: 'Available supply (millions LBC)',
	valueField: 'AvailableSupply',
	bullet: 'round',
	bulletBorderThickness: 1,
	bulletBorderAlpha: 1,
	bulletColor: '#ffffff',
	bulletSize: 5,
	useLineColorForBulletBorder: true,
	lineColor: '#1e88e5',
	hideBulletsCount: 101,
	balloonText: '[[AvailableSupply]]',
	balloonFunction: function(item, graph) {
       var result = graph.balloonText;
       return result.replace('[[AvailableSupply]]', (Math.round((item.dataContext.AvailableSupply / 1000000) * 1000000)/1000000).toFixed(2));
                    }
},
{
	id: 'g-reward',
	valueAxis: 'v-reward',
	title: 'Block Reward (LBC)',
	valueField: 'RewardLBC',
	bullet: 'round',
	bulletBorderThickness: 1,
	bulletBorderAlpha: 1,
	bulletColor: '#ffffff',
	bulletSize: 5,
	useLineColorForBulletBorder: true,
	lineColor: '#0b7a06',
	balloonText: '[[RewardLBC]] LBC<br>Block [[BlockId]]',
	hideBulletsCount: 101
},
{
	id: 'g-inflation-rate',
	valueAxis: 'v-inflation-rate',
	title: 'Annualized Inflation Rate',
	valueField: 'InflationRate',
	bullet: 'round',
	bulletBorderThickness: 1,
	bulletBorderAlpha: 1,
	bulletColor: '#ffffff',
	bulletSize: 5,
	useLineColorForBulletBorder: true,
	lineColor: '#ff9900',
	balloonText: '[[InflationRate]]%',
	hideBulletsCount: 101,
	balloonFunction: function(item, graph) {
		var result = graph.balloonText;
		return result.replace('[[InflationRate]]', item.dataContext.InflationRate.toFixed(2));
    }
}
],
chartCursor: {
cursorAlpha: 0.1,
fullWidth: true,
valueLineBalloonEnabled: true,
categoryBalloonColor: '#333333',
cursorColor: '#1e88e5'
},
chartScrollbar: {
scrollbarHeight: 36,
color: '#888888',
gridColor: '#bbbbbb'
},
legend: {
marginLeft: 110,
useGraphSettings: true,
valueText: "",
spacing: 64,

},
export: {
enabled: true,
fileName: 'lbry-supply-chart',
position: 'bottom-right',
divId: 'chart-export'
}
});
loadChartData();
});
