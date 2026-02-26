/* Total Employees */
var options = {
  series: [
    {
      data: [47, 45, 54, 38, 56, 24, 65, 31, 37, 39, 62, 51, 35, 41, 35, 27, 53, 53, 61, 27, 54, 43, 19, 46],
    },
  ],
  chart: {
    type: 'area',
    height: 50,
    sparkline: {
      enabled: true,
    },
  },
  stroke: {
    curve: 'smooth',
    width: 1.5,
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
  colors: ["var(--primary-color)"],
  tooltip: {
    fixed: {
      enabled: false,
    },
    x: {
      show: false,
    },
    y: {
      title: {
        formatter: function (seriesName) {
          return "";
        },
      },
    },
  },
};
var chart = new ApexCharts(document.querySelector("#employees"), options);
chart.render();
/* Total Employees */

/* Total Jobs Applied */
var options = {
  series: [
    {
      data: [47, 45, 54, 38, 56, 24, 65, 31, 37, 39, 62, 51, 35, 41, 35, 27, 53, 53, 61, 27, 54, 43, 19, 46],
    },
  ],
  chart: {
    type: 'area',
    height: 50,
    sparkline: {
      enabled: true,
    },
  },
  stroke: {
    curve: 'smooth',
    width: 1.5,
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
            color: 'rgba(133, 204, 65, 0.4)',
            opacity: 1
          },
          {
            offset: 60,
            color: 'rgba(133, 204, 65, 0.2)',
            opacity: 1
          },
          {
            offset: 100,
            color: 'rgba(133, 204, 65, 0)',
            opacity: 1
          }
        ],
      ]
    },
  },
  colors: ["rgb(133, 204, 65)"],
  tooltip: {
    fixed: {
      enabled: false,
    },
    x: {
      show: false,
    },
    y: {
      title: {
        formatter: function (seriesName) {
          return "";
        },
      },
    },
  },
};
var chart2 = new ApexCharts(document.querySelector("#job-applied"), options);
chart2.render();
/* Total Jobs Applied */

/* Total Compensation */
var options = {
  series: [
    {
      data: [47, 45, 54, 38, 56, 24, 65, 31, 37, 39, 62, 51, 35, 41, 35, 27, 53, 53, 61, 27, 54, 43, 19, 46],
    },
  ],
  chart: {
    type: 'area',
    height: 50,
    sparkline: {
      enabled: true,
    },
  },
  stroke: {
    curve: 'smooth',
    width: 1.5,
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
            color: 'rgba(40, 200, 235, 0.4)',
            opacity: 1
          },
          {
            offset: 60,
            color: 'rgba(40, 200, 235, 0.2)',
            opacity: 1
          },
          {
            offset: 100,
            color: 'rgba(40, 200, 235, 0)',
            opacity: 1
          }
        ],
      ]
    },
  },
  colors: ["rgb(40, 200, 235)"],
  tooltip: {
    fixed: {
      enabled: false,
    },
    x: {
      show: false,
    },
    y: {
      title: {
        formatter: function (seriesName) {
          return "";
        },
      },
    },
  },
};
var chart3 = new ApexCharts(document.querySelector("#total-payouts"), options);
chart3.render();
/* Total Compensation */

/* Annual Compensation */
var options = {
  series: [
    {
      data: [47, 45, 54, 38, 56, 24, 65, 31, 37, 39, 62, 51, 35, 41, 35, 27, 53, 53, 61, 27, 54, 43, 19, 46],
    },
  ],
  chart: {
    type: 'area',
    height: 50,
    sparkline: {
      enabled: true,
    },
    dropShadow: {
      enabled: true,
      enabledOnSeries: undefined,
      top: 0,
      left: 0,
      blur: 3,
      color: "rgb(244, 110, 244)",
      opacity: 0.4,
    },
  },
  stroke: {
    curve: 'smooth',
    width: 1.5,
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
            color: 'rgba(244, 110, 244, 0.4)',
            opacity: 1
          },
          {
            offset: 60,
            color: 'rgba(244, 110, 244, 0.2)',
            opacity: 1
          },
          {
            offset: 100,
            color: 'rgba(244, 110, 244, 0)',
            opacity: 1
          }
        ],
      ]
    },
  },
  colors: ["rgb(244, 110, 244)"],
  tooltip: {
    fixed: {
      enabled: false,
    },
    x: {
      show: false,
    },
    y: {
      title: {
        formatter: function (seriesName) {
          return "";
        },
      },
    },
  },
};
var chart4 = new ApexCharts(document.querySelector("#gross-salary"), options);
chart4.render();
/* Annual Compensation */

/* application-statistics */
var chart = {
  series: [
    {
      name: "Hired",
      data: [44, 42, 57, 86, 58, 55, 70, 43, 23, 54, 77, 34],
    },
    {
      name: "Rejected",
      data: [-34, -22, -37, -56, -21, -35, -60, -34, -56, -78, -89, -53],
    },
  ],
  chart: {
    toolbar: {
      show: false,
    },
    type: "bar",
    fontFamily: "'Poppins', sans-serif",
    height: 320,
    stacked: true,
  },
  colors: ["rgb(244, 110, 244)", "var(--primary-color)"],
  plotOptions: {
    bar: {
      columnWidth: "15%",
      borderRadiusApplication: 'around',
      borderRadiusWhenStacked: 'all',
      borderRadius: 2,
    }
  },
  dataLabels: {
    enabled: false,
  },
  stroke: {
    show: true,
    width: 2,
    endingShape: 'rounded',
    colors: ['transparent'],
  },
  legend: {
    show: true,
    position: 'bottom',
    offsetY: 10,
    fontSize: "13px",
    markers: {
      size: 4,
      shape: 'circle',
    },
  },
  grid: {
    borderColor: "rgba(0,0,0,0.1)",
    strokeDashArray: 3,
    xaxis: {
      lines: {
        show: false,
      },
    },
  },
  xaxis: {
    axisBorder: {
      show: false,
    },
    categories: [
      "Jan",
      "Feb",
      "Mar",
      "Apr",
      "May",
      "Jun",
      "Jul",
      "Aug",
      "Sep",
      "Oct",
      "Nov",
      "Dec",
    ],

  },
  yaxis: {
    tickAmount: 4,
  },
};
var chart1 = new ApexCharts(document.querySelector("#Application-statistics"), chart);
chart1.render();
/* application-statistics */

/*  Gender chart */
var options = {
  series: [500, 350, 150],
  chart: {
    width: 310,
    type: 'polarArea'
  },
  labels: ['Male', 'Female', 'Others'],
  fill: {
    opacity: 0.9
  },
  stroke: {
    width: 1,
    colors: undefined
  },
  colors: ["var(--primary-color)", "rgb(40, 200, 235)", "rgb(133, 204, 65)"],
  yaxis: {
    show: false
  },
  legend: {
    position: 'right',
    offsetY: 30,
    offsetX: -30,
    markers: {
      size: 5,
      shape: "circle",
      strokeWidth: 0
    }
  },
};
var chart07 = new ApexCharts(document.querySelector("#gender-chart"), options);
chart07.render();
/*  Gender chart */