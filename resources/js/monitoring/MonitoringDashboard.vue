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

    <div v-if="fetchError" class="alert alert-danger mb-4" role="alert">
      {{ fetchError }}
      <span class="small d-block mt-1">Les données ci-dessous proviennent du chargement serveur. Cliquez Actualiser après avoir corrigé le panel (php-fpm / nginx).</span>
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
              <th>Doctor</th>
              <th>Critiques</th>
              <th>Avert.</th>
              <th>Vu</th>
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
              <td>
                <span v-if="server.doctor_status" class="badge" :class="doctorStatusClass(server.doctor_status)">
                  {{ doctorStatusLabel(server.doctor_status) }}
                </span>
                <span v-else class="text-muted">—</span>
              </td>
              <td>
                <span v-if="server.critical" class="badge text-bg-danger">{{ server.critical }}</span>
                <span v-else class="text-muted">0</span>
              </td>
              <td>
                <span v-if="server.warnings" class="badge text-bg-warning">{{ server.warnings }}</span>
                <span v-else class="text-muted">0</span>
              </td>
              <td class="small text-muted">{{ server.last_seen || '—' }}</td>
              <td class="small text-muted">{{ server.report_at || 'Aucun' }}</td>
              <td>
                <button class="btn btn-sm btn-outline-primary" @click="selectServer(server)">
                  Détails
                </button>
              </td>
            </tr>
            <tr v-if="!servers.length">
              <td colspan="10" class="text-center text-muted py-4">
                Aucun serveur. Installez l'agent Obiora sur vos VPS.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="selectedServer" class="row g-4">
      <div class="col-12">
        <div class="card obiora-card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
              <h2 class="h6 mb-0">Dernier rapport Doctor — {{ selectedServer.name }}</h2>
              <span v-if="latestReportLoading" class="small text-muted">Chargement…</span>
            </div>

            <div v-if="latestReport" class="row g-3 mb-3">
              <div class="col-md-3">
                <div class="text-muted small">Score</div>
                <span class="badge fs-6" :class="scoreClass(latestReport.score)">{{ latestReport.score }}%</span>
              </div>
              <div class="col-md-3">
                <div class="text-muted small">Statut</div>
                <span class="badge" :class="doctorStatusClass(latestReport.status)">
                  {{ doctorStatusLabel(latestReport.status) }}
                </span>
              </div>
              <div class="col-md-3">
                <div class="text-muted small">Hostname</div>
                <div>{{ latestReport.hostname || selectedServer.hostname || '—' }}</div>
              </div>
              <div class="col-md-3">
                <div class="text-muted small">Agent</div>
                <div>{{ latestReport.doctor_version || '—' }}</div>
              </div>
              <div class="col-md-4">
                <div class="text-muted small">Généré</div>
                <div class="small">{{ formatDate(latestReport.generated_at) }}</div>
              </div>
              <div class="col-md-4">
                <div class="text-muted small">Signature</div>
                <span v-if="latestReport.signature_verified" class="badge text-bg-success">Vérifiée</span>
                <span v-else class="badge text-bg-secondary">Non vérifiée</span>
              </div>
              <div class="col-md-4" v-if="latestReport.support_mode">
                <div class="text-muted small">Mode</div>
                <span class="badge text-bg-info">Support</span>
              </div>
            </div>
            <p v-else-if="!latestReportLoading" class="text-muted small mb-3">
              Aucun rapport Doctor reçu pour ce serveur.
            </p>

            <div v-if="latestReport?.critical_findings?.length" class="alert alert-danger py-2 mb-3">
              <strong class="small">Findings critiques</strong>
              <ul class="small mb-0 mt-1">
                <li v-for="(f, idx) in latestReport.critical_findings" :key="'c'+idx">
                  <strong>{{ f.module }}</strong> — {{ f.title }}
                  <span v-if="f.details" class="text-muted">({{ f.details }})</span>
                </li>
              </ul>
            </div>

            <div v-if="moduleWarnings.length" class="alert alert-warning py-2 mb-3">
              <strong class="small">Avertissements</strong>
              <ul class="small mb-0 mt-1">
                <li v-for="(w, idx) in moduleWarnings" :key="'w'+idx">{{ w }}</li>
              </ul>
            </div>

            <div v-if="latestReport?.results?.length" class="table-responsive">
              <table class="table table-sm mb-0 align-middle">
                <thead>
                  <tr>
                    <th>Module</th>
                    <th>Statut</th>
                    <th>Détails</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="mod in latestReport.results" :key="mod.module">
                    <td class="fw-medium">{{ mod.module }}</td>
                    <td>
                      <span class="badge" :class="moduleStatusClass(mod.status)">
                        {{ mod.status || '—' }}
                      </span>
                    </td>
                    <td class="small text-muted">{{ formatModuleDetails(mod) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
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
              <ul v-if="compareResult.modules?.length" class="mb-2">
                <li v-for="(m, idx) in compareResult.modules" :key="idx">
                  {{ m.module }} — {{ m.change }}
                  <span v-if="m.score_delta"> ({{ m.score_delta > 0 ? '+' : '' }}{{ m.score_delta }})</span>
                </li>
              </ul>
              <div v-if="compareResult.metrics?.length" class="mt-2">
                <strong class="small">Métriques modifiées</strong>
                <ul class="mb-0 mt-1">
                  <li v-for="(m, idx) in compareResult.metrics" :key="'m'+idx">
                    {{ m.module }}.{{ m.metric }} : {{ m.left ?? '—' }} → {{ m.right ?? '—' }}
                  </li>
                </ul>
              </div>
              <p v-if="!compareResult.modules?.length && !compareResult.metrics?.length" class="text-muted mb-0">
                Aucune différence détectée.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card obiora-card mt-4">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
          <h2 class="h6 mb-0">Installation agent Doctor</h2>
          <a v-if="doctorUrl" :href="doctorUrl" class="btn btn-outline-secondary btn-sm">Page Doctor & Suite</a>
        </div>
        <p class="small text-muted mb-3">
          Commande prête à copier pour le serveur
          <strong>{{ installServerName || 'sélectionné' }}</strong>
          — sans dépôt ObiOra-Doctor sur la machine cible.
        </p>
        <div v-if="installLoading" class="text-muted small">Chargement…</div>
        <template v-else-if="installRemote">
          <p class="small fw-medium mb-1">Sur un VPS distant (root) :</p>
          <div class="obiora-copy-block mb-3">
            <pre class="small mb-0 obiora-copy-text">{{ installRemote }}</pre>
            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="obioraCopyFromButton(this)">
              Copier
            </button>
          </div>
          <p class="small fw-medium mb-1">Sur le serveur du panel (local) :</p>
          <div class="obiora-copy-block">
            <pre class="small mb-0 obiora-copy-text">{{ installLocal }}</pre>
            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="obioraCopyFromButton(this)">
              Copier
            </button>
          </div>
        </template>
        <p v-else class="small text-muted mb-0">
          Sélectionnez un serveur dans le tableau ou ajoutez-en un dans Serveurs.
        </p>
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
  installBase: { type: String, default: '' },
  diagnosticsLatestBase: { type: String, default: '' },
  doctorUrl: { type: String, default: '' },
  realtimeEnabled: { type: Boolean, default: false },
  panelUrl: { type: String, default: '' },
  initialFleet: { type: Array, default: () => [] },
  initialAlerts: { type: Array, default: () => [] },
});

