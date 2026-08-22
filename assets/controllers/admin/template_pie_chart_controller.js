import { Controller } from '@hotwired/stimulus';
import ApexCharts from 'apexcharts';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['container'];
    static values = {
        data: Array,
        labels: Array
    }

    connect() {
        const options = {
            chart: {
                type: 'donut',
                height: 380,
                toolbar: {
                    show: true, // Muestra el menú de descarga
                    offsetX: 0,
                    offsetY: 0,
                    tools: {
                        download: true, // Habilita explícitamente el botón de descarga
                        selection: false,
                        zoom: false,
                        zoomin: false,
                        zoomout: false,
                        pan: false,
                        reset: false
                    },
                    export: {
                        csv: {
                            filename: 'ventas-por-categoria', // Nombre del archivo descargado
                            columnDelimiter: ',',
                            headerCategory: 'Categoría',
                            headerValue: 'Cantidad de Ventas',
                        },
                        svg: {
                            filename: 'grafica-ventas',
                        },
                        png: {
                            filename: 'grafica-ventas',
                        }
                    }
                }
            },
            colors: ['#3C50E0', '#80CAEE', '#F2994A', '#2A3A4B', '#A3B1CC'],
            // En gráficos tipo pie, 'series' es un arreglo plano de números (no objetos)
            series: this.dataValue,
            labels: this.labelsValue, // Usamos la propiedad 'labels' en lugar de 'xaxis'
            tooltip: {
                theme: 'dark', // Mantiene el estilo oscuro acorde a tu fondo
                style: {
                    fontSize: '12px',
                    fontFamily: 'inherit'
                },
                y: {
                    title: {
                        // Aplica el padding mediante CSS personalizado inyectado por la librería
                        formatter: (seriesName) => seriesName,
                    }
                },
                // Inyectamos estilos directamente a los contenedores internos de ApexCharts
                custom: function ({ series, seriesIndex, dataPointIndex, w }) {
                    const label = w.globals.labels[seriesIndex];
                    const value = series[seriesIndex];

                    // Retornamos el HTML estructurado con los paddings exactos que solicitas
                    return `
                        <div style="
                            padding: 2px 3px 2px 3px; 
                            background: #1e293b; 
                            color: #fff; 
                            border: 1px solid #334155; 
                            border-radius: 4px;
                            font-size: 12px;
                            display: flex;
                            align-items: center;
                            gap: 6px;
                        ">
                            <span style="background-color: ${w.config.colors[seriesIndex]}; width: 8px; height: 8px; border-radius: 50%; display: inline-block;"></span>
                            <span>${label}: <strong>${value}</strong></span>
                        </div>
                    `;
                }
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '70%', // Grosor de la dona (porcentaje del radio)
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total Ventas',
                                fontSize: '16px',
                                color: '#64748B', // Color gris del texto
                                formatter: function (w) {
                                    // Suma automáticamente los valores
                                    return w.globals.seriesTotals.reduce((a, b) => a + b, 0)
                                }
                            }
                        }
                    }
                }
            },
            responsive: [{
                breakpoint: 480,
                options: {
                    chart: {
                        width: 200
                    },
                    legend: {
                        position: 'bottom' // Mueve las etiquetas abajo en pantallas móviles
                    }
                }
            }],
            legend: {
                position: 'right', // Coloca las etiquetas al lado derecho en pantallas grandes
                fontSize: '14px',
                labels: {
                    colors: '#64748B' // Asegura que los textos coincidan con el diseño del dashboard
                }
            }
        };

        this.chart = new ApexCharts(this.containerTarget, options);
        this.chart.render();
    }

    disconnect() {
        if (this.chart) {
            this.chart.destroy();
        }
    }
}
