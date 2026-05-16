import {
    Chart,
    BarElement,
    BarController,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
} from 'chart.js';

Chart.register(BarElement, BarController, CategoryScale, LinearScale, Tooltip, Legend);

document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('jobs-chart');
    if (!canvas) return;

    const labels  = JSON.parse(canvas.dataset.labels);
    const success = JSON.parse(canvas.dataset.success);
    const failed  = JSON.parse(canvas.dataset.failed);

    const style = getComputedStyle(document.documentElement);
    const colorPrimary = style.getPropertyValue('--color-primary').trim()  || '#adc6ff';
    const colorError   = style.getPropertyValue('--color-error').trim()    || '#ffb4ab';
    const colorSurface = style.getPropertyValue('--color-surface-container-highest').trim() || '#2d3449';
    const colorText    = style.getPropertyValue('--color-on-surface-variant').trim() || '#c2c6d6';

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Sucesso',
                    data: success,
                    backgroundColor: colorPrimary + '33',
                    borderColor: colorPrimary,
                    borderWidth: 1,
                    borderRadius: 4,
                    borderSkipped: false,
                },
                {
                    label: 'Falha',
                    data: failed,
                    backgroundColor: colorError + '33',
                    borderColor: colorError,
                    borderWidth: 1,
                    borderRadius: 4,
                    borderSkipped: false,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    labels: {
                        color: colorText,
                        font: { family: "'Inter', sans-serif", size: 11 },
                        boxWidth: 10,
                        padding: 16,
                    },
                },
                tooltip: {
                    backgroundColor: '#171f33',
                    borderColor: '#424754',
                    borderWidth: 1,
                    titleColor: colorText,
                    bodyColor: colorText,
                    titleFont: { family: "'Inter', sans-serif", size: 11 },
                    bodyFont: { family: "'Inter', sans-serif", size: 12 },
                    padding: 10,
                    callbacks: {
                        title: (items) => items[0].label,
                    },
                },
            },
            scales: {
                x: {
                    grid: { color: colorSurface, drawBorder: false },
                    ticks: {
                        color: colorText,
                        font: { family: "'Inter', sans-serif", size: 11 },
                    },
                    border: { display: false },
                },
                y: {
                    grid: { color: colorSurface, drawBorder: false },
                    ticks: {
                        color: colorText,
                        font: { family: "'Inter', sans-serif", size: 11 },
                        stepSize: 1,
                        precision: 0,
                    },
                    border: { display: false },
                    beginAtZero: true,
                },
            },
        },
    });
});
