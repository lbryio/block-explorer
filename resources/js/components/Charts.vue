<style lang="scss">
  .chart-container {
    position: relative;
    margin: auto;
    height: 50vh;
    width: 75vw;
  }
</style>

<template>
    <div class="row">
      <div class="col-lg-12">
        <div class="mb-3 card">
              <div class="card-header-tab card-header">
                  <div class="card-header-title">
                      <i class="header-icon lnr-rocket icon-gradient bg-tempting-azure"> </i>
                      <div id="chart_title">{{ api[selected_tab].name }} [latest 12 hours]</div>
                  </div>
                  <div class="btn-actions-pane-right">
                      <div class="nav">
                        <button id="0_button" @click="panelClick(0)" :class="{active: selected_tab === 0}" class="ml-1 border-0 btn btn-pill btn-wide btn-transition btn-outline-alternate">Difficulty</button>
                        <button id="1_button" @click="panelClick(1)" :class="{active: selected_tab === 1}" class="ml-1 border-0 btn btn-pill btn-wide btn-transition btn-outline-alternate">Block Size</button>
                      </div>
                  </div>
              </div>
              <div class="tab-content">
                  <div class="tab-pane fade active show">
                      <div class="chart-container">
                        <canvas id="chart_body"></canvas>
                      </div>
                  </div>
              </div>
          </div>
      </div>
    </div>
</template>

<script>
import { Bar } from 'vue-chartjs';

export default {

   data: () => {
     return {
       //chart: "",
       selected_tab: 0,
       api_endpoint: 'https://spallina.dev/api/v1',
       api: [{
              name: 'Difficulty',
              api_method: '/difficulty/12'
            }, {
              name: 'Block Size',
              api_method: '/blocksize/12'
            }]
     }
   },
   methods: {
     panelClick(index) {
       this.selected_tab = index;
       this.chart.destroy();
       switch(index) {
         case 0:
          this.renderDifficultyChart();
          break;

         case 1:
          this.renderBlockSizeChart();
          break;
       }
     },
     renderDifficultyChart() {
       let Height = new Array();
       let Time = new Array();
       let Difficulty = new Array();
       this.axios.get(this.api_endpoint + this.api[this.selected_tab].api_method).then((response) => {
          let data = response.data;
          if(data) {
             data.forEach(element => {
             Height.push(element.height);
             Time.push(new Date(element.block_time * 1000).toLocaleTimeString());
             Difficulty.push(element.difficulty);
             });
             this.chart = new Chart(document.getElementById('chart_body').getContext('2d'), {
               type: 'line',
               data: {
                 labels: Time,
                 datasets: [{
                    label: 'Difficulty',
                    backgroundColor: '#FC2525',
                    borderColor: '#FC2525',
                    data: Difficulty,
                    fill: false,
                    pointRadius: 1,
					          pointHoverRadius: 10
                  }]
               },
               options: {
                 responsive: true,
                 maintainAspectRatio: false,
                 scales: {
                   yAxes: [
                     {
                       ticks: {
                         callback: function(label, index, labels) {
                           return label/1000000000+'bn';
                         }
                       },
                       scaleLabel: {
                         display: true,
                         labelString: '1bn = 1\'000\'000\'000'
                       }
                     }
                   ],
                   xAxes: [{
                     ticks: {
                       autoSkip: true,
                       maxTicksLimit: 15
                     }
                   }]
                 },
                 tooltips: {
                   mode: 'index',
				           intersect: false,
					         callbacks: {
						         footer: function(tooltipItems, data) {
                       return 'Block #' + Height[tooltipItems[0].index];
						         },
					         },
					         footerFontStyle: 'normal'
				         },
               }
             }

             );
            } else {
              console.log('No data');
            }
          });
     },
     renderBlockSizeChart() {
       let Height = new Array();
       let Time = new Array();
       let BlockSize = new Array();
       this.axios.get(this.api_endpoint + this.api[this.selected_tab].api_method).then((response) => {
          let data = response.data;
          if(data) {
             data.forEach(element => {
             Height.push(element.height);
             Time.push(new Date(element.block_time * 1000).toLocaleTimeString());
             BlockSize.push(element.block_size/1000);
             });
             this.chart = new Chart(document.getElementById('chart_body').getContext('2d'), {
               type: 'line',
               data: {
                 labels: Time,
                 datasets: [{
                    label: 'Block Size',
                    backgroundColor: '#005ec9',
                    borderColor: '#005ec9',
                    data: BlockSize,
                    fill: false,
                    pointRadius: 1,
					          pointHoverRadius: 10
                  }]
               },
               options: {
                 responsive: true,
                 maintainAspectRatio: false,
                 scales: {
                   yAxes: [
                     {
                       ticks: {
                         callback: function(label, index, labels) {
                           return label+'kB';
                         }
                       }
                     }
                   ],
                   xAxes: [{
                     ticks: {
                       autoSkip: true,
                       maxTicksLimit: 15
                     }
                   }]
                 },
                 tooltips: {
                   mode: 'index',
				           intersect: false,
					         callbacks: {
						         footer: function(tooltipItems, data) {
                       return 'Block #' + Height[tooltipItems[0].index];
						         },
					         },
					         footerFontStyle: 'normal'
				         },
               }
             }

             );
            } else {
              console.log('No data');
            }
          });
     }
   },
   mounted() {
     this.renderDifficultyChart();
   }
}
</script>
