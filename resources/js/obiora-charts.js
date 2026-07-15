/**
 * Helpers ApexCharts — thème sombre/clair + graphiques monitoring type Pinguzo.
 */

function obioraIsDarkTheme() {
    return document.documentElement.getAttribute('data-obiora-theme') !== 'light';
}

export function obioraApexTheme() {
    const dark = obioraIsDarkTheme();

    return {
        mode: dark ? 'dark' : 'light',
        palette: 'palette1',
        monochrome: { enabled: false },
    };
}

export function obioraApexTooltip() {
    const dark = obioraIsDarkTheme();

    return {
        theme: dark ? 'dark' : 'light',
        style: {
            fontSize: '12px',
        },
        x: { show: true, format: 'dd/MM HH:mm' },
    };
}

export function obioraApexGrid() {
    const dark = obioraIsDarkTheme();

    return {
        borderColor: dark ? 'rgba(148, 163, 184, 0.15)' : 'rgba(15, 23, 42, 0.08)',
        strokeDashArray: 3,
        padding: { left: 8, right: 8 },
    };
}

export function obioraApexChartColors() {
    return {
        foreColor: obioraIsDarkTheme() ? '#94a3b8' : '#64748b',
    };
}

function obioraRenderEmptyChart(el, message = 'Aucune donnée sur cette période') {
    if (!el) {
        return false;
    }

    el.innerHTML = `<div class="d-flex align-items-center justify-content-center h-100 text-muted small px-3 text-center" style="min-height:180px;">${message}</div>`;

    return false;
}

function obioraHasSeriesData(categories, values) {
    if (!categories?.length || !values?.length) {
        return false;
    }

    return values.some((value) => value !== null && value !== undefined);
}

function obioraHasMultiSeriesData(categories, series) {
    if (!categories?.length || !series?.length) {
        return false;
    }

    return series.some((row) => (row.data || []).some((value) => value !== null && value !== undefined));
}

/**
 * Axe catégories avec repères espacés (évite le bloc blanc illisible).
 */
