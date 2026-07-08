# Modules Obiora Doctor v0.3.0

25 modules integres. Voir [REPORT.md](REPORT.md) pour le detail complet.

## Systeme
cpu, ram, swap, disk, smart, raid, network, kernel

## Virtualisation
docker, kvm, lxc, virtualizor

## Bases de donnees & web
mysql, postgresql, php, apache, nginx, litespeed, laravel

## Hebergement & securite
cpanel, plesk, directadmin, firewall, security, benchmark

## Contrat module

Chaque module implemente `scan()` et `diagnostic()` et retourne un score 0-100.

Activation/desactivation via `config/modules.json`.
