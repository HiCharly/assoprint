# Conteneur LXC

[← Retour au README](../README.md)

Ce document décrit la création du conteneur qui héberge l'application.

L'imprimante est pilotée **par le réseau** : le conteneur n'a besoin d'aucun
périphérique particulier, et peut donc rester non privilégié. Le passthrough USB,
envisagé au départ, est conservé en annexe pour le cas où il redeviendrait
nécessaire — voir aussi [la section 7 de
cups-printer-setup.md](cups-printer-setup.md#7-pourquoi-pas-lusb), qui explique
pourquoi il ne mène nulle part dans un conteneur.

Suite du parcours : [CUPS et l'imprimante](cups-printer-setup.md), puis
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
| Cœurs          | 2                    | la conversion des PDF est le seul pic de charge          |
| Mémoire        | 1024 Mo              | Laravel + CUPS + `cloudflared` tiennent largement dedans |
| Disque         | 8 Go                 | prévoir plus si les PDF déposés ne sont jamais purgés    |
| Non privilégié | oui                  | rien ici n'exige de privilèges                           |
| Réseau         | DHCP ou IP fixe      | aucun port entrant n'a besoin d'être ouvert              |

Le conteneur doit simplement pouvoir **joindre l'imprimante sur le réseau
local** : c'est la seule contrainte réseau, avec la sortie Internet dont
`cloudflared` a besoin.

## 2. Vérifier que l'imprimante est joignable

```bash
pct enter <VMID>
```

```bash
ping -c 2 192.168.1.41
```

Si l'imprimante répond, passez à [la configuration de
CUPS](cups-printer-setup.md). Sinon, c'est un problème de réseau ou de VLAN à
régler avant toute chose : rien d'autre ne peut fonctionner tant que ces deux
machines ne se voient pas.

---

## Annexe — passthrough USB

À ne lire que si l'imprimante ne peut pas être mise sur le réseau. Rappel :
`ipp-usb` ne démarre pas tout seul dans un conteneur, faute d'événements udev, et
il faudra donc aussi lui écrire un service systemd maison.

### Repérer le périphérique sur l'hôte

```bash
lsusb | grep -i hewlett
```

```
Bus 001 Device 006: ID 03f0:c52a HP, Inc HP Color LaserJet Pro M254dw
```

Retenez le **bus** (`001`), le **device** (`006`) et l'identifiant
**vendor:product** (`03f0:c52a`). Le nœud correspondant est
`/dev/bus/usb/001/006`.

### Rattacher le périphérique

Depuis Proxmox 8.1 :

```bash
pct set <VMID> --dev0 path=/dev/bus/usb/001/006,uid=100000,gid=100000
```

`uid=100000,gid=100000` correspond à `root` **vu de l'intérieur** d'un conteneur
non privilégié : sans cette correspondance, le nœud appartiendrait à un
utilisateur inexistant dans le conteneur.

```bash
pct stop <VMID> && pct start <VMID>
```

### Le numéro de device n'est pas stable

Débrancher puis rebrancher l'imprimante, ou redémarrer l'hôte, lui donne un autre
numéro, et le passthrough pointe alors vers un nœud disparu. Deux parades :

**a. Passer le bus entier** — dans `/etc/pve/lxc/<VMID>.conf` :

```
lxc.cgroup2.devices.allow: c 189:* rwm
lxc.mount.entry: /dev/bus/usb/001 dev/bus/usb/001 none bind,optional,create=dir
```

Cette méthode exige un conteneur **privilégié**, ce qui est un recul net en
matière de cloisonnement.

**b. Fixer le nom du nœud par une règle udev**, sur l'hôte, dans
`/etc/udev/rules.d/99-imprimante.rules` :

```
SUBSYSTEM=="usb", ATTR{idVendor}=="03f0", ATTR{idProduct}=="c52a", MODE="0660", GROUP="lp", SYMLINK+="imprimante"
```

```bash
udevadm control --reload-rules && udevadm trigger
```

### Revenir en arrière

Pour retirer un passthrough devenu inutile, sur l'hôte :

```bash
pct set <VMID> --delete dev0
```

```bash
rm -f /etc/udev/rules.d/99-imprimante.rules && udevadm control --reload-rules && udevadm trigger
```

```bash
pct stop <VMID> && pct start <VMID>
```

Et dans le conteneur :

```bash
apt purge -y ipp-usb && apt autoremove -y
```

---

[← Retour au README](../README.md)
