
(function () {

  /* Project Statistics */
  var options = {
    chart: {
      height: 315,
      type: "line",
      stacked: false,
      toolbar: {
        show: false,
      }
    },
    dataLabels: {
      enabled: false
    },
    colors: ["var(--primary-color)", "rgb(244, 110, 244)", "rgb(133, 204, 65)"],
    series: [{
      name: 'Active Projects',
      type: 'column',
      data: [104, 102, 117, 146, 118, 115, 220, 103, 83, 114, 265, 174],
    }, {
      name: "Completed Projects",
      type: "column",
      data: [92, 75, 123, 111, 196, 122, 159, 102, 138, 136, 62, 240]
    }, {
      name: 'Project Revenue',
      type: 'line',
      data: [35, 52, 86, 65, 102, 70, 152, 87, 55, 92, 170, 80],
    }],
    stroke: {
      width: [2, 2, 2],
      curve: "smooth"
    },
    plotOptions: {
      bar: {
        columnWidth: '30%',
        borderRadius: 2
      }
    },
    markers: {
      size: [0, 0, 5],
      colors: undefined,
      strokeColors: '#fff',
      strokeOpacity: 0,
      strokeWidth: 0,
      strokeDashArray: 0,
      fillOpacity: 1,
      discrete: [],
      shape: "circle",
      radius: [0, 0, 2],
      offsetX: 0,
      offsetY: 0,
      onClick: undefined,
      onDblClick: undefined,
      showNullDataPoints: true,
      hover: {
        size: undefined,
        sizeOffset: 3
      }
    },
    fill: {
      opacity: [1, 1, 1]
    },
    grid: {
      borderColor: '#f1f1f1',
      strokeDashArray: 3
    },
    legend: {
      show: true,
      position: 'bottom',
    },
    yaxis: {
      min: 0,
      forceNiceScale: true,
      title: {
        style: {
          color: '	#adb5be',
          fontSize: '14px',
          fontFamily: 'poppins, sans-serif',
          fontWeight: 600,
          cssClass: 'apexcharts-yaxis-label',
        },
      },
      labels: {
        formatter: function (y) {
          return y.toFixed(0) + "";
        }
      }
    },
    xaxis: {
      type: 'month',
      categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
      axisBorder: {
        show: true,
        color: 'rgba(119, 119, 142, 0.05)',
        offsetX: 0,
        offsetY: 0,
      },
      axisTicks: {
        show: true,
        borderType: 'solid',
        color: 'rgba(119, 119, 142, 0.05)',
        width: 6,
        offsetX: 0,
        offsetY: 0
      },
      labels: {
        rotate: -90
      }
    },
    tooltip: {
      enabled: true,
      shared: false,
      intersect: true,
      x: {
        show: false
      }
    },
  };
  var chart = new ApexCharts(document.querySelector("#project-statistics"), options);
  chart.render();
  /* Project Statistics */

  /* task-activity */
  var options = {
    series: [1754, 634, 878, 470],
    labels: ["On Going", "Completed", "To do", "Pending"],
    chart: {
      height: 208,
      type: 'donut',
    },
    dataLabels: {
      enabled: false,
    },

    legend: {
      show: false,
    },
    stroke: {
      show: true,
      curve: 'smooth',
      lineCap: 'round',
      colors: "#fff",
      width: 0,
      dashArray: 0,
    },
    plotOptions: {
      pie: {
        startAngle: -90,
        endAngle: 90,
        offsetY: 10,
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
              offsetY: -30
            },
            value: {
              show: true,
              fontSize: '15px',
              color: undefined,
              offsetY: -25,
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
    grid: {
      padding: {
        bottom: -100
      }
    },
    colors: ["var(--primary-color)", "rgba(40, 200, 235, 1)", "rgba(133, 204, 65, 1)", "rgba(244, 110, 244, 1)"],
  };
  var chart = new ApexCharts(document.querySelector("#task-activity"), options);
  chart.render();
  /* task-activity */

})();