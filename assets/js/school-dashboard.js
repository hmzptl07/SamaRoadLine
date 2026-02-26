/* Earnings Report */
var options = {
  series: [{
    name: 'Total Present',
    type: "column",
    data: [44, 30, 57, 80, 90, 55, 70, 43, 23, 54, 77, 34]
  }, {
    name: 'Total Absent',
    type: "area",
    data: [30, 25, 36, 30, 45, 35, 64, 51, 59, 36, 39, 51]
  }],
  chart: {
    fontFamily: 'Montserrat',
    height: 295,
    type: 'line',
    stacked: !1,
    toolbar: {
      show: !1
    },
    dropShadow: {
      enabled: true,
      enabledOnSeries: undefined,
      top: 6,
      left: 0,
      blur: 0,
      color: 'var(--primary-color)',
      opacity: 0.05
    },
  },
  grid: {
    borderColor: '#f2f6f7',
    borderColor: "#f1f1f1",
    strokeDashArray: 2,
    xaxis: {
      lines: {
        show: true
      }
    },
    yaxis: {
      lines: {
        show: false
      }
    }
  },
  dataLabels: {
    enabled: false
  },
  legend: {
    position: 'top'
  },
  colors: ["var(--primary-color)", "rgb(244, 110, 244)"],
  fill: {
    type: ['solid', 'gradient'],
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.4,
      opacityTo: 0.1,
      type: "vertical",
      stops: [0, 90, 100],
      colorStops: [
        [
          {
            offset: 0,
            color: "var(--primary-color)",
            opacity: 1
          },
          {
            offset: 75,
            color: "var(--primary-color)",
            opacity: 1
          },
          {
            offset: 100,
            color: 'var(--primary-color)',
            opacity: 1
          }
        ],
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
    }
  },
  stroke: {
    width: [1.5, 1.5],
    curve: ['smooth', 'smooth'],
    dashArray: [0, 4]
  },
  labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
  legend: {
    show: true,
    position: 'top'
  },
  plotOptions: {
    bar: {
      columnWidth: "25%",
      borderRadius: 2
    }
  },
  tooltip: {
    enabled: true,
    theme: "dark",
  }
};
var chart1 = new ApexCharts(document.querySelector("#earnings-report"), options);
chart1.render();
/* Earnings Report */

 /* Attendance Overview */
 var options = {
  chart: {
      height: 199,
      type: "radialBar",
  },
  series: [72, 84],
  colors: ["var(--primary-color)", "rgba(244, 110, 244, 1)"],
  plotOptions: {
      radialBar: {
          hollow: {
              margin: 0,
              size: "60%",
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
  stroke: {
      lineCap: "round",
  },
};
var chart1 = new ApexCharts(document.querySelector("#attendance-overview"), options);
chart1.render();
/* Attendance Overview */