const fetchError = ref('');

const installLocal = ref('');
const installRemote = ref('');
const installServerName = ref('');
const installLoading = ref(false);

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
const latestReport = ref(null);
const latestReportLoading = ref(false);
const pingChart = ref(null);
const scoreChart = ref(null);
let pingChartInstance = null;
let scoreChartInstance = null;
let eventSource = null;

const summaryCards = computed(() => {
  const online = servers.value.filter((s) => s.ping_success).length;
  const critical = servers.value.reduce((sum, s) => sum + (s.critical || 0), 0);
  const warnings = servers.value.reduce((sum, s) => sum + (s.warnings || 0), 0);
  const avgScore = servers.value.filter((s) => s.score !== null);
  const mean = avgScore.length
    ? Math.round(avgScore.reduce((sum, s) => sum + s.score, 0) / avgScore.length)
    : '—';

  return [
    { label: 'Serveurs', value: servers.value.length },
    { label: 'En ligne (ping)', value: online },
    { label: 'Score moyen', value: mean === '—' ? mean : `${mean}%` },
    { label: 'Critiques / Avert.', value: `${critical} / ${warnings}` },
  ];
});

const moduleWarnings = computed(() => {
  if (!latestReport.value?.results) return [];
  const items = [];
  for (const mod of latestReport.value.results) {
    if (mod.status === 'warning') {
      items.push(`${mod.module} : statut warning`);
    }
    for (const f of mod.findings ?? []) {
      if (f.level === 'WARNING') {
        items.push(`${mod.module} — ${f.title}${f.details ? ` (${f.details})` : ''}`);
      }
    }
  }
  return items;
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

function doctorStatusClass(status) {
  if (status === 'ok') return 'text-bg-success';
  if (status === 'warning') return 'text-bg-warning';
  if (status === 'critical') return 'text-bg-danger';
  return 'text-bg-secondary';
}

function doctorStatusLabel(status) {
  if (status === 'ok') return 'OK';
  if (status === 'warning') return 'Attention';
  if (status === 'critical') return 'Critique';
  return status;
}

function moduleStatusClass(status) {
  if (status === 'ok') return 'text-bg-success';
  if (status === 'warning') return 'text-bg-warning';
  if (status === 'critical' || status === 'error') return 'text-bg-danger';
  return 'text-bg-secondary';
}

function formatDate(iso) {
  if (!iso) return '—';
  try {
    return new Date(iso).toLocaleString('fr-FR');
  } catch {
    return iso;
  }
}

function formatModuleDetails(mod) {
  const parts = [];
  if (mod.load) parts.push(`Charge ${mod.load}`);
  if (mod.mem_kb != null && mod.mem_total_kb) {
    const pct = Math.round((mod.mem_kb / mod.mem_total_kb) * 100);
    parts.push(`RAM ${pct}%`);
  }
  if (mod.root_used_pct != null) parts.push(`Disque / ${mod.root_used_pct}%`);
  if (mod.failed_units != null) parts.push(`systemd failed: ${mod.failed_units}`);
  if (mod.score != null) parts.push(`score module ${mod.score}`);
  if (mod.metrics && typeof mod.metrics === 'object') {
    Object.entries(mod.metrics).forEach(([k, v]) => parts.push(`${k}: ${v}`));
  }
  for (const f of mod.findings ?? []) {
    if (f.level !== 'CRITICAL') {
      parts.push(`${f.level}: ${f.title}`);
    }
  }
  return parts.join(' · ') || '—';
}

async function fetchFleet() {
  loading.value = true;
  fetchError.value = '';
  try {
    const response = await fetch(props.fleetUrl, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
    if (!response.ok) {
      fetchError.value = `API monitoring indisponible (HTTP ${response.status}).`;
      return;
    }
    const data = await response.json();
    servers.value = data.servers || [];
    alerts.value = data.alerts || [];
  } catch {
    fetchError.value = 'Impossible de joindre l\'API monitoring (panel en 502 ou réseau).';
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
  latestReport.value = null;
  await Promise.all([
    loadInstallCommand(server.id, server.name),
    loadLatestReport(server.id),
  ]);
  await nextTick();
  await loadCharts(server.id);
}

async function loadLatestReport(serverId) {
  if (!props.diagnosticsLatestBase) return;
  latestReportLoading.value = true;
  try {
    const response = await fetch(`${props.diagnosticsLatestBase}/${serverId}/diagnostics/latest`, {
      headers: { Accept: 'application/json' },
    });
    latestReport.value = response.ok ? await response.json() : null;
  } catch {
    latestReport.value = null;
  } finally {
    latestReportLoading.value = false;
  }
}

async function loadInstallCommand(serverId, serverName) {
  if (!props.installBase) return;
  installLoading.value = true;
  installLocal.value = '';
  installRemote.value = '';
  installServerName.value = serverName || '';
  try {
    const response = await fetch(`${props.installBase}/${serverId}/install-command`, {
      headers: { Accept: 'application/json' },
    });
    if (!response.ok) return;
    const data = await response.json();
    installLocal.value = data.local || '';
    installRemote.value = data.remote || '';
    installServerName.value = data.server_name || serverName || '';
  } finally {
    installLoading.value = false;
  }
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
  if (props.initialFleet?.length) {
    servers.value = props.initialFleet;
  }
  if (props.initialAlerts?.length) {
    alerts.value = props.initialAlerts;
  }
  if (servers.value.length > 0) {
    loadInstallCommand(servers.value[0].id, servers.value[0].name);
  }
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
