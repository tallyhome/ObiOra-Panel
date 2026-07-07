import '../echo.js';
import { createApp } from 'vue';
import MonitoringDashboard from './MonitoringDashboard.vue';

const root = document.getElementById('monitoring-app');

if (root) {
    createApp(MonitoringDashboard, {
        fleetUrl: root.dataset.fleetUrl,
        streamUrl: root.dataset.streamUrl,
        pingHistoryBase: root.dataset.pingHistoryUrl,
        scoreHistoryBase: root.dataset.scoreHistoryUrl,
        compareBase: root.dataset.compareBaseUrl,
        alertsReadBase: root.dataset.alertsReadUrl,
        panelUrl: root.dataset.panelUrl,
        realtimeEnabled: root.dataset.realtimeEnabled === '1',
    }).mount(root);
}
