import Chart from 'chart.js/auto';

const POLL_MS = 5000;
const chartInstances = new Map();

function readDashboard() {
    const el = document.getElementById('crash-analyzer-dashboard');
    if (!el) return null;
    try {
        return JSON.parse(el.dataset.dashboard || '{}');
    } catch {
        return null;
    }
}

function destroyCharts() {
    chartInstances.forEach((chart) => chart.destroy());
    chartInstances.clear();
}

function registerChart(id, chart) {
    if (chartInstances.has(id)) {
        chartInstances.get(id).destroy();
    }
    if (chart) {
        chartInstances.set(id, chart);
    }
}

function labelsFromSeries(data) {
    return data.map((d) => d.label || d.x || '');
}

function valuesFromSeries(data, key = 'y') {
    return data.map((d) => d[key] ?? d.y ?? d.connections ?? 0);
}

function buildLineChart(canvasId, label, data, color, options = {}) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || !data?.length) return null;

    return registerChart(canvasId, new Chart(canvas, {
        type: 'line',
        data: {
            labels: labelsFromSeries(data),
            datasets: [{
                label,
                data: valuesFromSeries(data),
                borderColor: color,
                backgroundColor: color + '33',
                tension: 0.25,
                fill: true,
                pointRadius: data.length > 80 ? 0 : 2,
                yAxisID: options.yAxisID || 'y',
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: {
                    display: true,
                    ticks: { maxTicksLimit: 8, maxRotation: 0 },
                },
                y: {
                    beginAtZero: true,
                    position: 'left',
                    title: options.yTitle ? { display: true, text: options.yTitle } : undefined,
                },
                ...(options.secondSeries ? {
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: options.y1Title || '' },
                    },
                } : {}),
            },
            plugins: {
                legend: { display: !!options.secondSeries },
            },
        },
    }));
}

function buildDualChart(canvasId, label1, data1, color1, label2, data2, color2) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || (!data1?.length && !data2?.length)) return null;

    const labels = labelsFromSeries(data1.length ? data1 : data2);

    return registerChart(canvasId, new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: label1,
                    data: valuesFromSeries(data1),
                    borderColor: color1,
                    backgroundColor: color1 + '22',
                    tension: 0.25,
                    fill: false,
                    pointRadius: 0,
                    yAxisID: 'y',
                },
                {
                    label: label2,
                    data: valuesFromSeries(data2),
                    borderColor: color2,
                    backgroundColor: color2 + '22',
                    tension: 0.25,
                    fill: false,
                    pointRadius: 0,
                    yAxisID: 'y1',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            scales: {
                x: { ticks: { maxTicksLimit: 8, maxRotation: 0 } },
                y: { beginAtZero: true, position: 'left', title: { display: true, text: '%' } },
                y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Load' } },
            },
            plugins: { legend: { display: true, position: 'bottom' } },
        },
    }));
}

function initCharts(dashboard) {
    destroyCharts();
    const charts = dashboard?.charts || {};
    buildDualChart('chart-cpu', 'CPU %', charts.cpu || [], '#e74c3c', 'Load 1m', charts.load || [], '#3498db');
    buildLineChart('chart-memory', 'RAM %', charts.memory, '#3498db');
    buildLineChart('chart-swap', 'Swap %', charts.swap, '#9b59b6');
    buildLineChart('chart-psi-io', 'PSI I/O avg10', charts.psi_io, '#f39c12');
    buildLineChart('chart-psi-mem', 'PSI RAM avg10', charts.psi_memory, '#e67e22');
    buildLineChart('chart-network', 'Connexions TCP', charts.network, '#2ecc71');
    buildLineChart('chart-thermal', 'Temp. max °C', charts.thermal, '#1abc9c');
}

async function pollDashboard() {
    const el = document.getElementById('crash-analyzer-dashboard');
    if (!el?.dataset.pollUrl) return;

    try {
        const res = await fetch(el.dataset.pollUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) return;
        const data = await res.json();
        el.dataset.dashboard = JSON.stringify(data);
        initCharts(data);
    } catch {
        // polling silencieux
    }
}

function boot() {
    const dashboard = readDashboard();
    if (dashboard) initCharts(dashboard);
    if (document.getElementById('crash-analyzer-dashboard')) {
        setInterval(pollDashboard, POLL_MS);
    }
}

document.addEventListener('DOMContentLoaded', boot);

document.addEventListener('livewire:navigated', boot);

if (window.Livewire) {
    document.addEventListener('livewire:init', () => {
        Livewire.hook('morph.updated', () => {
            if (document.getElementById('crash-analyzer-dashboard')) {
                initCharts(readDashboard());
            }
        });
    });
}
