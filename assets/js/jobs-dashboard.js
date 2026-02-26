"use strict"
// Candidates Performance

var options1 = {
  series: [{
    name: 'Weekly',
    data: [31, 11, 22, 37, 13, 22, 37, 21, 44, 22, 34, 25],
    type: "column",
  }, {
    name: 'Monthly',
    data: [44, 55, 41, 67, 22, 43, 21, 41, 56, 27, 43, 23],
    type: "area",
  }, {
    name: 'Daily',
    data: [30, 8, 20, 36, 15, 22, 37, 19, 44, 24, 32, 23],
    type: "line",
  }],
  chart: {
    height: 300,
    type: "line",
    toolbar: {
      show: false,
    },
  },
  dataLabels: {
    enabled: false
  },
  stroke: {
    show: true,
    width: [2, 1, 2],   
    curve: "smooth",
  },
  grid: {
    borderColor: '#f1f1f1',
    strokeDashArray: 3
  },
  xaxis: {
    categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    axisBorder: {
      show: false,
    },
    axisTicks: {
      show: false,
    },
  },
  yaxis: {
    axisBorder: {
      show: false,
    },
  },
  legend: {
    show: true,
    position: "top",
    horizontalAlign: "right",
    fontSize: "11px",
    fontFamily: "Helvetica, Arial",
    fontWeight: 600,
    labels: {
      colors: '#74767c',
    },
    markers: {
      width: 7,
      height: 7,
      strokeWidth: 0,
      strokeColor: "#fff",
      fillColors: undefined,
      radius: 12,
      customHTML: undefined,
      onClick: undefined,
      offsetX: 0,
      offsetY: 0,
    },
  },
  labels: [
    "01/01/2003",
    "02/01/2003",
    "03/01/2003",
    "04/01/2003",
    "05/01/2003",
    "06/01/2003",
    "07/01/2003",
    "08/01/2003",
    "09/01/2003",
    "10/01/2003",
    "11/01/2003",
    "12/01/2003",
  ],
  colors: ["var(--primary-color)", "var(--primary005)", "rgb(244, 110, 244)"],
  plotOptions: {
    bar: {
      columnWidth: "15%",
      borderRadius: 4,
    }
  },
  fill: {
    opacity: [1, 0.05, 1],
  },
  tooltip: {
    y: {
      formatter: function (val) {
        return "$ " + val + " thousands"
      }
    }
  }
};
var chart1 = new ApexCharts(document.querySelector("#candidatePerformance"), options1);
chart1.render();

// Candidates Performance

/* Job Statistics */
var options = {
  chart: {
    height: 260,
    type: "radialBar"
  },
  series: [75, 67],
  colors: ["var(--primary-color)", "rgb(244, 110, 244)"],
  plotOptions: {
      radialBar: {
          hollow: {
              margin: 0,
              size: "65%",
              background: "#fff",
          },
          dataLabels: {
              name: {
                  offsetY: -10,
                  color: "#4b9bfa",
                  fontSize: "16px",
                  show: false,
              },
              value: {
                  offsetY: 10,
                  color: "#4b9bfa",
                  fontSize: "22px",
                  show: true,
              },
              total: {
                  show: true,
                  label: 'Total',
              }
          },
      },
  },
  legend: {
    show: true,
    position: 'bottom',
    horizontalAlign: 'center',
    fontWeight: 600,
    fontSize: '11px',
    labels: {
      colors: '#74767c',
    },
    markers: {
      width: 7,
      height: 7,
      strokeWidth: 0,
      radius: 12,
      offsetX: 0,
      offsetY: 0
    },
  },
  stroke: {
    lineCap: "round",
  },
  labels: ["Job View", "Job Applied"],
};
var chart = new ApexCharts(document.querySelector("#quickData"), options);
chart.render();
/* Job Statistics */