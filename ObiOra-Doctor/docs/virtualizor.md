# Virtualizor

Le module `virtualizor` verifie :

- etat du service Virtualizor via systemd
- accessibilite libvirt via `virsh`
- inventaire des VMs
- informations noeud via `virsh nodeinfo`

## Commandes de verification manuelle

```bash
systemctl status virtualizor --no-pager
virsh list --all
virsh nodeinfo
```

## Recommandations

- Verifier les bridges reseau Virtualizor
- Surveiller les logs du panel
- Controler les erreurs de stockage et de migration VPS
