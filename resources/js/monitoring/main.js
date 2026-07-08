import '../echo.js';
import { createApp } from 'vue';
import { obioraCopyFromButton, obioraCopyText } from '../copy.js';
import MonitoringDashboard from './MonitoringDashboard.vue';

const root = document.getElementById('monitoring-app');

function parseJsonDataset(value, fallback) {
    if (!value) return fallback;
    try {
        return JSON.parse(value);
    } catch {
        return fallback;
    }
}

if (root) {
    window.obioraCopyText = obioraCopyText;
    window.obioraCopyFromButton = obioraCopyFromButton;

    createApp(MonitoringDashboard, {
        fleetUrl: root.dataset.fleetUrl,
        streamUrl: root.dataset.streamUrl,
        pingHistoryBase: root.dataset.pingHistoryUrl,
        scoreHistoryBase: root.dataset.scoreHistoryUrl,
        compareBase: root.dataset.compareBaseUrl,
        alertsReadBase: root.dataset.alertsReadUrl,
        installBase: root.dataset.installBaseUrl,
        diagnosticsLatestBase: root.dataset.diagnosticsLatestUrl,
        doctorUrl: root.dataset.doctorUrl,
        panelUrl: root.dataset.panelUrl,
        realtimeEnabled: root.dataset.realtimeEnabled === '1',
        initialFleet: parseJsonDataset(root.dataset.initialFleet, []),
        initialAlerts: parseJsonDataset(root.dataset.initialAlerts, []),
    }).mount(root);
}
