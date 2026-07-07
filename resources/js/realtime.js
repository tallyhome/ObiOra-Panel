function bindRealtimeDashboard() {
    if (!window.ObioraEcho || !window.obioraRealtime?.enabled) {
        return;
    }

    const serverId = window.obioraRealtime.serverId;
    if (!serverId) {
        return;
    }

    window.ObioraEcho.private(`obiora.server.${serverId}`)
        .listen('.dashboard.metrics', (payload) => {
            window.Livewire?.dispatch('realtime-dashboard-metrics', {
                metrics: payload.metrics ?? {},
                services: payload.services ?? [],
            });
        })
        .listen('.service.state', (payload) => {
            window.Livewire?.dispatch('realtime-service-state', payload);
        });

    window.ObioraEcho.private(`obiora.progress.${serverId}.marketplace`)
        .listen('.progress.updated', (payload) => {
            window.Livewire?.dispatch('realtime-progress', payload);
        });

    window.ObioraEcho.private('obiora.monitoring')
        .listen('.monitoring.fleet', (payload) => {
            window.dispatchEvent(new CustomEvent('obiora-monitoring-fleet', { detail: payload }));
        });
}

document.addEventListener('livewire:init', bindRealtimeDashboard);
