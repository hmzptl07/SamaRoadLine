(function () {
  "use strict";

  var options = {
    series: [{
      name: 'Hours',
      data: [20, 35, 66, 40, 30, 55, 45]
    }],
    chart: {
      height: 260,
      fontFamily: 'Poppins, Arial, sans-serif',
      type: 'area',
      toolbar: {
        show: false
      }
    },
    grid: {
      show: false,
      borderColor: '#f2f6f7',
    },
    dataLabels: {
      enabled: false
    },
    legend: {
      position: 'top',
      fontSize: '13px',
    },
    colors: ["var(--primary-color)"],
    stroke: {
      width: [2],
      curve: 'smooth',
    },
    fill: {
      type: 'gradient',
      gradient: {
        opacityFrom: 0.5,
        opacityTo: 0.2,
        stops: [0, 60],
        colorStops: [
          [
            {
              offset: 0,
              color: 'var(--primary04)',
              opacity: 1
            },
            {
              offset: 60,
              color: 'var(--primary02)',
              opacity: 1
            },
            {
              offset: 100,
              color: 'rgba(121, 97, 245, 0)',
              opacity: 1
            }
          ],
        ]
      },
    },
    tooltip: {
      enabled: true,
      theme: "dark",
    },
    labels: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
  };
  var chart3 = new ApexCharts(document.querySelector("#podcast-activity"), options);
  chart3.render();

})()