export function obioraCategoryAxisOptions(categories, options = {}) {
    const list = categories || [];
    const maxTicks = options.maxTicks ?? 7;
    const len = list.length;
    const step = len > 1 ? Math.max(1, Math.floor((len - 1) / Math.max(1, maxTicks - 1))) : 1;
    const tickIndices = new Set();

    for (let i = 0; i < len; i += step) {
        tickIndices.add(i);
    }

    if (len > 0) {
        tickIndices.add(len - 1);
    }

    return {
        categories: list,
        tickAmount: Math.min(maxTicks, len),
        labels: {
            show: options.show !== false,
            rotate: options.rotate ?? (len > 18 ? -35 : 0),
            hideOverlappingLabels: true,
            trim: true,
            maxHeight: 56,
            style: { fontSize: '11px' },
            formatter(value, _timestamp, opts) {
                const idx = opts?.i ?? opts?.dataPointIndex;

                if (typeof idx === 'number' && tickIndices.has(idx)) {
                    return value;
                }

                return '';
            },
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
        tooltip: { enabled: true },
    };
}

/**
 * Graphique temps de réponse avec points (style Pinguzo).
 */
export function obioraRenderResponseChart(el, categories, values, options = {}) {
    if (!el || typeof ApexCharts === 'undefined') {
        return null;
    }

    if (!obioraHasSeriesData(categories, values)) {
        obioraRenderEmptyChart(el, options.emptyMessage || 'Aucun check enregistré sur la période');

        return null;
    }

    el.innerHTML = '';

    const chart = new ApexCharts(el, {
        chart: {
            type: 'area',
            height: options.height || 260,
            toolbar: { show: false },
            animations: { enabled: false },
            ...obioraApexChartColors(),
        },
        theme: obioraApexTheme(),
        series: [{ name: options.seriesName || 'Response ms', data: values || [] }],
        xaxis: obioraCategoryAxisOptions(categories || [], { maxTicks: 8 }),
        yaxis: {
            labels: {
                formatter: (v) => (options.ySuffix ? `${v} ${options.ySuffix}` : `${v} ms`),
            },
        },
        stroke: { curve: 'smooth', width: 2 },
        markers: {
            size: 4,
            strokeWidth: 2,
            strokeColors: '#3b82f6',
            colors: ['#3b82f6'],
            hover: { size: 6 },
        },
        colors: ['#3b82f6'],
        fill: {
            type: 'gradient',
            gradient: { opacityFrom: 0.35, opacityTo: 0.05 },
        },
        dataLabels: { enabled: false },
        grid: obioraApexGrid(),
        tooltip: obioraApexTooltip(),
    });

    chart.render();

    return chart;
}

/**
 * Graphique area % (CPU, memory…).
 */
export function obioraRenderAreaChart(el, title, categories, values, color, options = {}) {
    if (!el || typeof ApexCharts === 'undefined') {
        return null;
    }

    if (!obioraHasSeriesData(categories, values)) {
        obioraRenderEmptyChart(el, options.emptyMessage || 'Aucune métrique agent — vérifiez obiora-agent.service');

        return null;
    }

    el.innerHTML = '';

    const chart = new ApexCharts(el, {
        chart: {
            type: 'area',
            height: options.height || 220,
            toolbar: { show: false },
            animations: { enabled: false },
            ...obioraApexChartColors(),
        },
        theme: obioraApexTheme(),
        series: [{ name: title, data: values || [] }],
        xaxis: obioraCategoryAxisOptions(categories || [], { maxTicks: options.maxTicks ?? 6 }),
        yaxis: {
            min: options.yMin ?? 0,
            max: options.yMax ?? 100,
            labels: { formatter: (v) => `${Math.round(v * 10) / 10}%` },
        },
        stroke: { curve: 'smooth', width: 2 },
        markers: {
            size: 0,
            hover: { size: 4 },
        },
        colors: [color],
        fill: {
            type: 'gradient',
            gradient: { opacityFrom: 0.35, opacityTo: 0.05 },
        },
        dataLabels: { enabled: false },
        grid: obioraApexGrid(),
        tooltip: obioraApexTooltip(),
    });

    chart.render();

    return chart;
}

export function obioraRenderLineChart(el, categories, series, options = {}) {
    if (!el || typeof ApexCharts === 'undefined') {
        return null;
    }

    if (!obioraHasMultiSeriesData(categories, series)) {
        obioraRenderEmptyChart(el, options.emptyMessage || 'Aucune donnée sur la période');

        return null;
    }

    el.innerHTML = '';

    const chart = new ApexCharts(el, {
        chart: {
            type: 'line',
            height: options.height || 220,
            toolbar: { show: false },
            animations: { enabled: false },
            ...obioraApexChartColors(),
        },
        theme: obioraApexTheme(),
        series: series || [],
        xaxis: obioraCategoryAxisOptions(categories || [], { maxTicks: options.maxTicks ?? 6 }),
        yaxis: options.yaxis || {},
        stroke: { curve: 'smooth', width: 2 },
        markers: { size: 0, hover: { size: 4 } },
        legend: options.legend || { show: false },
        colors: options.colors || ['#3b82f6', '#22c55e', '#f59e0b'],
        dataLabels: { enabled: false },
        grid: obioraApexGrid(),
        tooltip: obioraApexTooltip(),
    });

    chart.render();

    return chart;
}

/**
 * Load average — trois courbes lisses avec légende.
 */
export function obioraRenderLoadChart(el, categories, series, options = {}) {
    if (!el || typeof ApexCharts === 'undefined') {
        return null;
    }

    if (!obioraHasMultiSeriesData(categories, series)) {
        obioraRenderEmptyChart(el, options.emptyMessage || 'Load average indisponible — agent métriques requis');

        return null;
    }

    el.innerHTML = '';

    const numeric = (series || []).flatMap((row) => (row.data || []).filter((v) => v !== null && v !== undefined));
    const maxVal = numeric.length ? Math.max(...numeric) : 1;

    const chart = new ApexCharts(el, {
        chart: {
            type: 'line',
            height: options.height || 220,
            toolbar: { show: false },
            animations: { enabled: false },
            ...obioraApexChartColors(),
        },
        theme: obioraApexTheme(),
        series: series || [],
        xaxis: obioraCategoryAxisOptions(categories || [], { maxTicks: 6 }),
        yaxis: {
            min: 0,
            max: Math.max(2, Math.ceil(maxVal * 1.25 * 10) / 10),
            tickAmount: 4,
            labels: { formatter: (v) => Number(v).toFixed(1) },
        },
        stroke: { curve: 'smooth', width: 2.5 },
        markers: { size: 0, hover: { size: 4 } },
        legend: {
            show: true,
            position: 'top',
            horizontalAlign: 'right',
            fontSize: '12px',
            markers: { width: 10, height: 10, radius: 2 },
        },
        colors: ['#3b82f6', '#22c55e', '#f59e0b'],
        dataLabels: { enabled: false },
        grid: obioraApexGrid(),
        tooltip: {
            ...obioraApexTooltip(),
            y: { formatter: (v) => Number(v).toFixed(2) },
        },
    });

    chart.render();

    return chart;
}

export function obioraParseChartData(el) {
    if (!el) {
        return {};
    }

    try {
        return JSON.parse(el.getAttribute('data-chart') || '{}');
    } catch {
        return {};
    }
}
