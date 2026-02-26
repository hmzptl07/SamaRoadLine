/*  Start::Followers */
var options = {
  series: [
    {
      data: [1, 20, 15, 35, 30, 25, 55, 45, 65],
    },
  ],
  chart: {
    height: 70,
    width: 100,
    type: 'area',
    zoom: {
      enabled: false
    },
    sparkline: {
      enabled: true
    }
  },
  tooltip: {
    enabled: true,
    x: {
      show: false
    },
    y: {
      title: {
        formatter: function (seriesName) {
          return ''
        }
      }
    },
    marker: {
      show: false
    }
  },
  dataLabels: {
    enabled: false
  },
  grid: {
    borderColor: 'transparent',
  },
  xaxis: {
    crosshairs: {
      show: false,
    }
  },
  yaxis: {
    max: 65,
  },
  colors: ["var(--primary-color)"],
  stroke: {
    width: [1.5],
  },
  fill: {
    opacity: 0.001,
    type: ['gradient'],
    gradient: {
      shade: 'light',
      shadeIntensity: 0.5,
      gradientToColors: ['var(--primary01)'],
      inverseColors: true,
      opacityFrom: 0.35,
      opacityTo: 0.05,
      stops: [0, 50, 100],
      colorStops: [
        [
          {
            offset: 0,
            color: "var(--primary-color)",
            opacity: 0.4
          },
          {
            offset: 55,
            color: "var(--primary-color)",
            opacity: 0.2
          },
          {
            offset: 100,
            color: "var(--primary-color)",
            opacity: 0
          }
        ],
      ]
    }
  }
};
var chart = new ApexCharts(document.querySelector("#chart-21"), options);
chart.render();
/*  End::Followers */

/*  Start::Session Rate */
var options1 = {
  series: [
    {
      data: [1, 20, 15, 35, 30, 25, 55, 45, 65],
    },
  ],
  chart: {
    height: 70,
    width: 100,
    type: 'area',
    zoom: {
      enabled: false
    },
    sparkline: {
      enabled: true
    }
  },
  tooltip: {
    enabled: true,
    x: {
      show: false
    },
    y: {
      title: {
        formatter: function (seriesName) {
          return ''
        }
      }
    },
    marker: {
      show: false
    }
  },
  dataLabels: {
    enabled: false
  },
  grid: {
    borderColor: 'transparent',
  },
  xaxis: {
    crosshairs: {
      show: false,
    }
  },
  yaxis: {
    max: 65,
  },
  colors: ["rgb(133, 204, 65)"],
  stroke: {
    width: [1.5],
  },
  fill: {
    opacity: 0.001,
    type: ['gradient'],
    gradient: {
      shade: 'light',
      shadeIntensity: 0.5,
      gradientToColors: ['rgba(133, 204, 65, 0.1)'],
      inverseColors: true,
      opacityFrom: 0.35,
      opacityTo: 0.05,
      stops: [0, 50, 100],
      colorStops: [
        [
          {
            offset: 0,
            color: "rgba(133, 204, 65, 1)",
            opacity: 0.4
          },
          {
            offset: 55,
            color: "rgba(133, 204, 65, 1)",
            opacity: 0.2
          },
          {
            offset: 100,
            color: "rgba(133, 204, 65, 1)",
            opacity: 0
          }
        ],
      ]
    }
  }
};
var chart1 = new ApexCharts(document.querySelector("#chart-22"), options1);
chart1.render();
/*  End::Session Rate */

/*  Start::Conversion Rate */
var options2 = {
  series: [
    {
      data: [1, 20, 15, 35, 30, 25, 55, 45, 65],
    },
  ],
  chart: {
    height: 70,
    width: 100,
    type: 'area',
    zoom: {
      enabled: false
    },
    sparkline: {
      enabled: true
    }
  },
  tooltip: {
    enabled: true,
    x: {
      show: false
    },
    y: {
      title: {
        formatter: function (seriesName) {
          return ''
        }
      }
    },
    marker: {
      show: false
    }
  },
  dataLabels: {
    enabled: false
  },
  grid: {
    borderColor: 'transparent',
  },
  xaxis: {
    crosshairs: {
      show: false,
    }
  },
  yaxis: {
    max: 65,
  },
  colors: ["rgb(40, 200, 235)"],
  stroke: {
    width: [1.5],
  },
  fill: {
    opacity: 0.001,
    type: ['gradient'],
    gradient: {
      shade: 'light',
      shadeIntensity: 0.5,
      gradientToColors: ['rgba(40, 200, 235, 0.1)'],
      inverseColors: true,
      opacityFrom: 0.35,
      opacityTo: 0.05,
      stops: [0, 50, 100],
      colorStops: [
        [
          {
            offset: 0,
            color: "rgba(40, 200, 235, 1)",
            opacity: 0.4
          },
          {
            offset: 55,
            color: "rgba(40, 200, 235, 1)",
            opacity: 0.2
          },
          {
            offset: 100,
            color: "rgba(40, 200, 235, 1)",
            opacity: 0
          }
        ],
      ]
    }
  }
};
var chart2 = new ApexCharts(document.querySelector("#chart-23"), options2);
chart2.render();
/*  End::Conversion Rate */

