# Changelog

## [0.3.0] - 2026-07-07

### Added

- 8 nouveaux modules : swap, raid, kvm, lxc, laravel, cpanel, plesk, directadmin
- Base de connaissances integree (causes probables, actions, documentation)
- Chargement dynamique des plugins
- Obiora Bench (`bench`) — benchmarks CPU/RAM/disque/reseau
- Obiora Agent (`agent`) — scans periodiques + heartbeat
- Obiora Rescue (`rescue`) — plan de depannage lecture seule
- Validation schema JSON des rapports
- Export zip des rapports (`--zip`)
- Options scan : `--exclude-module`, `--json`, `--quiet`, `--verbose`
- Configuration par module (`config/modules.json`)
- Logging application (`logs/obiora-doctor.log`)
- Rapport complet `docs/REPORT.md`
- 13 tests unitaires

### Changed

- 25 modules actifs au total
- Version moteur 0.2.0 -> 0.3.0
- JSON enrichi avec `findings_enriched`

## [0.2.0] - 2026-07-07

- 17 modules, watch, API, compare, support anonymise, interactive

## [0.1.0] - 2026-07-07

- Premier moteur Python modulaire
