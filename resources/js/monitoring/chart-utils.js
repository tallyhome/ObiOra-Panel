/**
 * Graphiques flotte (Vue) — axes datetime lisibles.
 */

function isDarkTheme() {
    return document.documentElement.getAttribute('data-obiora-theme') !== 'light';
}

function apexTheme() {
    return { mode: isDarkTheme() ? 'dark' : 'light' };
}

function apexGrid() {
    return {
        borderColor: isDarkTheme() ? 'rgba(148, 163, 184, 0.15)' : 'rgba(15, 23, 42, 0.08)',
        strokeDashArray: 3,
    };
}

function datetimeAxis(durationMs) {
    const format = durationMs <= 86400000 ? 'HH:mm' : durationMs <= 604800000 ? 'dd/MM HH:mm' : 'dd/MM';

    return {
        type: 'datetime',
        labels: {
            datetimeUTC: false,
            format,
            style: { fontSize: '11px' },
            rotate: durationMs <= 86400000 ? 0 : -35,
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
        tooltip: { enabled: true },
    };
}

export function isoPoints(samples, valueKey) {
    return (samples || [])
        .filter((sample) => sample?.at)
        .map((sample) => ({
            x: new Date(sample.at).getTime(),
            y: sample[valueKey] ?? 0,
        }));
}

export function chartDurationMs(points) {
    if (!points?.length) {
        return 86400000;
    }

    const xs = points.map((p) => p.x);

    return Math.max(3600000, Math.max(...xs) - Math.min(...xs));
}

export function fleetAreaChartOptions(name, points, color = '#0d6efd') {
    const durationMs = chartDurationMs(points);

    return {
        chart: { type: 'area', height: 280, toolbar: { show: false }, animations: { enabled: false } },
        theme: apexTheme(),
        series: [{ name, data: points }],
        xaxis: datetimeAxis(durationMs),
        yaxis: { labels: { formatter: (v) => `${Math.round(v)}` } },
        stroke: { curve: 'smooth', width: 2 },
        markers: { size: 0, hover: { size: 4 } },
        colors: [color],
        fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0.04 } },
        dataLabels: { enabled: false },
        grid: apexGrid(),
        tooltip: { theme: isDarkTheme() ? 'dark' : 'light', x: { format: 'dd/MM HH:mm' } },
    };
}

export function fleetLineChartOptions(name, points, color = '#198754', yMax = 100) {
    const durationMs = chartDurationMs(points);

    return {
        chart: { type: 'line', height: 280, toolbar: { show: false }, animations: { enabled: false } },
        theme: apexTheme(),
        series: [{ name, data: points }],
        xaxis: datetimeAxis(durationMs),
        yaxis: { min: 0, max: yMax, tickAmount: 5 },
        stroke: { curve: 'smooth', width: 2.5 },
        markers: { size: 0, hover: { size: 4 } },
        colors: [color],
        dataLabels: { enabled: false },
        grid: apexGrid(),
        tooltip: { theme: isDarkTheme() ? 'dark' : 'light', x: { format: 'dd/MM HH:mm' } },
    };
}
