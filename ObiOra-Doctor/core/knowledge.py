"""Built-in knowledge base for diagnostic findings."""

from __future__ import annotations

from typing import Any

KNOWLEDGE_BASE: dict[str, dict[str, str]] = {
    "Pression memoire critique": {
        "cause": "Consommation RAM excessive ou fuite memoire applicative.",
        "action": "Identifier les processus avec ps/top et verifier les OOM dans dmesg.",
        "docs": "https://www.kernel.org/doc/html/latest/admin-guide/mm/index.html",
    },
    "Espace disque critique": {
        "cause": "Partitions pleines, logs volumineux ou backups non nettoyes.",
        "action": "Utiliser df -h et du pour localiser les repertoires volumineux.",
        "docs": "",
    },
    "Echec SMART detecte": {
        "cause": "Disque physique en fin de vie ou secteurs defectueux.",
        "action": "Planifier le remplacement et verifier les sauvegardes.",
        "docs": "",
    },
    "RAID degrade": {
        "cause": "Disque manquant ou defaillant dans le tableau RAID.",
        "action": "Verifier mdstat et remplacer le disque degrade.",
        "docs": "",
    },
    "SSH root login autorise": {
        "cause": "Configuration SSH permissive.",
        "action": "Desactiver PermitRootLogin et utiliser un utilisateur privilegie.",
        "docs": "",
    },
    "APP_DEBUG active": {
        "cause": "Mode debug Laravel actif en production.",
        "action": "Mettre APP_DEBUG=false dans .env et vider le cache config.",
        "docs": "https://laravel.com/docs/configuration",
    },
    "OOM detecte": {
        "cause": "Le kernel a tue des processus faute de RAM disponible.",
        "action": "Augmenter la RAM, optimiser les services ou activer du swap.",
        "docs": "",
    },
    "Kernel panic detecte": {
        "cause": "Crash du noyau Linux (driver, hardware, bug kernel).",
        "action": "Analyser dmesg, mettre a jour kernel et verifier le materiel.",
        "docs": "",
    },
    "Reboot en attente": {
        "cause": "Mise a jour kernel ou paquets systeme necessitant un redemarrage.",
        "action": "Planifier un reboot en fenetre de maintenance.",
        "docs": "",
    },
    "Daemon Docker inaccessible": {
        "cause": "Service Docker arrete ou permissions insuffisantes.",
        "action": "Verifier systemctl status docker et le groupe docker.",
        "docs": "",
    },
    "Virtualizor inactif ou non installe": {
        "cause": "Service Virtualizor arrete ou serveur non Virtualizor.",
        "action": "Verifier systemctl status virtualizor sur un noeud Virtualizor.",
        "docs": "",
    },
}


def enrich_finding(title: str, details: str, recommendation: str) -> dict[str, Any]:
    """Return knowledge base enrichment for a finding title."""

    entry = KNOWLEDGE_BASE.get(title, {})
    return {
        "title": title,
        "details": details,
        "recommendation": recommendation,
        "probable_cause": entry.get("cause", ""),
        "suggested_action": entry.get("action", recommendation),
        "documentation": entry.get("docs", ""),
    }


def enrich_report(report_dict: dict[str, Any]) -> dict[str, Any]:
    """Add knowledge base data to all findings in a report."""

    for result in report_dict.get("results", []):
        enriched: list[dict[str, Any]] = []
        for finding in result.get("findings", []):
            item = enrich_finding(
                finding.get("title", ""),
                finding.get("details", ""),
                finding.get("recommendation", ""),
            )
            item["level"] = finding.get("level")
            item["commands"] = finding.get("commands", [])
            enriched.append(item)
        result["findings_enriched"] = enriched
    return report_dict
