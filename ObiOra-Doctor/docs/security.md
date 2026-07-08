# Securite Obiora Doctor

## Mode diagnostic

- Lecture seule par defaut
- Aucune modification systeme pendant un scan
- Aucune commande destructive

## Mode support

Le flag `--support` redige :

- adresses IP
- noms de domaine
- secrets connus (password, token, APP_KEY)
- chemins sensibles

## Actions correctives

Les futures actions de reparation vivront dans `Obiora Rescue` avec :

- confirmation explicite
- sauvegarde prealable
- plan de rollback documente

## Politique rollback

1. Sauvegarder la configuration avant toute action.
2. Tester sur un serveur non critique.
3. Conserver les logs dans `logs/`.
4. Restaurer depuis la sauvegarde en cas d'echec.

## Interface web (production Virtualizor)

- Bind `127.0.0.1` uniquement — jamais `0.0.0.0`
- Token dans `config/web.token` (chmod 600, non versionne)
- Authentification Bearer obligatoire
- Rate limit : 1 scan / 60 secondes
- Acces distant via tunnel SSH uniquement :

```bash
ssh -L 8766:127.0.0.1:8766 root@serveur
```

Ne jamais ouvrir le port web sur le firewall public.
