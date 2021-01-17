function getReward(blockHeight) {
    if (blockHeight === 0) {
        return 400000000;
    }
    else if (blockHeight <= 5100) {
        return 1;
    }
    else if (blockHeight <= 55000) {
        return 1 + Math.floor((blockHeight - 5001) / 100);
    }
    else {
        const level = Math.floor((blockHeight - 55001) / 32);
        let reduction = Math.floor((Math.floor(Math.sqrt((8 * level) + 1)) - 1) / 2);
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
    return Math.floor((reduction * reduction + reduction) / 2) > level;
}

function getAverageBlockTime(blocks) {
    const windowSize = 100;
    let sum = 0;
    let height = 0;
    for(let i = blocks.length - windowSize; i < blocks.length; i++) {
        sum += blocks[i].block_time - blocks[i-1].block_time;
        height += blocks[i].height - blocks[i-1].height + 1;
    }
    return sum / height;
}

function getBlockTime(currentHeight, blocks, averageBlockTime) {
    if(currentHeight < blocks.length) {
        return blocks[currentHeight].block_time;
    } else {
        return (blocks[blocks.length - 1].block_time + ((blocks.length - currentHeight + 1) * averageBlockTime));
    }
}

function buildChartData(blockData) {
    let chartData = [];
    let supply = 0;
    let averageBlockTime = getAverageBlockTime(blockData);
    let blocksPerYear = Math.floor((3600*24*365) / averageBlockTime);
    let lastBlockHeight = 4071017; // Last block with reward
    let blockTime = 0;

    // Getting historical data
    for(let i = 0; i < blockData.length; i++) {
        let newReward = getReward(blockData[i].height);
        if(i === 0) {
            supply += newReward;
            newReward = 0; // Reward for 1st block set to 0 for scale
        } else if(i === 1) {
            supply += blockData[i].height - blockData[i - 1].height - 1;
        } else {
            let oldReward = getReward(blockData[i - 1].height);
            supply += (blockData[i].height - blockData[i-1].height) * ((newReward + oldReward) / 2);
        }

        // Get blockTime from data or from prediction
        blockTime = getBlockTime(i, blockData, averageBlockTime);

        // Inflation Rate
        let inflationRate = 0;
        if(i !== 0) {
            inflationRate = (newReward * blocksPerYear * 100) / supply;
        }

        chartData.push({
            date: new Date(blockTime * 1000),
            AvailableSupply: supply / 1000000,
            RewardLBC: newReward,
            InflationRate: inflationRate,
            BlockId: blockData[i].height
        });
    }

    // Getting historical data
    for(let j = blockData[blockData.length - 1].height; j < lastBlockHeight; j++) {
        let skip = 1000;
        let reward = getReward(j);
        supply += reward;

        // Get blockTime from data or from prediction
        blockTime += averageBlockTime;

        // Inflation Rate
        let newInflationRate = (reward * blocksPerYear * 100) / supply;

        if(j % skip === 0) {
            chartData.push({
                date: new Date(blockTime * 1000),
                AvailableSupply: supply / 1000000,
                RewardLBC: reward,
                InflationRate: newInflationRate,
                BlockId: j
            });
        }
    }

    return chartData;
}

am4core.ready(function() {
    let chart = am4core.create("mining-inflation-chart", am4charts.XYChart);
    chart.colors.step = 3;

    // Create axes
    let dateAxis = chart.xAxes.push(new am4charts.DateAxis());

    let supplyValueAxis = chart.yAxes.push(new am4charts.ValueAxis());
    let supplySeries = chart.series.push(new am4charts.LineSeries());
    supplySeries.dataFields.valueY = "AvailableSupply";
    supplySeries.dataFields.dateX = "date";
    supplySeries.strokeWidth = 2;
    supplySeries.yAxis = supplyValueAxis;
    supplySeries.name = "Available Supply (millions LBC)";
    supplySeries.tooltipText = "{valueY} LBC";

    let rewardSeries = chart.series.push(new am4charts.LineSeries());
    rewardSeries.dataFields.valueY = "RewardLBC";
    rewardSeries.dataFields.dateX = "date";
    rewardSeries.strokeWidth = 2;
    rewardSeries.yAxis = supplyValueAxis;
    rewardSeries.name = "Block Reward (LBC)";
    rewardSeries.tooltipText = "{valueY} LBC";

    let inflationValueAxis = chart.yAxes.push(new am4charts.ValueAxis());
    let inflationSeries = chart.series.push(new am4charts.LineSeries());
    inflationSeries.dataFields.valueY = "InflationRate";
    inflationSeries.dataFields.dateX = "date";
    inflationSeries.strokeWidth = 2;
    inflationSeries.yAxis = inflationValueAxis;
    inflationSeries.name = "Annualized Inflation Rate";
    inflationSeries.tooltipText = "{valueY}%";
    inflationValueAxis.renderer.opposite = true;

    chart.legend = new am4charts.Legend();
    chart.cursor = new am4charts.XYCursor();
    chart.exporting.menu = new am4core.ExportMenu();


    chart.dataSource.url = "/api/stats/mining";
    chart.events.on("beforedatavalidated", function(ev) {
        if(ev.target.data.length > 0 && ev.target.data[0].data !== undefined) {
            chart.data = buildChartData(ev.target.data[0].data);
            $(".mining-inflation-chart-box .load-progress").hide();
        }
    });
});
