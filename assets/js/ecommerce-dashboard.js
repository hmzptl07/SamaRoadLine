
/* Order Status */
var options = {
  series: [{
    name: "Paid",
    type: "column",
    data: [33, 21, 32, 37, 23, 32, 47, 31, 54, 32, 20, 38]
  }, {
    name: "Unpaid",
    type: "area",
    data: [44, 55, 41, 42, 22, 43, 21, 35, 56, 27, 43, 27]
  }, {
    name: "Refunded",
    type: "line",
    data: [30, 25, 36, 30, 45, 35, 64, 51, 59, 36, 39, 51]
  }],
  chart: {
    height: 300,
    type: "line",
    stacked: !1,
    toolbar: {
      show: !1
    }
  },
  stroke: {
    width: [0, 0, 2],
    dashArray: [0, 0, 4],
    show: true,
    curve: 'smooth',
    lineCap: 'butt',
  },
  grid: {
    borderColor: '#f1f1f1',
    strokeDashArray: 3
  },
  xaxis: {
    axisBorder: {
      color: 'rgba(119, 119, 142, 0.05)',
      offsetX: 0,
      offsetY: 0,
    },
    axisTicks: {
      color: 'rgba(119, 119, 142, 0.05)',
      width: 6,
      offsetX: 0,
      offsetY: 0
    },
  },
  plotOptions: {
    bar: {
      columnWidth: "10%",
      borderRadius: 3
    }
  },
  legend: {
    position: "top",
    markers: {
      size: 7,
      strokeWidth: 0,
    },
  },
  labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
  colors: ['var(--primary-color)', "rgba(244, 110, 244, 0.05)", 'rgb(133, 204, 65)'],
  tooltip: {
    theme: "dark",
  },
};
var chart = new ApexCharts(document.querySelector("#order-status"), options);
chart.render();
/* Order Status */

/* Total Orders */
var options = {
  chart: {
    height: 295,
    type: 'radialBar',
    responsive: 'true',
    offsetX: 0,
    offsetY: 15,
  },
  plotOptions: {
    radialBar: {
      startAngle: -135,
      endAngle: 135,
      size: 120,
      imageWidth: 50,
      imageHeight: 50,
      track: {
        strokeWidth: '97%',
        // strokeWidth: "0",
      },
      dropShadow: {
        enabled: false,
        top: 0,
        left: 0,
        bottom: 0,
        blur: 3,
        opacity: 0.5
      },
      dataLabels: {
        name: {
          fontSize: '16px',
          color: undefined,
          offsetY: 30,
        },
        hollow: {
          size: "60%"
        },
        value: {
          offsetY: -10,
          fontSize: '22px',
          color: undefined,
          formatter: function (val) {
            return val + "%";
          }
        }
      }
    }
  },
  colors: ['var(--primary-color)'],
  fill: {
    type: "solid",
    gradient: {
      shade: "dark",
      type: "horizontal",
      shadeIntensity: .5,
      gradientToColors: ["#b94eed"],
      inverseColors: false,
      opacityFrom: 1,
      opacityTo: 1,
      stops: [0, 100]
    }
  },
  stroke: {
    dashArray: 3
  },
  series: [92],
  labels: ["Orders"]
};
var chart1 = new ApexCharts(document.querySelector("#total-orders"), options);
chart1.render();
/* Total Orders */

/* top selling categories */
var options = {
  series: [{
    name: 'Sales',
    data: [650, 770, 840, 890, 1100, 1380]
  }],
  chart: {
    height: 312,
    type: 'bar',
    events: {
      click: function (chart, w, e) {
      }
    },
    toolbar: {
      show: false,
    }
  },
  colors: ['var(--primary-color)', 'rgba(133, 204, 65, 1)', 'rgba(40, 200, 235, 1)', 'rgba(244, 110, 244, 1)', 'rgba(250, 182, 50, 1)', 'rgba(250, 75, 66, 1)'],
  plotOptions: {
    bar: {
      barHeight: '15%',
      distributed: true,
      horizontal: true,
      borderRadius: 3,
    }
  },
  dataLabels: {
    enabled: false
  },
  legend: {
    show: false
  },
  grid: {
    borderColor: '#f1f1f1',
    strokeDashArray: 3
  },
  xaxis: {
    categories: [
      ['Electronics'],
      ['Fashion'],
      ['Kitchen Appliances'],
      ['Beauty Products'],
      ['Books'],
      ['Toys and Games'],
    ],
    labels: {
      show: false,
      style: {
        fontSize: '12px'
      },
    }
  },
  yaxis: {
    offsetX: 30,
    offsetY: 30,
    labels: {
      show: true,
      style: {
        colors: "#8c9097",
        fontSize: '11px',
        fontWeight: 500,
        cssClass: 'apexcharts-yaxis-label',
      },
      offsetY: 8,
    }
  },
  tooltip: {
    enabled: true,
    shared: false,
    intersect: true,
    x: {
      show: false
    },
    theme: "dark",
  },
};
var chart2 = new ApexCharts(document.querySelector("#top-selling-categories"), options);
chart2.render();
/* top selling categories */