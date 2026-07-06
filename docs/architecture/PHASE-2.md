# ObiOra Panel — Phase 2 : Installation automatique (v1.1.0)

## One-liner

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/tallyhome/ObiOra-Panel/main/install/install.sh)
```

## Options

```bash
bash install.sh \
  --domain panel.example.com \
  --email admin@example.com \
  --docker \
  --ftp \
  --tag v1.1.0 \
  --dir /opt/obiora-panel
```

## OS supportés

| Famille | Versions |
|---|---|
| Debian | 11, 12 |
| Ubuntu | 20.04, 22.04, 24.04 |
| AlmaLinux | 8, 9, 10 |
| Rocky Linux | 8, 9, 10 |

## Ce que fait l'installateur

1. Vérifie root, OS, RAM, disque, ports
2. Met à jour le système
3. Installe : Nginx, PHP 8.3, MariaDB, Redis, Composer, Git, Node, Supervisor, Certbot, Fail2Ban, UFW/firewalld
4. Optionnel : Docker, vsftpd
5. Crée l'utilisateur `obiora`
6. Configure MariaDB + base `obiora_panel`
7. Clone GitHub et configure Laravel (.env, migrate, seed)
8. Configure virtual host Nginx
9. SSL Let's Encrypt (si `--domain` + `--email`)
10. Démarre services systemd (queue, scheduler, agent)
11. Configure pare-feu

## Services systemd

| Service | Rôle |
|---|---|
| `obiora-queue` | Queue worker Laravel |
| `obiora-scheduler.timer` | Scheduler (cron) |
| `obiora-agent` | Agent local (multi-serveurs, Phase 3+) |
| `nginx` | Serveur web |
| `php8.3-fpm` | PHP-FPM |

## Rollback

En cas d'erreur, un snapshot `.env` est restauré automatiquement. Logs : `/var/log/obiora-install.log`

## Désinstallation

```bash
bash install/uninstall.sh
```
