# CrashHunter Enterprise

Diagnostic Black Box pour freezes sur serveurs dédiés OVH (AlmaLinux 10, Virtualizor, KVM).

## Objectif

Répondre après un reboot : **Pourquoi le serveur s'est figé ?**

Même sans kernel panic, sans logs, après hard reset OVH/IPMI.

## Architecture plugins

Chaque collecteur est un plugin indépendant dans `crashhunter/plugins/collectors/` :

| Plugin | Rôle |
|--------|------|
| cpu, memory, scheduler | CPU, charge, runqueue |
| swap, oom, numa, hugepages | Mémoire avancée |
| disk, lvm, xfs | Stockage et filesystem |
| libvirt, qemu, virtualizor | Virtualisation |
| ssh, ping, responsiveness | Réactivité système |
| dstate | Investigation processus D |
| journal, dmesg, watchdog | Noyau et logs |
| ipmi, smart, raid, temperature, pci | Matériel |
| ebpf (optionnel) | Introspection noyau via bpftrace |

Activation/désactivation via `config.yaml` → `collectors.enabled`.

## Configuration

`/opt/crashhunter/config.yaml` — intervalle (1s ou 5s), ring auto (60 min), seuils, incident mode, rétention, Prometheus, eBPF.

## Détection

- **Silent Freeze Detection** : SSH, ping, virsh, scheduler, IOWait, D-state
- **Rules Engine** : règles YAML extensibles (`analysis/rules/default.yaml`)
- **Incident Mode** : 500 ms × 60 s avec collecte exhaustive

## Rapports

- Timeline microseconde avec corrélation causale (↓)
- Classification reboot (soft, hard, IPMI, OVH, panic, watchdog)
- Similarité inter-crashes
- Régressions (changement kernel/Virtualizor avant crash)
- Recommandations actionnables
- Bundle `crashhunter-YYYYMMDD-HHMMSS.tar.zst` prêt pour support OVH

## CLI

```bash
crashhunter run
crashhunter status
crashhunter incidents
crashhunter report --force
crashhunter bundle
crashhunter simulate /opt/crashhunter/data/incidents/Incident_* --step-by-step
```

## Prometheus (optionnel)

```yaml
prometheus:
  enabled: true
  metrics_file: prometheus.metrics
```

Scrape via Grafana : `crashhunter_cpu_percent`, `crashhunter_iowait_percent`, etc.

## Auto-diagnostic

`PluginHealthManager` désactive automatiquement les plugins trop lents ou en échec répété, puis les réactive après cooldown.

## Tests

```bash
cd crashhunter && python -m pytest tests/ -v
```
