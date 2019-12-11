function buildChartData(claimsData) {
	var chartData = [];
    var lastDate = 0;
    var nbClaimsDay = 0;
    var nbClaimsTotal = 0;
    var bidClaimsDay = 0;
    var bidClaimsTotal = 0;
    var nbChannelsDay = 0;
    var nbChannelsTotal = 0;
	for(var i = 0; i < claimsData.length; i++) {
        if(claimsData[i].transaction_time == 0) {
            continue;
        }
        var transactionDate = new Date(claimsData[i].transaction_time * 1000);
        transactionDate.setHours(0,0,0,0)
        if(lastDate == 0) {
            lastDate = transactionDate;
        }
        if(transactionDate.toString() != lastDate.toString()) {
            nbClaimsTotal += nbClaimsDay;
            bidClaimsTotal += bidClaimsDay;
            var dateData = {
				date: lastDate,
				NumberClaims: nbClaimsTotal,
                BidsClaims: bidClaimsTotal,
			};
            chartData.push(dateData);
            nbClaimsDay = 0;
            bidClaimsDay = 0;
            lastDate = transactionDate;
        }
        if(claimsData[i].claim_type == 1) {
            nbClaimsDay += 1;
            bidClaimsDay += claimsData[i].effective_amount/100000000;
        }
	}
	return chartData;
}

function loadChartData() {
    var api_url = "https://chainquery.lbry.com/api/sql?query=";
	var query = "SELECT c1.claim_type, c1.bid_state, c1.effective_amount, c1.transaction_time, o.transaction_time AS 'spent_time' FROM claim c1 LEFT JOIN (SELECT output.claim_id, tx.transaction_time FROM output INNER JOIN input ON input.prevout_hash = output.transaction_hash AND input.prevout_n = output.vout INNER JOIN transaction tx ON tx.id = input.transaction_id) o ON o.claim_id=c1.claim_id AND c1.bid_state='Spent' ORDER BY c1.transaction_time ASC";
    var url = api_url + query;
  var loadProgress = $('.bids-chart-container .load-progress');
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
chart = AmCharts.makeChart('bids-chart', {
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
	id: 'v-number-claims',
	axisColor: '#1e88e5',
	axisThickness: 2,
	position: 'left',
},
{
	id: 'v-bids-claims',
	axisColor: '#0b7a06',
	axisThickness: 2,
	position: 'left',
	offset: 75,
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
	id: 'g-number-claims',
	valueAxis: 'v-number-claims',
	title: 'Number of claims',
	valueField: 'NumberClaims',
	bullet: 'round',
	bulletBorderThickness: 1,
	bulletBorderAlpha: 1,
	bulletColor: '#ffffff',
	bulletSize: 5,
	useLineColorForBulletBorder: true,
	lineColor: '#1e88e5',
	hideBulletsCount: 101,
	balloonText: '[[NumberClaims]]',
},
{
	id: 'g-bids-claims',
	valueAxis: 'v-bids-claims',
	title: 'Bids for claims (LBC)',
	valueField: 'BidsClaims',
	bullet: 'round',
	bulletBorderThickness: 1,
	bulletBorderAlpha: 1,
	bulletColor: '#ffffff',
	bulletSize: 5,
	useLineColorForBulletBorder: true,
	lineColor: '#0b7a06',
	balloonText: '[[BidsClaims]] LBC',
	hideBulletsCount: 101
},
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
fileName: 'lbry-bids-chart',
position: 'bottom-right',
divId: 'chart-export'
}
});
loadChartData();
});
