# Conteneur LXC et imprimante USB

[← Retour au README](../README.md)

Ce document décrit la création du conteneur qui héberge l'application, et le
rattachement de l'imprimante USB branchée sur l'hôte Proxmox.

Les deux autres pièces de l'installation sont documentées à part :
[CUPS et l'imprimante](cups-printer-setup.md), puis
[le tunnel Cloudflare](cloudflare-tunnel-setup.md). Le déroulé complet du
déploiement est dans [deployment.md](deployment.md).

---

## 1. Créer le conteneur

Un conteneur Debian 13 (_trixie_) est recommandé : PHP y est en 8.4, alors que
Debian 12 s'arrête à 8.2 — trop ancien pour Laravel 13, qui exige PHP 8.3 au
minimum. Sur Debian 12, il faudrait ajouter le dépôt Sury, une dépendance
externe de plus à maintenir.

Depuis l'interface Proxmox : **Create CT**, puis

| Réglage        | Valeur conseillée    | Pourquoi                                                 |
| -------------- | -------------------- | -------------------------------------------------------- |
| Template       | `debian-13-standard` | PHP 8.4 dans les dépôts officiels                        |
| Cœurs          | 2                    | la conversion PDF côté CUPS est le seul pic de charge    |
| Mémoire        | 1024 Mo              | Laravel + CUPS + `cloudflared` tiennent largement dedans |
| Disque         | 8 Go                 | prévoir plus si les PDF déposés ne sont jamais purgés    |
| Non privilégié | oui                  | voir la section 3                                        |
| Réseau         | DHCP ou IP fixe      | aucun port entrant n'a besoin d'être ouvert              |

Notez l'identifiant du conteneur (`<VMID>`), il sert dans toutes les commandes
qui suivent. Elles s'exécutent **sur l'hôte Proxmox**, pas dans le conteneur.

## 2. Repérer l'imprimante sur l'hôte

```bash
lsusb | grep -i hewlett
```

La sortie ressemble à :

```
Bus 001 Device 006: ID 03f0:c52a HP, Inc HP Color LaserJet Pro M254dw
```

Retenez trois choses : le **bus** (`001`), le **device** (`006`) et l'identifiant
**vendor:product** (`03f0:c52a`). Le nœud correspondant est
`/dev/bus/usb/001/006`.

```bash
ls -l /dev/bus/usb/001/006
```

## 3. Rattacher l'imprimante au conteneur

Depuis Proxmox 8.1, un périphérique se passe au conteneur avec `--dev0` ; depuis
8.2, l'interface web propose la même chose via **Resources → Add → Device
Passthrough**.

```bash
pct set <VMID> --dev0 path=/dev/bus/usb/001/006,uid=100000,gid=100000
```

`uid=100000,gid=100000` correspond à `root` **vu de l'intérieur** d'un conteneur
non privilégié : sans cette correspondance, le nœud appartiendrait à un
utilisateur inexistant dans le conteneur et CUPS ne pourrait pas l'ouvrir.

Redémarrez le conteneur pour que la correspondance prenne effet :

```bash
pct stop <VMID> && pct start <VMID>
```

### Le piège : le numéro de device change

`006` n'est pas stable. Débrancher puis rebrancher l'imprimante, ou redémarrer
l'hôte, lui donne un autre numéro — et le passthrough pointe alors vers un nœud
qui n'existe plus. L'application affiche dans ce cas une erreur d'imprimante
introuvable sur chaque tâche.

Deux parades, au choix.

**a. Passer le bus entier.** Plus robuste tant que l'imprimante reste branchée
sur le même port physique. Dans `/etc/pve/lxc/<VMID>.conf` :

```
lxc.cgroup2.devices.allow: c 189:* rwm
lxc.mount.entry: /dev/bus/usb/001 dev/bus/usb/001 none bind,optional,create=dir
```

`189` est le _major_ des périphériques USB ; `c 189:* rwm` autorise le conteneur
à ouvrir n'importe lequel d'entre eux. Cette méthode demande un conteneur
**privilégié** : c'est un compromis assumé, à ne retenir que si la première
solution se révèle trop fragile en pratique.

**b. Fixer le nom du nœud par une règle udev**, sur l'hôte, dans
`/etc/udev/rules.d/99-imprimante.rules` :

```
SUBSYSTEM=="usb", ATTR{idVendor}=="03f0", ATTR{idProduct}=="c52a", MODE="0660", GROUP="lp", SYMLINK+="imprimante"
```

```bash
udevadm control --reload-rules && udevadm trigger
```

Le lien `/dev/imprimante` pointe alors toujours vers le bon nœud. Vérifiez
ensuite que le passthrough suit bien le lien sur votre version de Proxmox ;
sinon, rabattez-vous sur la solution (a).

## 4. Vérifier depuis le conteneur

```bash
pct enter <VMID>
```

```bash
apt update && apt install -y usbutils
```

```bash
lsusb | grep -i hewlett
```

Si l'imprimante apparaît ici, le passthrough fonctionne et vous pouvez passer à
[la configuration de CUPS](cups-printer-setup.md). Sinon, reprenez l'étape 3 :
tant que `lsusb` ne voit rien dans le conteneur, CUPS ne verra rien non plus.

---

[← Retour au README](../README.md)
