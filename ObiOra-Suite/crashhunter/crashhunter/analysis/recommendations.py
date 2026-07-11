"""Actionable recommendations based on diagnosis."""

from __future__ import annotations

from typing import Any

RECOMMENDATIONS: dict[str, list[str]] = {
    "disk_timeout": [
        "Vérifier SMART de tous les disques (smartctl -a).",
        "Contrôler les logs du contrôleur RAID / NVMe.",
        "Tester I/O avec fio sur le volume suspect.",
        "Contacter OVH si dégradation SMART confirmée.",
    ],
    "d_state_processes": [
        "Identifier le wchan et stack des processus D-state.",
        "Vérifier latence LVM/XFS et état dmsetup.",
        "Suspendre migrations VM pendant investigation.",
    ],
    "iowait_high": [
        "Analyser iostat/pidstat pour trouver le processus I/O bound.",
        "Vérifier queue depth et latence disque.",
    ],
    "virtualizor_timeout": [
        "Vérifier service Virtualizor et logs PHP.",
        "Contrôler charge libvirt et espace disque VM.",
    ],
    "kernel_panic": [
        "Activer kdump si pas déjà fait.",
        "Analyser dmesg complet et var/log/messages.",
    ],
    "thermal_event": [
        "Vérifier ventilation salle et capteurs IPMI.",
        "Nettoyer filtres et contrôler ventilateurs.",
    ],
    "oom": [
        "Identifier processus tué par OOM killer.",
        "Ajuster limites mémoire VM ou ajouter swap.",
    ],
    "ssh_timeout": [
        "Freeze système probable — consulter rapport chronologique.",
        "Vérifier si incident mode a capturé des données d'urgence.",
    ],
}


class RecommendationsEngine:
    """Generate remediation recommendations from diagnosis findings."""

    def generate(self, diagnosis: dict[str, Any], reboot: dict[str, Any] | None = None) -> list[dict[str, Any]]:
        recs: list[dict[str, Any]] = []
        seen: set[str] = set()

        for finding in diagnosis.get("findings", []):
            cat = finding.get("category", "")
            if cat in seen:
                continue
            seen.add(cat)
            actions = RECOMMENDATIONS.get(cat, [
                f"Investiguer la catégorie {cat} avec les logs du rapport.",
            ])
            recs.append({
                "category": cat,
                "title": finding.get("title", cat),
                "confidence": finding.get("confidence", 0),
                "actions": actions,
            })

        if reboot and reboot.get("reboot_type") in ("hard_reboot_ipmi", "ovh_reboot"):
            recs.append({
                "category": "reboot",
                "title": "Reboot matériel/OVH",
                "confidence": reboot.get("confidence", 0.7),
                "actions": [
                    "Consulter IPMI SEL pour la cause du reset.",
                    "Vérifier ticket OVH pour intervention datacenter.",
                    "Corréler avec timeline pré-reboot du Black Box.",
                ],
            })

        if not recs:
            recs.append({
                "category": "unknown",
                "title": "Freeze silencieux",
                "confidence": 0.0,
                "actions": [
                    "Envoyer le bundle diagnostic (crashhunter bundle) au support.",
                    "Comparer avec crashes similaires dans l'historique.",
                    "Activer eBPF si disponible pour prochain incident.",
                ],
            })
        return recs
