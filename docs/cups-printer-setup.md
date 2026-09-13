# CUPS et la HP Color LaserJet Pro M254dw

[← Retour au README](../README.md)

Ce document part d'un conteneur qui _voit déjà_ l'imprimante en USB — c'est
l'objet de [proxmox-lxc-setup.md](proxmox-lxc-setup.md). Toutes les commandes
s'exécutent **dans le conteneur**.

L'objectif : une file d'impression CUPS fonctionnelle, dont le nom sera repris
dans `PRINTER_NAME`, et qu'une impression de test valide **avant** de brancher
l'application dessus.

---

## 1. Installer CUPS

```bash
apt update && apt install -y cups ipp-usb poppler-utils
```

| Paquet          | Rôle                                                                                                                 |
| --------------- | -------------------------------------------------------------------------------------------------------------------- |
| `cups`          | le serveur d'impression, et les commandes `lp` / `lpstat` qu'utilise l'application                                   |
| `ipp-usb`       | expose une imprimante USB moderne comme une imprimante réseau IPP, ce qui permet à CUPS de la configurer sans pilote |
| `poppler-utils` | fournit `pdfinfo`, dont l'application se sert pour compter les pages                                                 |

```bash
systemctl enable --now cups ipp-usb
```

## 2. Laisser ipp-usb détecter l'imprimante

`ipp-usb` surveille les branchements et crée la file automatiquement. Quelques
secondes après le démarrage du service :

```bash
lpstat -p -d
```

```
printer HP_Color_LaserJet_Pro_M254dw is idle.  enabled since ...
```

**Notez le nom exact de la file** : c'est lui, et rien d'autre, qui ira dans
`PRINTER_NAME`. Il contient souvent des underscores et parfois un suffixe.

Si rien n'apparaît :

```bash
systemctl status ipp-usb
journalctl -u ipp-usb -n 50 --no-pager
lsusb | grep -i hewlett
```

Un `lsusb` muet renvoie au passthrough (document précédent). Un `lsusb` qui voit
l'imprimante mais un `lpstat` vide signifie que le mode « driverless » n'a pas
abouti : passez à la section 5.

## 3. Impression de test, en ligne de commande

C'est l'étape à ne pas sauter : tant qu'une page ne sort pas avec `lp`, inutile
de chercher du côté de l'application.

```bash
echo "Test AssoPrint" | lp -d HP_Color_LaserJet_Pro_M254dw
```

```bash
lpstat -W not-completed -o HP_Color_LaserJet_Pro_M254dw
```

La tâche disparaît de cette liste quand elle est terminée — c'est exactement le
signal qu'utilise le job `PollCupsJobStatus` pour marquer une tâche imprimée.

Testez aussi les deux options que l'application manipule :

```bash
lp -d HP_Color_LaserJet_Pro_M254dw -n 2 -o sides=two-sided-long-edge -o print-color-mode=monochrome /usr/share/cups/data/testprint
```

## 4. Vérifier les options réellement supportées

```bash
lpoptions -p HP_Color_LaserJet_Pro_M254dw -l
```

Cherchez `sides` et `print-color-mode` dans la sortie. L'application envoie :

| Réglage dans l'interface | Option `lp`                   |
| ------------------------ | ----------------------------- |
| Recto simple             | `sides=one-sided`             |
| Recto verso (bord long)  | `sides=two-sided-long-edge`   |
| Recto verso (bord court) | `sides=two-sided-short-edge`  |
| Couleur                  | `print-color-mode=color`      |
| Noir et blanc            | `print-color-mode=monochrome` |

Si votre file annonce d'autres noms, c'est le mapping des enums
`App\Enums\Duplex` et `App\Enums\ColorMode` qu'il faut ajuster — les méthodes
`cupsSides()` et `cupsPrintColorMode()` sont là pour ça.

## 5. Si le mode « sans pilote » ne suffit pas

Certaines HP Color LaserJet Pro rendent mal en IPP Everywhere : pages blanches,
caractères aberrants, couleurs absentes. Dans ce cas, ajoutez la file à la main
avec le pilote HP :

```bash
apt install -y hplip printer-driver-hpcups
```

```bash
lpinfo -v | grep -i hp
```

```bash
lpadmin -p HP_M254dw -E -v "usb://HP/Color%20LaserJet%20Pro%20M254dw?serial=XXXXXXXX" -m drv:///hp/hpcups.drv/hp-color_laserjet_pro_m254dw.ppd
```

```bash
lpadmin -d HP_M254dw
```

Reprenez alors l'étape 3 avec ce nom de file, et reportez-le dans
`PRINTER_NAME`.

## 6. CUPS ne doit écouter que sur localhost

C'est la configuration par défaut, mais elle mérite d'être vérifiée : l'interface
d'administration de CUPS ne demande pas de mot de passe par défaut et n'a
strictement rien à faire sur le réseau.

```bash
grep -E "^Listen|^Port" /etc/cups/cupsd.conf
```

La seule ligne attendue est :

```
Listen localhost:631
```

Si une ligne `Port 631` ou `Listen *:631` traîne, remplacez-la par celle
ci-dessus puis `systemctl restart cups`. Seul `cloudflared` expose quelque chose
vers l'extérieur, et il n'expose que l'application.

## 7. Renseigner l'application

Dans le `.env` de l'application :

```
PRINTER_NAME=HP_Color_LaserJet_Pro_M254dw
```

```bash
php artisan config:clear
```

Ce nom n'est jamais écrit en dur dans le code : il est lu via
`config('print.printer_name')`, et une tâche échoue avec un message explicite
s'il est vide.

---

[← Retour au README](../README.md)
