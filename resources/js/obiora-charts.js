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
        x: { show: true },
    };
}

export function obioraApexGrid() {
    const dark = obioraIsDarkTheme();

    return {
        borderColor: dark ? 'rgba(148, 163, 184, 0.15)' : 'rgba(15, 23, 42, 0.08)',
        strokeDashArray: 3,
    };
}

export function obioraApexChartColors() {
    return {
        foreColor: obioraIsDarkTheme() ? '#94a3b8' : '#64748b',
    };
}

/**
 * Graphique temps de réponse avec points (style Pinguzo).
 */
export function obioraRenderResponseChart(el, categories, values, options = {}) {
    if (!el || typeof ApexCharts === 'undefined') {
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
        xaxis: {
            categories: categories || [],
            labels: { show: false },
            axisBorder: { show: false },
        },
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
        xaxis: { categories: categories || [], labels: { show: false } },
        yaxis: {
            min: options.yMin ?? 0,
            max: options.yMax ?? 100,
            labels: { formatter: (v) => `${v}%` },
        },
        stroke: { curve: 'smooth', width: 2 },
        markers: {
            size: 3,
            strokeWidth: 0,
            colors: [color],
            hover: { size: 5 },
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
    if (!el || typeof ApexCharts === 'undefined') return null;
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
        xaxis: { categories: categories || [], labels: { show: false } },
        stroke: { curve: 'smooth', width: 2 },
        markers: { size: 3, hover: { size: 5 } },
        dataLabels: { enabled: false },
        grid: obioraApexGrid(),
        tooltip: obioraApexTooltip(),
    });
    chart.render();
    return chart;
}

export function obioraParseChartData(el) {
    if (!el) return {};
    try {
        return JSON.parse(el.getAttribute('data-chart') || '{}');
    } catch {
        return {};
    }
}
