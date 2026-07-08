import Chart from 'chart.js/auto';

const POLL_MS = 5000;

function readDashboard() {
    const el = document.getElementById('crash-analyzer-dashboard');
    if (!el) return null;
    try {
        return JSON.parse(el.dataset.dashboard || '{}');
    } catch {
        return null;
    }
}

function buildLineChart(canvasId, label, data, color) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || !data?.length) return null;

    return new Chart(canvas, {
        type: 'line',
        data: {
            labels: data.map((d) => d.x),
            datasets: [{
                label,
                data: data.map((d) => d.y ?? d.connections ?? 0),
                borderColor: color,
                backgroundColor: color + '33',
                tension: 0.3,
                fill: true,
                pointRadius: 0,
            }],
        },
        options: {
            responsive: true,
            animation: false,
            scales: {
                x: { display: data.length < 40, ticks: { maxTicksLimit: 8 } },
                y: { beginAtZero: true },
            },
            plugins: { legend: { display: false } },
        },
    });
}

function initCharts(dashboard) {
    const charts = dashboard?.charts || {};
    buildLineChart('chart-cpu', 'CPU %', charts.cpu, '#e74c3c');
    buildLineChart('chart-memory', 'RAM %', charts.memory, '#3498db');
    buildLineChart('chart-disk', 'IO Wait %', charts.disk, '#f39c12');
    buildLineChart('chart-network', 'Connexions TCP', charts.network, '#2ecc71');
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
        document.querySelectorAll('canvas').forEach((c) => {
            const chart = Chart.getChart(c);
            if (chart) chart.destroy();
        });
        initCharts(data);
    } catch {
        // polling silencieux
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const dashboard = readDashboard();
    if (dashboard) initCharts(dashboard);
    setInterval(pollDashboard, POLL_MS);
});
