(function () {
  "use strict";

  /* total revenue */
  var options = {
    series: [{
      data: [98, 110, 80, 145, 105, 112, 87, 148, 102]
    }],
    chart: {
      height: 40,
      type: 'area',
      fontFamily: 'Montserrat, sans-serif',
      foreColor: '#5d6162',
      zoom: {
        enabled: false
      },
      sparkline: {
        enabled: true
      }
    },
    tooltip: {
      enabled: true,
      theme: "dark",
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
    stroke: {
      curve: 'smooth',
      width: [1.5],
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
    title: {
      text: undefined,
    },
    grid: {
      borderColor: 'transparent',
    },
    xaxis: {
      crosshairs: {
        show: false,
      }
    },
    colors: ["var(--primary-color)"],
  };
  var chart1 = new ApexCharts(document.querySelector("#total-revenue"), options);
  chart1.render();
  /* total revenue */

  /* total customers */
  var options = {
    series: [{
      data: [98, 110, 80, 145, 105, 112, 87, 148, 102]
    }],
    chart: {
      height: 40,
      type: 'area',
      fontFamily: 'Montserrat, sans-serif',
      foreColor: '#5d6162',
      zoom: {
        enabled: false
      },
      sparkline: {
        enabled: true
      }
    },
    tooltip: {
      enabled: true,
      theme: "dark",
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
    stroke: {
      curve: 'smooth',
      width: [1.5],
    },
    title: {
      text: undefined,
    },
    grid: {
      borderColor: 'transparent',
    },
    xaxis: {
      crosshairs: {
        show: false,
      }
    },
    colors: ["rgb(133, 204, 65)"],
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
  };
  var chart2 = new ApexCharts(document.querySelector("#total-customers"), options);
  chart2.render();
  /* total customers */

  /* total transactions */
  var options = {
    series: [{
      data: [98, 110, 80, 145, 105, 112, 87, 148, 102]
    }],
    chart: {
      height: 40,
      type: 'area',
      fontFamily: 'Montserrat, sans-serif',
      foreColor: '#5d6162',
      zoom: {
        enabled: false
      },
      sparkline: {
        enabled: true
      }
    },
    tooltip: {
      enabled: true,
      theme: "dark",
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
    stroke: {
      curve: 'smooth'
    },
    title: {
      text: undefined,
    },
    grid: {
      borderColor: 'transparent',
    },
    xaxis: {
      crosshairs: {
        show: false,
      }
    },
    colors: ["rgb(40, 200, 235)"],
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
    stroke: {
      width: [1.5],
    }
  };
  var chart3 = new ApexCharts(document.querySelector("#total-transactions"), options);
  chart3.render();
  /* total transactions */

  /* total products */
  var options = {
    series: [{
      data: [98, 110, 80, 145, 105, 112, 87, 148, 102]
    }],
    chart: {
      height: 40,
      type: 'area',
      fontFamily: 'Montserrat, sans-serif',
      foreColor: '#5d6162',
      zoom: {
        enabled: false
      },
      sparkline: {
        enabled: true
      }
    },
    tooltip: {
      enabled: true,
      theme: "dark",
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
    stroke: {
      curve: 'smooth'
    },
    title: {
      text: undefined,
    },
    grid: {
      borderColor: 'transparent',
    },
    xaxis: {
      crosshairs: {
        show: false,
      }
    },
    colors: ["rgb(244, 110, 244)"],
    stroke: {
      width: [1.5],
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
  };
  var chart4 = new ApexCharts(document.querySelector("#total-products"), options);
  chart4.render();
  /* total products */

  /* Sales Statistics */
  var options1 = {
    series: [{
      name: 'Sales',
      data: [74, 85, 57, 56, 76, 35, 61, 98, 36, 50, 48, 29],
      type: "bar",
    }, {
      name: 'Revenue',
      data: [46, 35, 101, 98, 44, 55, 57, 56, 55, 34, 79, 46],
      type: "bar",
    }, {
      name: 'Profit',
      data: [26, 35, 41, 78, 34, 65, 27, 46, 37, 65, 49, 23],
      type: "bar",
    }, {
      name: 'Customers',
      data: [20, 53, 11, 13, 48, 52, 78, 43, 47, 73, 45, 48],
      type: "area",
    }],
    chart: {
      height: 300,
      type: "bar",
      toolbar: {
        show: false,
      },
      stacked: true,
      fontFamily: 'Nunito, sans-serif',
    },
    dataLabels: {
      enabled: false
    },
    stroke: {
      show: true,
      width: 2,
      endingShape: 'rounded',
      colors: ['transparent'],
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
      position: 'bottom',
      offsetY: 10,
      fontSize: "13px",
      markers: {
        size: 4,
        shape: 'circle',
        strokeWidth: 0
      },
    },
    labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
    colors: ['var(--primary-color)', 'rgb(40, 200, 235)', 'rgb(133, 204, 65)', "var(--primary005)"],
    plotOptions: {
      bar: {
        columnWidth: "15%",
        borderRadiusApplication: 'around',
        borderRadiusWhenStacked: 'all',
        borderRadius: 2,
      }
    },
    fill: {
      opacity: 1
    },
    tooltip: {
      y: {
        formatter: function (val) {
          return "$ " + val + " thousands"
        }
      }
    }
  };
  var chart1 = new ApexCharts(document.querySelector("#sales-statistics"), options1);
  chart1.render();
  /* Sales Statistics */

  /* Visitors By Device */
  var options = {
    series: [4289, 3655, 2964, 1573],
    labels: ["Mobile", "Desktop", "Laptop", "Tablet"],
    chart: {
      height: 224,
      type: 'donut',
    },
    dataLabels: {
      enabled: false,
    },
    legend: {
      show: true,
      position: 'bottom',
      markers: {
        size: 4,
        shape: 'circle',
        strokeWidth: 0
      },
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
        expandOnClick: false,
        donut: {
          size: '86%',
          background: 'transparent',
          labels: {
            show: true,
            name: {
              show: true,
              fontSize: '20px',
              color: '#495057',
              fontFamily: "Montserrat, sans-serif",
              offsetY: -5
            },
            value: {
              show: true,
              fontSize: '22px',
              color: undefined,
              offsetY: 5,
              fontWeight: 600,
              fontFamily: "Montserrat, sans-serif",
              formatter: function (val) {
                return val + "%"
              }
            },
            total: {
              show: true,
              showAlways: true,
              label: 'Total Visitors',
              fontSize: '14px',
              fontWeight: 400,
              color: '#495057',
            }
          }
        }
      }
    },
    colors: ["var(--primary-color)", "rgba(244, 110, 244, 1)", "rgba(133, 204, 65, 1)", "rgba(40, 200, 235, 1)"],
  };
  var chart = new ApexCharts(document.querySelector("#visitors-device"), options);
  chart.render();
  /* Visitors By Device */

})();