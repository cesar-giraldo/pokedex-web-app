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
        // 1. Definir las opciones del gráfico usando los valores que vienen de Symfony
        const options = {
            chart: {
                type: 'bar',
                height: 350
            },
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    horizontal: false, // Cambia a true si quieres barras horizontales
                }
            },
            series: [{
                name: this.labelValue || 'Data',
                data: this.dataValue
            }],
            xaxis: {
                categories: this.categoriesValue
            }
        };

        // 2. Renderizar el gráfico en el contenedor objetivo
        this.chart = new ApexCharts(this.containerTarget, options);
        this.chart.render();
    }

    disconnect() {
        // Destruir el gráfico si el elemento se elimina del DOM (buena práctica)
        if (this.chart) {
            this.chart.destroy();
        }
    }
}
