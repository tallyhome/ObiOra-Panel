/**
 * Graphiques monitoring (serveur + moniteur) — init centralisé après morph Livewire.
 */
import {
    obioraRenderAreaChart,
    obioraRenderLineChart,
    obioraRenderLoadChart,
    obioraRenderResponseChart,
    obioraParseChartData,
} from './obiora-charts';

const chartInstances = new Map();

function destroyChart(el) {
    if (!el?.id) {
        return;
    }

    const existing = chartInstances.get(el.id);

    if (existing) {
        existing.destroy();
        chartInstances.delete(el.id);
    }
}

function storeChart(el, chart) {
    if (el?.id && chart) {
        chartInstances.set(el.id, chart);
    }
}

function renderArea(el, title, categories, values, color, options = {}) {
    if (!el) {
        return;
    }

    destroyChart(el);
    const chart = obioraRenderAreaChart(el, title, categories, values, color, options);

    storeChart(el, chart);
}

function renderLine(el, categories, series, options = {}) {
    if (!el) {
        return;
    }

    destroyChart(el);
    const chart = obioraRenderLineChart(el, categories, series, options);

    storeChart(el, chart);
}

function renderLoad(el, categories, series, options = {}) {
    if (!el) {
        return;
    }

    destroyChart(el);
    const chart = obioraRenderLoadChart(el, categories, series, options);

    storeChart(el, chart);
}

export function obioraInitMonitorCharts() {
    const el = document.getElementById('monitor-response-chart');

    if (!el || !window.ApexCharts) {
        return;
    }

    const data = obioraParseChartData(el);
    destroyChart(el);
    const chart = obioraRenderResponseChart(el, data.categories || [], data.values || [], { height: 260 });

    storeChart(el, chart);
}

export function obioraInitServerMetricsCharts() {
    if (!window.ApexCharts) {
        return;
    }

    const holder = document.getElementById('server-metrics-chart-data');

    if (!holder) {
        return;
    }

    const payload = obioraParseChartData(holder);
    const s = payload.series || payload;

    renderArea(document.getElementById('chart-cpu'), 'CPU', s.cpu?.categories || [], s.cpu?.values || [], '#3b82f6');
    renderArea(document.getElementById('chart-memory'), 'Memory', s.memory?.categories || [], s.memory?.values || [], '#22c55e');
    renderArea(document.getElementById('chart-disk'), 'Disk', s.disk?.categories || [], s.disk?.values || [], '#f59e0b');
    renderLoad(document.getElementById('chart-load'), s.load?.categories || [], s.load?.series || []);

    renderArea(document.getElementById('chart-cpu-tab'), 'CPU', s.cpu?.categories || [], s.cpu?.values || [], '#3b82f6', { height: 280 });
    renderArea(document.getElementById('chart-steal-tab'), 'CPU Steal', s.cpu_steal?.categories || [], s.cpu_steal?.values || [], '#ef4444');
    renderArea(document.getElementById('chart-memory-tab'), 'Memory', s.memory?.categories || [], s.memory?.values || [], '#22c55e', { height: 280 });
    renderArea(document.getElementById('chart-swap-tab'), 'Swap', s.swap?.categories || [], s.swap?.values || [], '#a855f7');
    renderArea(document.getElementById('chart-disk-tab'), 'Disk', s.disk?.categories || [], s.disk?.values || [], '#f59e0b', { height: 280 });

    const net = payload.network || {};
    renderLine(document.getElementById('chart-network-rxtx'), net.categories || [], [
        { name: 'RX kbps', data: net.rx_kbps || [] },
        { name: 'TX kbps', data: net.tx_kbps || [] },
    ], { height: 280 });
    renderLine(document.getElementById('chart-network-tcp'), net.categories || [], [
        { name: 'TCP', data: net.tcp_connections || [] },
    ]);
}

function waitForApexAndInit(initFn, attempts = 0) {
    if (window.ApexCharts) {
        initFn();

        return;
    }

    if (attempts < 60) {
        setTimeout(() => waitForApexAndInit(initFn, attempts + 1), 100);
    }
}

export function obioraBootMonitoringCharts() {
    waitForApexAndInit(() => {
        obioraInitMonitorCharts();
        obioraInitServerMetricsCharts();
    });
}

export function obioraRegisterMonitoringChartHooks() {
    document.addEventListener('livewire:navigated', obioraBootMonitoringCharts);

    document.addEventListener('livewire:init', () => {
        Livewire.hook('morph.updated', () => {
            waitForApexAndInit(() => {
                obioraInitMonitorCharts();
                obioraInitServerMetricsCharts();
            });
        });
    });
}
