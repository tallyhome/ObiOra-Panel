<template>
  <div class="monitoring-dashboard">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
      <div>
        <h1 class="h3 mb-1">Monitoring Obiora</h1>
        <p class="text-muted mb-0">
          Temps réel via SSE · graphiques · alertes · comparaison rapports
        </p>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge" :class="reverbConnected ? 'text-bg-primary' : (streamConnected ? 'text-bg-success' : 'text-bg-secondary')">
          {{ reverbConnected ? 'Reverb' : (streamConnected ? 'SSE Live' : 'Hors ligne') }}
        </span>
        <button type="button" class="btn btn-outline-secondary btn-sm" @click="fetchFleet" :disabled="loading">
          {{ loading ? '…' : 'Actualiser' }}
        </button>
      </div>
    </div>

    <div v-if="alerts.length" class="mb-4">
      <div
        v-for="alert in alerts"
        :key="alert.id"
        class="alert mb-2"
        :class="alertClass(alert.severity)"
        role="alert"
      >
        <strong>{{ alert.title }}</strong>
        <span v-if="alert.server"> — {{ alert.server }}</span>
        <div class="small">{{ alert.message }}</div>
        <button type="button" class="btn btn-sm btn-outline-dark mt-2" @click="dismissAlert(alert.id)">
          Marquer lu
        </button>
      </div>
    </div>

    <div class="row g-4 mb-4">
      <div class="col-md-3" v-for="card in summaryCards" :key="card.label">
        <div class="card obiora-card h-100">
          <div class="card-body">
            <div class="text-muted small">{{ card.label }}</div>
            <div class="h4 mb-0">{{ card.value }}</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card obiora-card mb-4">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead>
            <tr>
              <th>Serveur</th>
              <th>IP</th>
              <th>Ping ICMP</th>
              <th>Score</th>
              <th>Critiques</th>
              <th>Signature</th>
              <th>Rapport</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="server in servers" :key="server.id">
              <td class="fw-semibold">{{ server.name }}</td>
              <td class="small">{{ server.ip || '—' }}</td>
              <td>
                <span class="badge" :class="server.ping_success ? 'text-bg-success' : 'text-bg-danger'">
                  {{ server.ping_success ? (server.ping_ms + ' ms') : 'timeout' }}
                </span>
                <span class="text-muted small ms-1">{{ server.ping_method }}</span>
              </td>
              <td>
                <span v-if="server.score !== null" class="badge" :class="scoreClass(server.score)">
                  {{ server.score }}%
                </span>
                <span v-else class="text-muted">—</span>
              </td>
              <td>{{ server.critical ?? 0 }}</td>
              <td>
                <span v-if="server.signature_verified" class="badge text-bg-success">OK</span>
                <span v-else class="badge text-bg-secondary">—</span>
              </td>
              <td class="small text-muted">{{ server.report_at || 'Aucun' }}</td>
              <td>
                <button class="btn btn-sm btn-outline-primary" @click="selectServer(server)">
                  Détails
                </button>
              </td>
            </tr>
            <tr v-if="!servers.length">
              <td colspan="8" class="text-center text-muted py-4">
                Aucun serveur. Installez l'agent Obiora sur vos VPS.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="selectedServer" class="row g-4">
      <div class="col-lg-6">
        <div class="card obiora-card h-100">
          <div class="card-body">
            <h2 class="h6">Latence ping — {{ selectedServer.name }}</h2>
            <div ref="pingChart" style="min-height: 280px;"></div>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card obiora-card h-100">
          <div class="card-body">
            <h2 class="h6">Historique score Doctor</h2>
            <div ref="scoreChart" style="min-height: 280px;"></div>
          </div>
        </div>
      </div>
      <div class="col-12">
        <div class="card obiora-card">
          <div class="card-body">
            <h2 class="h6">Comparer deux rapports</h2>
            <div class="row g-2 align-items-end">
              <div class="col-md-4">
                <label class="form-label small">Rapport A</label>
                <select v-model="compareLeft" class="form-select form-select-sm">
                  <option value="">—</option>
                  <option v-for="r in scoreReports" :key="'l'+r.id" :value="r.id">
                    {{ r.at?.slice(0, 16) }} — {{ r.score }}%
                  </option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label small">Rapport B</label>
                <select v-model="compareRight" class="form-select form-select-sm">
                  <option value="">—</option>
                  <option v-for="r in scoreReports" :key="'r'+r.id" :value="r.id">
                    {{ r.at?.slice(0, 16) }} — {{ r.score }}%
                  </option>
                </select>
              </div>
              <div class="col-md-4">
                <button type="button" class="btn btn-primary btn-sm" @click="runCompare" :disabled="!compareLeft || !compareRight">
                  Comparer
                </button>
              </div>
            </div>
            <div v-if="compareResult" class="mt-3 small">
              <p class="mb-2">
                Δ score : <strong>{{ compareResult.score_delta }}</strong>
                ({{ compareResult.left_score }}% → {{ compareResult.right_score }}%)
              </p>
              <ul v-if="compareResult.modules?.length" class="mb-0">
                <li v-for="(m, idx) in compareResult.modules" :key="idx">
                  {{ m.module }} — {{ m.change }}
                  <span v-if="m.score_delta"> ({{ m.score_delta > 0 ? '+' : '' }}{{ m.score_delta }})</span>
                </li>
              </ul>
              <p v-else class="text-muted mb-0">Aucune différence module détectée.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card obiora-card mt-4">
      <div class="card-body">
        <h2 class="h6">Installation agent</h2>
        <pre class="small bg-dark text-light p-3 rounded mb-0"><code>OBIORA_PANEL_URL={{ panelUrl }} \
