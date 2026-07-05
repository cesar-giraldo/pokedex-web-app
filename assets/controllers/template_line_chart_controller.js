import { Controller } from '@hotwired/stimulus';
import ApexCharts from 'apexcharts';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['container'];
    static values = {
        data: Array,
        categories: Array,
        label: String
    }

    connect() {
        const options = {
            chart: {
                type: 'line', // Cambiamos el tipo a 'line'
                height: 350,
                zoom: {
                    enabled: false // Desactiva el zoom por defecto para un look más limpio
                }
            },
            stroke: {
                curve: 'smooth', // 'smooth' para líneas curvas o 'straight' para líneas rectas y angulares
                width: 3        // Grosor de la línea en píxeles
            },
            series: [{
                name: this.labelValue || 'Datos',
                data: this.dataValue
            }],
            xaxis: {
                categories: this.categoriesValue
            },
            markers: {
                size: 4 // Añade puntos/marcadores en cada nodo de la gráfica
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
