var PREMINE_REWARD = 400000000;
var INITIAL_REWARD_END_HEIGHT = 5100;
var REWARD_RAMP_END_HEIGHT = 55000;
var REWARD_RAMP_GROUP_SIZE = 100;
var REWARD_REDUCTION_GROUP_SIZE = 32;
var MAX_STANDARD_REWARD = 500;
var SECONDS_PER_YEAR = 60 * 60 * 24 * 365;
var DEFAULT_BLOCK_TIME = 150;

function getReward(blockHeight) {
 if (blockHeight == 0) {
   return PREMINE_REWARD;
 }
 else if (blockHeight <= INITIAL_REWARD_END_HEIGHT) {
   return 1;
 }
 else if (blockHeight <= REWARD_RAMP_END_HEIGHT) {
   return 1 + Math.floor((blockHeight - (INITIAL_REWARD_END_HEIGHT - REWARD_RAMP_GROUP_SIZE + 1)) / REWARD_RAMP_GROUP_SIZE);
 }
 else {
	var level = Math.floor((blockHeight - (REWARD_RAMP_END_HEIGHT + 1)) / REWARD_REDUCTION_GROUP_SIZE);
	var reduction = Math.floor((Math.floor(Math.sqrt((8 * level) + 1)) - 1) / 2);
	while(!(withinLevelBounds(reduction, level))) {
		if(Math.floor((reduction * reduction + reduction) / 2) > level) {
            reduction--;
        }
        else {
            reduction++;
        }
	}
	return Math.max(0, MAX_STANDARD_REWARD - reduction);
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
	if (blocks.length < 2) {
		return DEFAULT_BLOCK_TIME;
	}

	var sum = 0;
	var intervals = 0;
	for(var i = 1; i < blocks.length; i++) {
		var blockTime = blocks[i].block_time - blocks[i - 1].block_time;
		if (blockTime > 0) {
			sum += blockTime;
			intervals++;
		}
	}
	return intervals > 0 ? sum / intervals : DEFAULT_BLOCK_TIME;
}

function getSupply(blockHeight) {
	if (blockHeight < 0) {
		return 0;
	}

	var supply = PREMINE_REWARD;
	supply += Math.min(blockHeight, INITIAL_REWARD_END_HEIGHT);

	if (blockHeight > INITIAL_REWARD_END_HEIGHT) {
		var rampBlocks = Math.min(blockHeight, REWARD_RAMP_END_HEIGHT) - INITIAL_REWARD_END_HEIGHT;
		var completeRampGroups = Math.floor(rampBlocks / REWARD_RAMP_GROUP_SIZE);
		var remainingRampBlocks = rampBlocks % REWARD_RAMP_GROUP_SIZE;
		supply += REWARD_RAMP_GROUP_SIZE * completeRampGroups * (completeRampGroups + 3) / 2;
		supply += remainingRampBlocks * (completeRampGroups + 2);
	}

	if (blockHeight > REWARD_RAMP_END_HEIGHT) {
		var reductionBlocks = blockHeight - REWARD_RAMP_END_HEIGHT;
		var completeReductionGroups = Math.floor((Math.sqrt(1 + (reductionBlocks / 4)) - 1) / 2);
		completeReductionGroups = Math.min(completeReductionGroups, MAX_STANDARD_REWARD);
		var completedReductionBlocks = REWARD_REDUCTION_GROUP_SIZE * completeReductionGroups * (completeReductionGroups + 1) / 2;
		var remainingReductionBlocks = reductionBlocks - completedReductionBlocks;
		var completedReductionSupply = REWARD_REDUCTION_GROUP_SIZE * (
			MAX_STANDARD_REWARD * completeReductionGroups * (completeReductionGroups + 1) / 2 -
			completeReductionGroups * (completeReductionGroups - 1) * (completeReductionGroups + 1) / 3
		);
		supply += completedReductionSupply;
		supply += remainingReductionBlocks * Math.max(0, MAX_STANDARD_REWARD - completeReductionGroups);
	}

	return supply;
}

function buildChartData(data) {
	var samples = data.samples || [];
	var recentBlocks = data.recent || [];
	if (samples.length === 0) {
		return [];
	}

	var chartData = [];
	var averageBlockTime = getAverageBlockTime(recentBlocks);
	var blocksPerYear = Math.max(1, Math.floor(SECONDS_PER_YEAR / averageBlockTime));
	var currentBlock = samples[samples.length - 1];
	var currentHeight = Number(currentBlock.height);
	var currentBlockTime = Number(currentBlock.block_time);
	var lastRewardBlock = Number(data.last_reward_block);
	var futureSampleInterval = Number(data.future_sample_interval);
	var points = [];

	for (var sampleIndex = 0; sampleIndex < samples.length; sampleIndex++) {
		points.push({
			height: Number(samples[sampleIndex].height),
			blockTime: Number(samples[sampleIndex].block_time)
		});
	}

	var nextFutureHeight = Math.ceil((currentHeight + 1) / futureSampleInterval) * futureSampleInterval;
	for (var futureHeight = nextFutureHeight; futureHeight <= lastRewardBlock; futureHeight += futureSampleInterval) {
		points.push({
			height: futureHeight,
			blockTime: currentBlockTime + ((futureHeight - currentHeight) * averageBlockTime)
		});
	}
	if (points[points.length - 1].height !== lastRewardBlock) {
		points.push({
			height: lastRewardBlock,
			blockTime: currentBlockTime + ((lastRewardBlock - currentHeight) * averageBlockTime)
		});
	}

	for (var pointIndex = 0; pointIndex < points.length; pointIndex++) {
		var point = points[pointIndex];
		var supply = getSupply(point.height);
		var lastYearHeight = Math.max(0, point.height - blocksPerYear);
		var lastYearSupply = getSupply(lastYearHeight);
		var inflationRate = lastYearSupply > 0 ? ((supply - lastYearSupply) / lastYearSupply) * 100 : 0;

		chartData.push({
			date: new Date(point.blockTime * 1000),
			AvailableSupply: supply,
			RewardLBC: point.height === 0 ? 0 : getReward(point.height),
			InflationRate: inflationRate,
			BlockId: point.height
		});
	}

	return chartData;
}

function loadChartData() {
  var loadProgress = $('.mining-inflation-chart-container .load-progress');
  $.ajax({
    url: '/api/v1/charts/inflation',
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
        $('#mining-inflation-chart').text('Unable to load inflation chart data.');
      }
    },
    error: function() {
      $('#mining-inflation-chart').text('Unable to load inflation chart data.');
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
