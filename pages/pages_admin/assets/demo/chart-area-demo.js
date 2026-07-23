// Set new default font family and font color to mimic Bootstrap's default styling
Chart.defaults.global.defaultFontFamily = '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
Chart.defaults.global.defaultFontColor = '#292b2c';

(function () {
  var ctx = document.getElementById("myAreaChart");
  if (!ctx) {
    return;
  }

  var actividadData = window.dashboardActividadData || {};
  var labels = Array.isArray(actividadData.labels) ? actividadData.labels : [];
  var visitas = Array.isArray(actividadData.visitas) ? actividadData.visitas : [];

  if (labels.length === 0 || visitas.length === 0 || labels.length !== visitas.length) {
    labels = ["Sin datos"];
    visitas = [0];
  }

  var maxVisitas = visitas.reduce(function (maximo, valor) {
    var numero = parseInt(valor, 10);
    if (isNaN(numero) || numero < 0) {
      numero = 0;
    }
    return numero > maximo ? numero : maximo;
  }, 0);

  var datasets = [{
    label: actividadData.datasetLabelVisitas || "Visitas",
    lineTension: 0.3,
    backgroundColor: "rgba(2,117,216,0.2)",
    borderColor: "rgba(2,117,216,1)",
    pointRadius: 4,
    pointBackgroundColor: "rgba(2,117,216,1)",
    pointBorderColor: "rgba(255,255,255,0.8)",
    pointHoverRadius: 5,
    pointHoverBackgroundColor: "rgba(2,117,216,1)",
    pointHitRadius: 30,
    pointBorderWidth: 2,
    data: visitas,
    yAxisID: "y-visitas"
  }];

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: datasets
    },
    options: {
      scales: {
        xAxes: [{
          gridLines: {
            display: false
          },
          ticks: {
            maxTicksLimit: 10
          }
        }],
        yAxes: [{
          id: "y-visitas",
          position: "left",
          ticks: {
            beginAtZero: true,
            maxTicksLimit: 6,
            suggestedMax: Math.max(5, Math.ceil(maxVisitas * 1.2))
          },
          gridLines: {
            color: "rgba(0, 0, 0, .125)"
          }
        }]
      },
      legend: {
        display: false
      },
      tooltips: {
        callbacks: {
          label: function (tooltipItem, data) {
            var datasetLabel = data.datasets[tooltipItem.datasetIndex].label || '';
            return datasetLabel + ': ' + tooltipItem.yLabel;
          }
        }
      }
    }
  });
})();