/*  Start::Review */
var options3 = {
  series: [
    {
      data: [1, 20, 15, 35, 30, 25, 55, 45, 65],
    },
  ],
  chart: {
    height: 70,
    width: 100,
    type: 'area',
    zoom: {
      enabled: false
    },
    sparkline: {
      enabled: true
    }
  },
  tooltip: {
    enabled: true,
    x: {
      show: false
    },
    y: {
      title: {
        formatter: function (seriesName) {
          return ''
        }
      }
    },
    marker: {
      show: false
    }
  },
  dataLabels: {
    enabled: false
  },
  grid: {
    borderColor: 'transparent',
  },
  xaxis: {
    crosshairs: {
      show: false,
    }
  },
  yaxis: {
    max: 65,
  },
  colors: ["rgb(244, 110, 244)"],
  stroke: {
    width: [1.5],
  },
  fill: {
    opacity: 0.001,
    type: ['gradient'],
    gradient: {
      shade: 'light',
      shadeIntensity: 0.5,
      gradientToColors: ['rgba(244, 110, 244, 0.1)'],
      inverseColors: true,
      opacityFrom: 0.35,
      opacityTo: 0.05,
      stops: [0, 50, 100],
      colorStops: [
        [
          {
            offset: 0,
            color: "rgba(244, 110, 244, 1)",
            opacity: 0.4
          },
          {
            offset: 55,
            color: "rgba(244, 110, 244, 1)",
            opacity: 0.2
          },
          {
            offset: 100,
            color: "rgba(244, 110, 244, 1)",
            opacity: 0
          }
        ],
      ]
    }
  }
};
var chart3 = new ApexCharts(document.querySelector("#chart-24"), options3);
chart3.render();
/*  End::Review */

/*  Start::audienceMetric */
var options4 = {
  series: [
    {
      type: "line",
      name: "Viewers",
      data: [
        {
          x: "Jan",
          y: 320,
        },
        {
          x: "Feb",
          y: 560,
        },
        {
          x: "Mar",
          y: 250,
        },
        {
          x: "Apr",
          y: 486,
        },
        {
          x: "May",
          y: 310,
        },
        {
          x: "Jun",
          y: 560,
        },
        {
          x: "Jul",
          y: 560,
        },
        {
          x: "Aug",
          y: 860,
        },
        {
          x: "Sep",
          y: 400,
        },
        {
          x: "Oct",
          y: 500,
        },
        {
          x: "Nov",
          y: 350,
        },
        {
          x: "Dec",
          y: 700,
        },
      ],
    },
    {
      type: "bar",
      name: "Followers",
      data: [
        {
          x: "Jan",
          y: 680,
        },
        {
          x: "Feb",
          y: 800,
        },
        {
          x: "Mar",
          y: 680,
        },
        {
          x: "Apr",
          y: 840,
        },
        {
          x: "May",
          y: 980,
        },
        {
          x: "Jun",
          y: 720,
        },
        {
          x: "Jul",
          y: 900,
        },
        {
          x: "Aug",
          y: 1000,
        },
        {
          x: "Sep",
          y: 850,
        },
        {
          x: "Oct",
          y: 950,
        },
        {
          x: "Nov",
          y: 750,
        },
        {
          x: "Dec",
          y: 860,
        },
      ],
    },
    {
      type: "bar",
      name: "Sessions",
      data: [
        {
          x: "Jan",
          y: 180,
        },
        {
          x: "Feb",
          y: 250,
        },
        {
          x: "Mar",
          y: 300,
        },
        {
          x: "Apr",
          y: 350,
        },
        {
          x: "May",
          y: 350,
        },
        {
          x: "Jun",
          y: 250,
        },
        {
          x: "Jul",
          y: 150,
        },
        {
          x: "Aug",
          y: 250,
        },
        {
          x: "Sep",
          y: 350,
        },
        {
          x: "Oct",
          y: 350,
        },
        {
          x: "Nov",
          y: 250,
        },
        {
          x: "Dec",
          y: 200,
        },
      ],
    },
  ],
  chart: {
    type: "area",
    height: 342,
    animations: {
      speed: 500,
    },
    toolbar: {
      show: false,
    },
    stacked: {
      enabled: true
    }
  },
  colors: ["rgba(244, 110, 244, 1)", "rgba(40, 200, 235, 1)", "var(--primary-color)"],
  dataLabels: {
    enabled: false,
  },
  grid: {
    borderColor: "#f1f1f1",
    strokeDashArray: 3,
  },
  fill: {
    type: ['soild', 'solid', 'soild'],
    gradient: {
      opacityFrom: 0.05,
      opacityTo: 0.05,
      shadeIntensity: 0.1,
    },
  },
  stroke: {
    curve: ["smooth", "stepline", "smooth"],
    width: [2, 2, 2],
    dashArray: [0, 0, 0, 0],
  },
  xaxis: {
    axisTicks: {
      show: false,
    },
  },
  yaxis: {
    labels: {
      formatter: function (value) {
        return "$" + value;
      },
    },
  },
  plotOptions: {
    bar: {
      columnWidth: "15%",
      borderRadius: "2",
      borderRadiusApplication: 'around',
      borderRadiusWhenStacked: 'all',
    },
  },
  tooltip: {
    y: [
      {
        formatter: function (e) {
          return void 0 !== e ? e.toFixed(0) : e;
        },
      },
      {
        formatter: function (e) {
          return void 0 !== e ? e.toFixed(0) : e;
        },
      },
      {
        formatter: function (e) {
          return void 0 !== e ? e.toFixed(0) : e;
        },
      },
    ],
  },
  legend: {
    show: true,
    position: "top",
    inverseOrder: true,
    markers: {
      size: 5,
      shape: "circle",
      strokeWidth: 0
    }
  },
};
document.getElementById("audienceMetric").innerHTML = "";
var chart4 = new ApexCharts(document.querySelector("#audienceMetric"), options4);
chart4.render();
/*  End::audienceMetric */

