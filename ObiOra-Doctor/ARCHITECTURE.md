# Architecture Obiora Doctor

Obiora Doctor doit etre concu comme un moteur de diagnostic extensible, pas comme une suite de commandes Bash collees ensemble.

## Vue Generale

```text
CLI/TUI
  |
  v
Command Router
  |
  v
Diagnostic Engine
  |
  +--> Module Registry
  +--> Command Runner
  +--> Scoring Engine
  +--> Knowledge Base
  +--> Report Renderer
```

## Structure Cible

```text
ObiOra-Doctor/
  bin/          executables et wrappers CLI
  config/       configuration globale et profils
  core/         moteur central partage par toute la suite
  modules/      diagnostics par technologie
  plugins/      extensions externes
  templates/    templates HTML, Markdown et texte
  reports/      sorties generees par execution
  tests/        tests unitaires et integration
  docs/         documentation utilisateur et technique
  logs/         logs locaux de l'application
  cache/        cache temporaire non critique
```

## Moteur Central

Le moteur central orchestre les modules. Il ne doit pas contenir de logique specifique a une technologie. Sa responsabilite est de charger les modules, executer les scans, consolider les resultats, calculer les scores et generer les rapports.

Responsabilites :

- charger la configuration ;
- detecter l'environnement ;
- preparer le contexte d'execution ;
- executer les modules selectionnes ;
- isoler les erreurs par module ;
- calculer le score global ;
- transmettre les resultats aux renderers.

## Contrat Module

Un module represente une zone de diagnostic : CPU, RAM, Docker, Virtualizor, MySQL, etc.

Interface cible :

```text
init(context) -> ModuleState
scan(context) -> RawData
diagnostic(raw_data, context) -> Findings
score(findings, context) -> Score
recommendations(findings, context) -> Recommendations
html(result) -> HtmlFragment
markdown(result) -> MarkdownFragment
json(result) -> JsonObject
```

Un module ne doit pas interrompre tout le scan si une commande manque. Il doit retourner une alerte claire et continuer quand c'est possible.

## Format Resultat

Chaque module doit retourner un objet logique equivalent a :

```json
{
  "module": "ram",
  "status": "ok",
  "score": 96,
  "findings": [
    {
      "level": "INFO",
      "title": "64 Go detectes",
      "details": "La memoire disponible est coherente avec la charge observee.",
      "recommendation": "Aucune action requise."
    }
  ],
  "metrics": {},
  "duration_ms": 0
}
```

## Health Score

Le score va de 0 a 100.

- `90-100` : sain.
- `70-89` : acceptable avec recommandations.
- `40-69` : degrade.
- `0-39` : critique.

Le score global doit etre pondere. Un probleme critique disque, kernel ou virtualisation doit peser plus lourd qu'une recommandation mineure.

## Command Runner

Toutes les commandes systeme doivent passer par une couche commune.

Cette couche gere :

- timeout ;
- capture stdout/stderr ;
- code de retour ;
- commande absente ;
- mode debug ;
- redaction des secrets ;
- journalisation.

## Securite

Le mode diagnostic est lecture seule par defaut.

Toute action corrective doit etre separee dans un mode explicite, par exemple `obiora rescue`, avec confirmation, sauvegarde et rollback.

Regles :

- pas de suppression destructive sans confirmation ;
- pas de modification systeme pendant un scan ;
- pas d'exposition de secrets dans les rapports ;
- anonymisation disponible pour le mode support.

## Reporting

Les renderers sont separes du diagnostic.

Sorties obligatoires :

- JSON pour API et automatisation ;
- Markdown pour lecture et tickets ;
- HTML pour rapport client ;
- texte pour terminal ou support brut.

## Obiora Suite

Obiora Doctor est le premier outil. Les autres outils doivent reutiliser le meme moteur quand c'est pertinent :

- Obiora Watch reutilise collecte, scoring et historique.
- Obiora Bench reutilise reporting et contexte systeme.
- Obiora Security reutilise modules, severites et recommandations.
- Obiora Monitor consomme les rapports et l'API locale.
- Obiora Agent execute les diagnostics sur des VPS distants.
