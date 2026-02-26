/* Total Leads */
let options2 = {
    series: [{
        data: [20, 14, 19, 10, 23, 20, 22, 9, 12]
    }],
    chart: {
        height: 40,
        width: 80,
        type: 'bar',
        fontFamily: 'Poppins, Arial, sans-serif',
        foreColor: '#5d6162',
        zoom: {
            enabled: false
        },
        sparkline: {
            enabled: true
        }
    },
    plotOptions: {
        bar: {
            columnWidth: '40%',
            borderRadius: 2,
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
        curve: 'straight'
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
const chart12 = new ApexCharts(document.querySelector("#total-leads"), options2);
chart12.render();
/* Total Leads */

/* Converion Rate */
let options3 = {
    series: [{
        data: [20, 14, 20, 22, 9, 12, 19, 10, 25]
    }],
    chart: {
        height: 40,
        width: 80,
        type: 'line',
        fontFamily: 'Poppins, Arial, sans-serif',
        foreColor: '#5d6162',
        zoom: {
            enabled: false
        },
        sparkline: {
            enabled: true
        }
    },
    plotOptions: {
        bar: {
            columnWidth: '40%',
            borderRadius: 2,
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
        width: 2,
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
};
const chart13 = new ApexCharts(document.querySelector("#conversion-rate"), options3);
chart13.render();
/* Converion Rate */

/* Pending tasks */
let options4 = {
    series: [{
        data: [20, 20, 22, 9, 14, 19, 10, 25, 12]
    }],
    chart: {
        height: 40,
        width: 80,
        type: 'bar',
        fontFamily: 'Poppins, Arial, sans-serif',
        foreColor: '#5d6162',
        zoom: {
            enabled: false
        },
        sparkline: {
            enabled: true
        }
    },
    plotOptions: {
        bar: {
            columnWidth: '40%',
            borderRadius: 2,
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
        curve: 'straight'
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
};
const chart14 = new ApexCharts(document.querySelector("#pending-tasks"), options4);
chart14.render();
/* Pending tasks */

/* Sales Pipeline */
let options7 = {
    series: [{
        data: [20, 20, 22, 9, 12, 14, 19, 10, 25]
    }],
    chart: {
        height: 40,
        width: 80,
        type: 'line',
        fontFamily: 'Poppins, Arial, sans-serif',
        foreColor: '#5d6162',
        zoom: {
            enabled: false
        },
        sparkline: {
            enabled: true
        }
    },
    plotOptions: {
        bar: {
            columnWidth: '40%',
            borderRadius: 2,
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
        width: 2,
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
};
const chart15 = new ApexCharts(document.querySelector("#sales-pipeline"), options7);
chart15.render();
/* Sales Pipeline */

/* New Contacts */
let options8 = {
    series: [{
        data: [20, 19, 10, 25, 20, 22, 9, 12, 14]
    }],
    chart: {
        height: 40,
        width: 80,
        type: 'bar',
        fontFamily: 'Poppins, Arial, sans-serif',
        foreColor: '#5d6162',
        zoom: {
            enabled: false
        },
        sparkline: {
            enabled: true
        }
    },
    plotOptions: {
        bar: {
            columnWidth: '40%',
            borderRadius: 2,
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
        curve: 'straight'
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
    colors: ["rgb(250, 182, 50)"],
};
const chart16 = new ApexCharts(document.querySelector("#new-contacts"), options8);
chart16.render();
/* New Contacts */

/* Revenue Analytics Chart */
var options = {
    series: [
        {
            type: 'line',
            name: 'Profit',
            data: [
                {
                    x: 'Jan',
                    y: 100
                },
                {
                    x: 'Feb',
                    y: 210
                },
                {
                    x: 'Mar',
                    y: 180
                },
                {
                    x: 'Apr',
                    y: 454
                },
                {
                    x: 'May',
                    y: 230
                },
                {
                    x: 'Jun',
                    y: 320
                },
                {
                    x: 'Jul',
                    y: 656
                },
                {
                    x: 'Aug',
                    y: 830
                },
                {
                    x: 'Sep',
                    y: 350
                },
                {
                    x: 'Oct',
                    y: 350
                },
                {
                    x: 'Nov',
                    y: 210
                },
                {
                    x: 'Dec',
                    y: 410
                }
            ]
        },
        {
            type: 'line',
            name: 'Revenue',
            chart: {
                dropShadow: {
                    enabled: true,
                    enabledOnSeries: undefined,
                    top: 5,
                    left: 0,
                    blur: 3,
                    color: '#000',
                    opacity: 0.1
                }
            },
            data: [
                {
                    x: 'Jan',
                    y: 180
                },
                {
                    x: 'Feb',
                    y: 620
                },
                {
                    x: 'Mar',
                    y: 476
                },
                {
                    x: 'Apr',
                    y: 220
                },
                {
                    x: 'May',
                    y: 520
                },
                {
                    x: 'Jun',
                    y: 780
                },
                {
                    x: 'Jul',
                    y: 435
                },
                {
                    x: 'Aug',
                    y: 515
                },
                {
                    x: 'Sep',
                    y: 738
                },
                {
                    x: 'Oct',
                    y: 454
                },
                {
                    x: 'Nov',
                    y: 525
                },
                {
                    x: 'Dec',
                    y: 230
                }
            ]
        },
        {
            type: 'line',
            name: 'Sales',
            chart: {
                dropShadow: {
                    enabled: true,
                    enabledOnSeries: undefined,
                    top: 5,
                    left: 0,
                    blur: 3,
                    color: '#000',
                    opacity: 0.1
                }
            },
            data: [
                {
                    x: 'Jan',
                    y: 200
                },
                {
                    x: 'Feb',
                    y: 530
                },
                {
                    x: 'Mar',
                    y: 110
                },
                {
                    x: 'Apr',
                    y: 130
                },
                {
                    x: 'May',
                    y: 480
                },
                {
                    x: 'Jun',
                    y: 520
                },
                {
                    x: 'Jul',
                    y: 780
                },
                {
                    x: 'Aug',
                    y: 435
                },
                {
                    x: 'Sep',
                    y: 475
                },
                {
                    x: 'Oct',
                    y: 738
                },
                {
                    x: 'Nov',
                    y: 454
                },
                {
                    x: 'Dec',
                    y: 480
                }
            ]
        }
    ],
    chart: {
        height: 290,
        animations: {
            speed: 500
        }
    },
    colors: ["var(--primary-color)", "rgb(133, 204, 65)", "rgb(250, 182, 50)"],
    dataLabels: {
        enabled: false
    },
    grid: {
        borderColor: '#f1f1f1',
        strokeDashArray: 3
    },
    stroke: {
        curve: 'smooth',
        width: [2, 2, 2],
        dashArray: [0, 0, 5]
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
            }
        },
    },
    tooltip: {
        y: [{
            formatter: function (e) {
                return void 0 !== e ? "$" + e.toFixed(0) : e
            }
        }, {
            formatter: function (e) {
                return void 0 !== e ? "$" + e.toFixed(0) : e
            }
        }, {
            formatter: function (e) {
                return void 0 !== e ? e.toFixed(0) : e
            }
        }]
    },
    legend: {
        show: true,
        position: "bottom",
        offsetX: 0,
        offsetY: 8,
        markers: {
            size: 4,
            strokeWidth: 0,
            strokeColor: '#fff',
        },
    },
    title: {
        align: 'left',
        style: {
            fontSize: '.8125rem',
            fontWeight: 'semibold',
            color: '#8c9097'
        },
    },
    markers: {
        hover: {
            sizeOffset: 5
        }
    }
};
var chart = new ApexCharts(document.querySelector("#crm-revenue-analytics"), options);
chart.render();
/* Revenue Analytics Chart */

/* Leads-overview chart */
var options17 = {
    series: [{
        name: 'Cold Leads',
        data: [80, 50, 30, 40, 100, 20, 40],
    }, {
        name: 'Hot Leads',
        data: [20, 30, 40, 90, 20, 90, 35],
    }, {
        name: 'Warm Leads',
        data: [40, 76, 78, 13, 43, 10, 80],
    }],
    chart: {
        height: 310,
        type: 'radar',
        toolbar: {
            show: false,
        }
    },
    title: {
        align: 'left',
        style: {
            fontSize: '13px',
            fontWeight: 'bold',
            color: '#8c9097'
        },
    },
    colors: ["var(--primary08)", "rgba(133, 204, 65, 0.85)", "rgba(40, 200, 235, 0.85)"],
    stroke: {
        width: 1
    },
    fill: {
        opacity: 0.05
    },
    markers: {
        size: 0
    },
    legend: {
        show: true,
        fontSize: "12px",
        position: 'bottom',
        horizontalAlign: 'center',
        fontFamily: "Montserrat",
        fontWeight: 500,
        offsetX: 0,
        offsetY: 0,
        labels: {
            colors: '#9ba5b7',
        },
        markers: {
            size: 4,
            strokeWidth: 0,
            strokeColor: '#fff',
            fillColors: undefined,
            radius: 7,
            offsetX: 0,
            offsetY: 0
        },
    },
    xaxis: {
        categories: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
    },
    yaxis: {
        tickAmount: 7,
        labels: {
            formatter: function (val, i) {
                if (i % 5 === 0) {
                    return val
                }
            }
        }
    }
};
var chart17 = new ApexCharts(document.querySelector("#leads-overview"), options17);
chart17.render();
/* Leads-overview chart */