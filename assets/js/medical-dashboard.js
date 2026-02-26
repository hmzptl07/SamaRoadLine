(function () {
  "use strict";

  /* Patients Overview */
  var options = {
    series: [
      {
        name: "Male",
        data: [80, 50, 30, 40, 100, 20, 80],
      },
      {
        name: "Female",
        data: [20, 100, 60, 50, 50, 80, 33],
      },
    ],
    chart: {
      height: 215,
      type: "radar",
      toolbar: {
        show: false,
      },
    },
    colors: ["rgba(244, 110, 244, 0.1)", "var(--primary01)"],
    stroke: {
      width: 1.5,
      colors: ["rgb(244, 110, 244)", "var(--primary-color)"],
    },
    fill: {
      opacity: 0.1,
    },
    markers: {
      size: 0,
    },
    legend: {
      show: false,
      offsetX: 0,
      offsetY: 0,
      fontSize: "12px",
      markers: {
        width: 6,
        height: 6,
        strokeWidth: 0,
        strokeColor: "#fff",
        fillColors: undefined,
        radius: 5,
        customHTML: undefined,
        onClick: undefined,
        offsetX: 0,
        offsetY: 0,
      },
    },
    xaxis: {
      categories: ["Cardiology", "Pediatrics", "Orthopedic", "Neurology", "Psychiatry", "Radiology", "Others"],
      axisBorder: { show: false },
    },
    yaxis: {
      axisBorder: { show: false },
    },
    grid: {
      padding: {
        bottom: -25
      }
    },
  };
  var chart = new ApexCharts(document.querySelector("#patients-overview"), options);
  chart.render();
  /* Patients Overview */

  /* total patients */
  var options = {
    series: [
      {
        type: "line",
        name: "This Year",
        data: [15, 30, 22, 49, 32, 45, 30, 45, 65, 45, 25, 45],
      },
      {
        type: "line",
        name: "Previous Year",
        data: [8, 40, 15, 32, 45, 30, 20, 25, 18, 23, 20, 40],
      }
    ],
    chart: {
      type: "line",
      height: 292,
      toolbar: {
        show: false
      },
    },
    plotOptions: {
      bar: {
        columnWidth: "40%",
        borderRadius: 4,
      }
    },
    colors: [
      "var(--primary07)",
      "rgba(244, 110, 244, 1)",
    ],
    fill: {
      type: 'solid',
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.4,
        opacityTo: 0.1,
        stops: [0, 90, 100],
      }
    },
    dataLabels: {
      enabled: false,
    },
    legend: {
      show: true,
      position: "top",
    },
    stroke: {
      curve: 'smooth',
      width: [2, 2],
      lineCap: 'round',
      dashArray: [4, 0]
    },
    grid: {
      borderColor: "#edeef1",
      strokeDashArray: 4,
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
    yaxis: {
      axisBorder: {
        show: true,
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
    },
    xaxis: {
      type: "month",
      categories: [
        "Jan",
        "Feb",
        "Mar",
        "Apr",
        "May",
        "Jun",
        "Jul",
        "Aug",
        "sep",
        "oct",
        "nov",
        "dec",
      ],
      axisBorder: {
        show: false,
        color: "rgba(119, 119, 142, 0.05)",
        offsetX: 0,
        offsetY: 0,
      },
      axisTicks: {
        show: false,
        borderType: "solid",
        color: "rgba(119, 119, 142, 0.05)",
        width: 6,
        offsetX: 0,
        offsetY: 0,
      },
      labels: {
        rotate: -90,
      },
    },
    tooltip: {
      enabled: true,
      theme: "dark",
    }
  };
  var chart4 = new ApexCharts(document.querySelector("#total-patients"), options);
  chart4.render();
  /* total patients */

})();