OBIORA_SERVER_ID=&lt;id&gt; \
OBIORA_AGENT_TOKEN=&lt;token&gt; \
OBIORA_SIGNING_KEY=&lt;cle_hex_64&gt; \
bash ObiOra-Doctor/install/install-agent.sh</code></pre>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ApexCharts from 'apexcharts';

const props = defineProps({
  fleetUrl: { type: String, required: true },
  streamUrl: { type: String, required: true },
  pingHistoryBase: { type: String, required: true },
  scoreHistoryBase: { type: String, required: true },
  compareBase: { type: String, default: '' },
  alertsReadBase: { type: String, default: '' },
  realtimeEnabled: { type: Boolean, default: false },
  panelUrl: { type: String, default: '' },
});

const servers = ref([]);
const alerts = ref([]);
const streamConnected = ref(false);
const reverbConnected = ref(false);
const loading = ref(true);
const selectedServer = ref(null);
const scoreReports = ref([]);
const compareLeft = ref('');
const compareRight = ref('');
const compareResult = ref(null);
const pingChart = ref(null);
const scoreChart = ref(null);
let pingChartInstance = null;
let scoreChartInstance = null;
let eventSource = null;

const summaryCards = computed(() => {
  const online = servers.value.filter((s) => s.ping_success).length;
  const critical = servers.value.reduce((sum, s) => sum + (s.critical || 0), 0);
  const avgScore = servers.value.filter((s) => s.score !== null);
  const mean = avgScore.length
    ? Math.round(avgScore.reduce((sum, s) => sum + s.score, 0) / avgScore.length)
    : '—';

  return [
    { label: 'Serveurs', value: servers.value.length },
    { label: 'En ligne (ping)', value: online },
    { label: 'Score moyen', value: mean === '—' ? mean : `${mean}%` },
    { label: 'Alertes', value: alerts.value.length },
  ];
});

function alertClass(severity) {
  if (severity === 'critical') return 'alert-danger';
  if (severity === 'warning') return 'alert-warning';
  return 'alert-info';
}

function scoreClass(score) {
  if (score >= 90) return 'text-bg-success';
  if (score >= 70) return 'text-bg-warning';
  return 'text-bg-danger';
}

async function fetchFleet() {
  loading.value = true;
  try {
    const response = await fetch(props.fleetUrl, { headers: { Accept: 'application/json' } });
    if (!response.ok) return;
    const data = await response.json();
    servers.value = data.servers || [];
    alerts.value = data.alerts || [];
  } finally {
    loading.value = false;
  }
}

function applyFleetPayload(data) {
  if (!data) return;
  servers.value = data.servers || [];
  alerts.value = data.alerts || [];
}

