(function() {
    "use strict";

    var myElement3 = document.getElementById('filemanager-file-details');
    new SimpleBar(myElement3, { autoHide: true });

    var options = {
        series: [38, 36, 27, 32],
        labels: ["Media", "Downloads", "Apps", "Documents"],
        chart: {
            height: 180,
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
                    size: '75%',
                    background: 'transparent',
                    labels: {
                        show: true,
                        name: {
                            show: true,
                            fontSize: '20px',
                            color: '#495057',
                            offsetY: -3,
                        },
                        value: {
                            show: true,
                            fontSize: '28px',
                            fontWeight: 600,
                            color: undefined,
                            offsetY: -40,
                            formatter: function (val) {
                                return val + "%"
                            },
                        },
                        total: {
                            show: true,
                            showAlways: true,
                            label: 'Used of 720 GB',
                            fontSize: '12px',
                            fontWeight: 400,
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
    var chart = new ApexCharts(document.querySelector("#file-manager-storage"), options);
    chart.render();

    /* dropzone */
    let myDropzone = new Dropzone(".dropzone");
    myDropzone.on("addedfile", file => {
        console.log(`File added: ${file.name}`);
    }); 

})();