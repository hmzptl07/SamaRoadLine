/* profile analysis */
var options = {
    series: [
        {
            name: "Followers",
            data: [44, 42, 57, 86, 58, 55, 70, 43, 23, 54, 77, 34],
        },
        {
            name: "Account Reached",
            data: [74, 72, 87, 116, 88, 85, 100, 73, 53, 84, 107, 64],
        },
        {
            name: "People Engaged",
            data: [84, 82, 97, 126, 98, 95, 110, 83, 63, 94, 117, 74],
        }
    ],
    chart: {
        stacked: true,
        type: "line",
        height: 332,
        dropShadow: {
            enabled: true,
            enabledOnSeries: undefined,
            top: 7,
            left: 1,
            blur: 3,
            color: '#000',
            opacity: 0.1
        },
        toolbar: {
            show: false,
        }
    },
    grid: {
        borderColor: "#f5f4f4",
        strokeDashArray: 5,
        yaxis: {
            lines: {
                show: true, // Ensure y-axis grids are shown
            },
        },
    },
    colors: [
        "var(--primary-color)",
        "rgba(40, 200, 235, 1)",
        "rgba(244, 110, 244, 1)",
    ],
    stroke: {
        curve: ["stepline", "stepline", "stepline"],
        width: [2, 2, 2],
    },
    dataLabels: {
        enabled: false,
    },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.4,
            opacityTo: 0.1,
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
                        color: "var(--primary-color)",
                        opacity: 1
                    }
                ],
                [
                    {
                        offset: 0,
                        color: "rgba(40, 200, 235,1)",
                        opacity: 1
                    },
                    {
                        offset: 75,
                        color: "rgba(40, 200, 235,1)",
                        opacity: 1
                    },
                    {
                        offset: 100,
                        color: "rgba(40, 200, 235,1)",
                        opacity: 1
                    }
                ],
                [
                    {
                        offset: 0,
                        color: "rgba(244, 110, 244,1)",
                        opacity: 1
                    },
                    {
                        offset: 75,
                        color: "rgba(244, 110, 244,1)",
                        opacity: 1
                    },
                    {
                        offset: 100,
                        color: "rgba(244, 110, 244,1)",
                        opacity: 1
                    }
                ]
            ]
        }
    },
    legend: {
        show: true,
        position: "top",
    },
    yaxis: {
        title: {
            style: {
                color: "#adb5be",
                fontSize: "14px",
                fontFamily: "Montserrat, sans-serif",
                fontWeight: 600,
                cssClass: "apexcharts-yaxis-label",
            },
        },
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
        labels: {
            formatter: function (y) {
                return y.toFixed(0) + "";
            },
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
        theme: "dark",
    }
};
var chart = new ApexCharts(document.querySelector("#profile-analysis"), options);
chart.render();
/* profile analysis */

/* Follow On Device */
var options = {
    series: [1754, 1234, 784],
    labels: ["Mobile", "Tablet", "Desktop"],
    chart: {
        height: 178,
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
                        label: 'Total Audience',
                        fontSize: '14px',
                        fontWeight: 400,
                        color: '#495057',
                    }
                }
            }
        }
    },
    colors: ["var(--primary-color)", "rgba(133, 204, 65, 1)", "rgba(40, 200, 235, 1)"],
};
var chart = new ApexCharts(document.querySelector("#devices"), options);
chart.render();
/* Follow On Device */