function connectReverb() {
  if (!props.realtimeEnabled || !window.ObioraEcho) return;
  window.ObioraEcho.private('obiora.monitoring')
    .listen('.monitoring.fleet', (payload) => {
      applyFleetPayload(payload);
      reverbConnected.value = true;
    });
  reverbConnected.value = true;
}

function connectStream() {
  if (props.realtimeEnabled && window.ObioraEcho) {
    connectReverb();
    return;
  }
  if (eventSource) eventSource.close();
  eventSource = new EventSource(props.streamUrl);
  eventSource.addEventListener('fleet', (event) => {
    try {
      const data = JSON.parse(event.data);
      applyFleetPayload(data);
      streamConnected.value = true;
    } catch {
      streamConnected.value = false;
    }
  });
  eventSource.onerror = () => {
    streamConnected.value = false;
  };
}

async function selectServer(server) {
  selectedServer.value = server;
  compareLeft.value = '';
  compareRight.value = '';
  compareResult.value = null;
  await nextTick();
  await loadCharts(server.id);
}

async function loadCharts(serverId) {
  const [pingRes, scoreRes, reportsRes] = await Promise.all([
    fetch(`${props.pingHistoryBase}/${serverId}/ping-history`),
    fetch(`${props.scoreHistoryBase}/${serverId}/score-history`),
    fetch(`${props.scoreHistoryBase}/${serverId}/diagnostics`),
  ]);
  const pingData = pingRes.ok ? await pingRes.json() : { samples: [] };
  const scoreData = scoreRes.ok ? await scoreRes.json() : { reports: [] };
  const reportsList = reportsRes.ok ? await reportsRes.json() : { reports: [] };
  scoreReports.value = (reportsList.reports || []).map((r, idx) => ({
    id: r.id,
    score: r.score,
    at: r.generated_at,
    key: idx,
  }));
  renderPingChart(pingData.samples || []);
  renderScoreChart(scoreData.reports || []);
}

async function runCompare() {
  if (!selectedServer.value || !compareLeft.value || !compareRight.value) return;
  const url = `${props.compareBase}/${selectedServer.value.id}/compare?left=${compareLeft.value}&right=${compareRight.value}`;
  const response = await fetch(url);
  compareResult.value = response.ok ? await response.json() : null;
}

async function dismissAlert(alertId) {
  if (!props.alertsReadBase) return;
  const response = await fetch(`${props.alertsReadBase}/${alertId}/read`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
    },
  });
  if (response.ok) {
    alerts.value = alerts.value.filter((a) => a.id !== alertId);
  }
}

function renderPingChart(samples) {
  if (!pingChart.value) return;
  if (pingChartInstance) pingChartInstance.destroy();
  pingChartInstance = new ApexCharts(pingChart.value, {
    chart: { type: 'area', height: 280, toolbar: { show: false } },
    series: [{ name: 'ms', data: samples.map((s) => s.latency_ms ?? 0) }],
    xaxis: { categories: samples.map((s) => s.at?.slice(11, 19) || '') },
    stroke: { curve: 'smooth' },
    colors: ['#0d6efd'],
  });
  pingChartInstance.render();
}

function renderScoreChart(reports) {
  if (!scoreChart.value) return;
  if (scoreChartInstance) scoreChartInstance.destroy();
  scoreChartInstance = new ApexCharts(scoreChart.value, {
    chart: { type: 'line', height: 280, toolbar: { show: false } },
    series: [{ name: 'Score %', data: reports.map((r) => r.score) }],
    xaxis: { categories: reports.map((r) => r.at?.slice(0, 16) || '') },
    yaxis: { min: 0, max: 100 },
    colors: ['#198754'],
  });
  scoreChartInstance.render();
}

onMounted(() => {
  fetchFleet();
  connectStream();
});

onBeforeUnmount(() => {
  if (eventSource) eventSource.close();
  if (pingChartInstance) pingChartInstance.destroy();
  if (scoreChartInstance) scoreChartInstance.destroy();
});

watch(servers, () => {
  if (selectedServer.value) {
    const updated = servers.value.find((s) => s.id === selectedServer.value.id);
    if (updated) selectedServer.value = updated;
  }
});
</script>
