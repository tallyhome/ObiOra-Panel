# Contribuer a Obiora Doctor

Merci de contribuer a Obiora Doctor. Le projet vise une qualite proche d'un logiciel commercial : fiable, lisible, testable et utile en production.

## Principes de Contribution

- Une contribution doit resoudre un probleme clair.
- Un commit doit representer une fonctionnalite ou un correctif coherent.
- Les changements non lies doivent etre separes.
- Le diagnostic doit rester en lecture seule par defaut.
- Les rapports ne doivent jamais exposer de secrets.

## Style de Code

Pour Python :

- respecter PEP8 ;
- formater avec Black ;
- trier les imports avec isort ;
- verifier avec Flake8 ;
- typer progressivement avec MyPy.

Pour Bash :

- utiliser `set -euo pipefail` quand c'est adapte ;
- verifier avec ShellCheck ;
- eviter les substitutions fragiles ;
- isoler les commandes systeme ;
- gerer les commandes absentes.

## Taille des Fichiers

Chaque fichier doit rester autour de 500 lignes maximum. Si un fichier devient trop gros, il faut le decouper par responsabilite : collecte, diagnostic, scoring, rendu, tests ou documentation.

## Modules

Un module doit suivre le contrat cible :

- `init()`
- `scan()`
- `diagnostic()`
- `score()`
- `recommendations()`
- `html()`
- `markdown()`
- `json()`

Un module doit retourner un score de 0 a 100 et des alertes classees en `INFO`, `WARNING` ou `CRITICAL`.

## Tests

Avant de proposer une modification :

```bash
shellcheck obiora-doctor.sh
```

Quand la base Python sera en place :

```bash
black .
isort .
flake8
mypy .
pytest
```

Les tests doivent couvrir au minimum :

- parsing des commandes ;
- scoring ;
- recommandations ;
- generation JSON ;
- comportement quand une commande systeme est absente.

## Securite

Ne jamais ajouter une action destructive dans un module de scan.

Les actions de reparation doivent vivre dans un mode separe et demander confirmation. Toute modification systeme doit prevoir une sauvegarde ou un rollback.

## Documentation

Toute nouvelle fonctionnalite doit mettre a jour au moins un fichier pertinent :

- `README.md` pour la vue utilisateur ;
- `ARCHITECTURE.md` pour le design interne ;
- `ROADMAP.md` pour les fonctionnalites planifiees ;
- `TODO.md` pour le suivi immediat ;
- `CHANGELOG.md` pour les changements livres.

## Pull Requests

Une pull request doit contenir :

- le probleme resolu ;
- l'approche choisie ;
- les risques ;
- les tests effectues ;
- un exemple de sortie si l'interface change.