/* Start:: Sales growth */
var options6 = {
  series: [{
    name: 'Last Year',
    data: [35, 36, 22, 44, 48, 37, 36, 26, 27, 33, 32, 36],
    type: 'line',
  }, {
    name: 'This Year',
    data: [55, 53, 46, 40, 45, 38, 46, 37, 22, 34, 40, 44,],
    type: 'bar',
  },
  ],
  chart: {
    type: 'line',
    height: 205,
    stacked: true,
    toolbar: {
      show: false,
    },
    sparkline: {
      enabled: false
    },
  },
  plotOptions: {
    bar: {
      columnWidth: "40%",
      borderRadius: "4",
      borderRadiusApplication: 'around',
      borderRadiusWhenStacked: 'all',
    },
  },
  grid: {
    show: true,
    xaxis: {
      lines: {
        show: true
      }
    },
    yaxis: {
      lines: {
        show: false
      }
    },
    padding: {
      top: 2,
      right: 2,
      bottom: 2,
      left: 2
    },
    borderColor: '#f1f1f1',
    strokeDashArray: 3
  },
  markers: {
    size: 3,
    hover: {
      size: 3
    },
  },
  colors: ["rgba(244, 110, 244, 1)", "var(--primary-color)"],
  stroke: {
    curve: 'straight',
    width: 2,
    dashArray: 5
  },
  legend: {
    show: true,
    position: "bottom",
    fontSize: '10px',
    fontFamily: 'Poppins',
    markers: {
      size: 3.5,
      shape: "circle",
      strokeWidth: 0
    },
  },
  yaxis: {
    axisBorder: {
      show: false,
      color: "rgba(119, 119, 142, 0.05)",
      offsetX: 0,
      offsetY: 0,
    },
    axisTicks: {
      show: true,
      borderType: "solid",
      color: "rgba(119, 119, 142, 0.05)",
      width: 6,
      offsetX: 0,
      offsetY: 0,
    },
    title: {
      style: {
        color: '#adb5be',
        fontSize: '14px',
        fontFamily: 'poppins, sans-serif',
        fontWeight: 600,
        cssClass: 'apexcharts-yaxis-label',
      },
    },
    labels: {
      show: false,
      formatter: function (y) {
        return y.toFixed(0) + "";
      }
    }
  },
  xaxis: {
    type: 'month',
    categories: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'],
    axisBorder: {
      show: true,
      color: "rgba(119, 119, 142, 0.05)",
      offsetX: 0,
      offsetY: 0,
    },
    title: {
      style: {
        color: '#adb5be',
        fontSize: '5px',
        fontFamily: 'poppins, sans-serif',
        fontWeight: 600,
        cssClass: 'apexcharts-yaxis-label',
      },
    },
  },
};
var chart11 = new ApexCharts(document.querySelector("#sales-growth"), options6);
chart11.render();
/* End:: Sales growth */

/*  referralsChart chart */
var options = {
  series: [14, 23, 21, 17, 15],
  chart: {
    type: 'polarArea',
    height: 320
  },
  stroke: {
    colors: ['rgba(255,255,255,0.5)'],
  },
  fill: {
    opacity: 1
  },
  legend: {
    show: false,
    position: 'bottom',
    markers: {
      size: 4,
      shape: 'circle',
    },
  },
  labels: ['Organic Search', 'Direct', 'Referral', 'Social', 'Email'],
  colors: ["var(--primary08)", "rgba(133, 204, 65, 0.8)", "rgba(40, 200, 235, 0.8)", "rgba(244, 110, 244, 0.8)", "rgba(250, 182, 50, 0.8)"],
  plotOptions: {
    pie: {
      startAngle: -90,
      endAngle: 90,
      offsetY: 0,
      expandOnClick: false,
      donut: {
        size: '95%',
        background: 'transparent',
      }
    }
  },
  grid: {
    padding: {
      bottom: -120
    }
  },
};
var chart = new ApexCharts(document.querySelector("#referrals-chart"), options);
chart.render();
/* referralsChart */