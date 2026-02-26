'use strict'

/* courses completed */
var options = {
  series: [{
    name: 'This Month',
    data: [44, 55, 41, 42, 22, 43, 21, 35, 56, 27, 43, 27]
  }, {
    name: 'This Week',
    data: [33, 21, 32, 37, 23, 32, 47, 31, 54, 32, 20, 38]
  }, {
    name: 'This Year',
    data: [30, 25, 36, 30, 45, 35, 64, 51, 59, 36, 39, 51]
  }],
  chart: {
    type: 'bar',
    height: 370,
    fontFamily: 'Poppins, sans-serif',
    foreColor: '#8c9097',
    stacked: true,
    toolbar: {
      show: false
    },
    zoom: {
      enabled: true
    },
  },
  grid: {
    borderColor: '#90A4AE',
    strokeDashArray: 2,
  },
  dataLabels: {
    enabled: false,
  },
  responsive: [{
    breakpoint: 480,
    options: {
      legend: {
        position: 'bottom',
        offsetX: -10,
        offsetY: 0
      }
    }
  }],
  stroke: {
    show: true,
    width: [4, 4, 4],
    curve: 'smooth',
  },
  plotOptions: {
    bar: {
      columnWidth: "20%",
      borderRadius: 3,
      borderRadiusApplication: 'around',
      borderRadiusWhenStacked: 'all',
      borderRadius: 2,
    },
  },
  legend: {
    position: 'right',
    offsetY: 40
  },
  fill: {
    opacity: 1
  },
  legend: {
    show: false
  },
  tooltip: {
    enabled: true,
    shared: true,
    intersect: false,
  },
  colors: ['var(--primary-color)', 'rgb(40, 200, 235)', 'rgb(133, 204, 65)'],
  labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
};
var chart = new ApexCharts(document.querySelector("#courses-completed"), options);
chart.render();
/* courses completed */

/* top course categories */
var options = {
  series: [31, 21, 15, 10, 23],
  chart: {
    width: 220,
    type: 'donut',
    sparkline: {
      enabled: true
    }
  },
  legend: {
    show: false,
  },
  plotOptions: {
    pie: {
      expandOnClick: false,
      donut: {
        size: '85%',
        background: 'transparent',
        labels: {
          show: true,
          name: {
            show: true,
            fontSize: '20px',
            color: '#495057',
            offsetY: -4
          },
          value: {
            show: true,
            fontSize: '18px',
            color: undefined,
            offsetY: 8,
            formatter: function (val) {
              return val + "%"
            }
          },
          total: {
            show: true,
            showAlways: true,
            label: 'Total',
            fontSize: '22px',
            fontWeight: 600,
            color: '#495057',
          }

        }
      }
    }
  },
  stroke: {
    width: 0
  },
  colors: ["var(--primary-color)", "rgba(40, 200, 235, 1)", "rgba(133, 204, 65, 1)", "rgba(244, 110, 244, 1)", "rgba(250, 182, 50, 1)"],
  labels: ['Sales', 'Marketing', 'IT', 'Consultancy', 'Finance'],
  responsive: [{
    breakpoint: 480,
    options: {
      chart: {
        width: 200
      },
      legend: {
        enabled: true,
        position: 'bottom',
      }
    }
  }]
};
var chart1 = new ApexCharts(document.querySelector("#course-categories"), options);
chart1.render();
/* top course categories */