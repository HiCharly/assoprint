# CUPS et la HP Color LaserJet Pro M254dw

[← Retour au README](../README.md)

L'imprimante est pilotée **par le réseau**, sur une adresse IP fixe, et non par
l'USB. Ce choix n'était pas celui prévu au départ ; il s'est imposé à
l'installation, pour une raison qui vaut d'être connue avant de reprendre le
chemin inverse — voir [la section 7](#7-pourquoi-pas-lusb).

Toutes les commandes s'exécutent **dans le conteneur**. L'objectif : une file
d'impression permanente, dont le nom ira dans `PRINTER_NAME`, et qu'une
impression de test valide **avant** de brancher l'application dessus.

---

## 1. Fixer l'adresse de l'imprimante

Rien ne fonctionnera durablement si l'imprimante change d'IP : la file pointerait
dans le vide et chaque tâche échouerait.

Deux façons, au choix :

- **réservation DHCP** sur la box, à partir de l'adresse MAC de l'imprimante —
  la plus simple à administrer ;
- **adresse fixe** configurée sur l'imprimante elle-même, via son écran :
  _Configuration → Réseau → Paramètres IPv4_.

Relevez l'adresse retenue, elle revient dans toutes les commandes qui suivent.
Dans les exemples : `192.168.1.41`.

```bash
ping -c 2 192.168.1.41
```

## 2. Installer CUPS

```bash
apt update && apt install -y cups cups-ipp-utils cups-filters poppler-utils
```

| Paquet           | Rôle                                                                                     |
| ---------------- | ---------------------------------------------------------------------------------------- |
| `cups`           | le serveur d'impression, et les commandes `lp` / `lpstat` qu'utilise l'application       |
| `cups-ipp-utils` | fournit `ipptool`, indispensable pour interroger l'imprimante quand quelque chose cloche |
| `cups-filters`   | fournit `driverless`, qui génère le bon PPD (section 4)                                  |
| `poppler-utils`  | fournit `pdfinfo`, dont l'application se sert pour compter les pages                     |

```bash
systemctl enable --now cups
```

## 3. Vérifier ce que l'imprimante déclare

Avant de créer quoi que ce soit, demandez-lui ses capacités :

```bash
ipptool -tv ipp://192.168.1.41/ipp/print get-printer-attributes.test | grep -iE "printer-make-and-model|sides-supported|print-color-mode-supported"
```

Attendu :

```
printer-make-and-model (textWithoutLanguage) = HP ColorLaserJet M253-M254
sides-supported (1setOf keyword) = one-sided,two-sided-short-edge,two-sided-long-edge
```

Ces deux lignes valent confirmation que l'imprimante est joignable **et** qu'elle
sait faire du recto verso. Si `sides-supported` ne mentionne que `one-sided`,
inutile d'aller plus loin : le problème est dans l'imprimante, pas dans CUPS.

## 4. Créer la file — avec `driverless`, pas avec `everywhere`

C'est **le** piège de cette installation, et il coûte cher parce qu'il échoue en
silence.

`lpadmin -m everywhere` semble être la commande naturelle. Elle crée bien une
file qui imprime — mais en posant un PPD **générique**, non issu de l'imprimante.
Le recto verso y est alors traduit en commandes PostScript qu'une imprimante
pilotée en IPP n'exécute jamais : chaque tâche sort en recto simple, sans le
moindre message d'erreur, quelle que soit la syntaxe employée.

La bonne commande demande à CUPS de générer le PPD **depuis la réponse de
l'imprimante** :

```bash
lpadmin -p HP_M254dw -E -v ipp://192.168.1.41/ipp/print -m driverless:ipp://192.168.1.41/ipp/print
```

```bash
lpadmin -d HP_M254dw
```

`HP_M254dw` est un nom que vous choisissez : il restera stable, contrairement aux
noms auto-découverts, qui portent un suffixe dérivé du matériel.

**Vérifiez immédiatement que le PPD est le bon :**

```bash
grep -i NickName /etc/cups/ppd/HP_M254dw.ppd
```

```
*NickName: "HP ColorLaserJet M253-M254, driverless, cups-filters 1.28.17"
```

Le modèle doit apparaître. Un PPD nommé `"Printer - IPP Everywhere"` est le PPD
générique : la file imprimera, mais ignorera le recto verso. Dans ce cas,
supprimez-la (`lpadmin -x HP_M254dw`) et reprenez avec `-m driverless:`.

### Files temporaires : ne vous y fiez pas

```bash
lpstat -e
```

Cette commande liste les imprimantes **découvertes**, qui n'existent que le temps
d'une tâche : CUPS les crée à la demande et les supprime après une minute
d'inactivité. `lpstat -p -d`, lui, ne montre que les files permanentes — il est
donc normal qu'il réponde `No destinations added` alors que `lpstat -e` affiche
un nom.

L'application a besoin d'une file **permanente**. Quand une file temporaire a
expiré, `lpstat -W not-completed -o <file>` échoue, et le suivi interprète cet
échec comme « tâche encore en file » : la tâche resterait « impression en cours »
jusqu'au délai d'abandon, alors même que la page est sortie.

## 5. Impression de test

L'étape à ne pas sauter : tant qu'une page ne sort pas avec `lp`, inutile de
chercher du côté de l'application.

```bash
printf 'Page 1 - AssoPrint\fPage 2 - AssoPrint\n' > /tmp/deux-pages.txt
```

```bash
cupsfilter -m application/pdf /tmp/deux-pages.txt > /tmp/deux.pdf 2>/dev/null
```

Testez ensuite les deux options — les seules — que l'application manipule :

```bash
lp -d HP_M254dw -o sides=two-sided-long-edge /tmp/deux.pdf
```

```bash
lp -d HP_M254dw -o sides=one-sided -o print-color-mode=color /usr/share/cups/data/testprint
```

Ce qu'il faut regarder, et rien d'autre : **une seule feuille imprimée des deux
côtés** pour la première, **des couleurs** pour la seconde. Deux feuilles
séparées signifient que le recto verso n'est pas passé — retour à la section 4.

```bash
lpstat -W not-completed -o HP_M254dw
```

La tâche disparaît de cette liste quand elle est terminée : c'est exactement le
signal qu'utilise le job `PollCupsJobStatus` pour marquer une tâche imprimée.

### Le contrôle d'état avant envoi

Avant de remettre quoi que ce soit à `lp`, l'application demande à l'imprimante
si elle peut imprimer. C'est nécessaire parce qu'une file **arrêtée continue
d'accepter les tâches** : `lp` réussit, renvoie un identifiant, et le document
s'empile sans que rien ne sorte.

```bash
ipptool -t -T 30 ipp://localhost/printers/HP_M254dw resources/cups/printer-ready.test
```

Lancée depuis `/var/www/assoprint`, cette commande est exactement celle
qu'exécute le serveur. Son **code de sortie** porte le verdict — et c'est lui
seul que l'application regarde :

```bash
echo $?
```

`0` : l'imprimante est prête. Autre chose : elle ne l'est pas, et les tâches
attendront au lieu de partir. Vérifiez-le une fois à l'installation, file active
puis `cupsdisable HP_M254dw` : la seconde exécution doit échouer, et le rapport
citer l'état et le motif.

Le `-t` n'est pas décoratif : sans lui, un test en échec n'affiche que
`successful-ok`, sans l'état ni les motifs. L'application ne pourrait alors plus
distinguer une imprimante bloquée d'une question restée sans réponse, et
laisserait partir les tâches.

Le fichier `resources/cups/printer-ready.test` porte les conditions à remplir.
Rien n'est déduit de la mise en forme du rapport, qui n'est lu que pour nommer
la cause (`media-empty`, `media-jam`, `paused`…) dans le message montré au
membre. Une cause non reconnue donne un message générique, jamais une erreur.

Si l'interrogation elle-même échoue — `ipptool` absent, URI erronée, CUPS muet —
l'application considère l'état comme inconnu et **laisse partir la tâche** : un
contrôle en panne ne doit pas bloquer les impressions de tout le club. Le délai
d'abandon de `PollCupsJobStatus` reste le filet dans ce cas.

Le cas de l'imprimante éteinte mérite d'être connu : CUPS ne l'apprend qu'en
échouant à lui parler. La **première** tâche après une extinction part donc
malgré le contrôle, et c'est son échec qui arrête la file — les suivantes, elles,
attendent.

## 6. Renseigner l'application

```bash
cd /var/www/assoprint && sed -i 's/^PRINTER_NAME=.*/PRINTER_NAME=HP_M254dw/' .env
```

```bash
php artisan optimize:clear && php artisan optimize && systemctl restart laravel-queue
```

Ce nom n'est jamais écrit en dur dans le code : il est lu via
`config('print.printer_name')`, et une tâche échoue avec un message explicite
s'il est vide.

Si votre file annonçait d'autres noms d'options que `sides` et
`print-color-mode`, ce sont les enums `App\Enums\Duplex` et
`App\Enums\ColorMode` qu'il faut ajuster — leurs méthodes `cupsSides()` et
`cupsPrintColorMode()` existent précisément pour isoler ce mapping.

## 7. Pourquoi pas l'USB

Le projet visait au départ une imprimante branchée en USB, servie par `ipp-usb`.
Ça ne fonctionne pas dans un conteneur LXC, et pour une raison structurelle :
`ipp-usb.service` est un service `static`, déclenché par **udev** au branchement
du périphérique. Or les événements udev de l'hôte ne se propagent pas dans un
conteneur. Le service y démarre, ne trouve aucun périphérique à servir, et
s'arrête aussitôt — `Active: inactive (dead)`, sans erreur.

Le réseau est de toute façon préférable ici : plus de passthrough à configurer,
plus de numéro de périphérique USB qui change au rebranchement, et un conteneur
qui peut rester non privilégié.

Si vous tenez malgré tout à l'USB, il faudra lancer `ipp-usb` autrement qu'avec
udev — un service systemd maison avec `Restart=always`, et le passthrough décrit
en annexe de [proxmox-lxc-setup.md](proxmox-lxc-setup.md). C'est nettement plus
fragile, pour un gain nul dès lors que l'imprimante est déjà sur le réseau.

## 8. CUPS ne doit écouter que sur localhost

C'est la configuration par défaut, mais elle mérite d'être vérifiée : l'interface
d'administration de CUPS ne demande pas de mot de passe et n'a rien à faire sur
le réseau.

```bash
grep -E "^Listen|^Port" /etc/cups/cupsd.conf
```

La seule ligne attendue :

```
Listen localhost:631
```

Si une ligne `Port 631` ou `Listen *:631` traîne, remplacez-la puis
`systemctl restart cups`. Seul `cloudflared` expose quelque chose vers
l'extérieur, et il n'expose que l'application.

## 9. Si le mode sans pilote ne suffit pas

Certaines HP Color LaserJet Pro rendent mal en IPP : pages blanches, caractères
aberrants. Le repli est le pilote HP, qui parle PCL directement :

```bash
apt install -y hplip printer-driver-hpcups
```

```bash
lpinfo -m | grep -i m254
```

```bash
lpadmin -x HP_M254dw; lpadmin -p HP_M254dw -E -v ipp://192.168.1.41/ipp/print -m <ppd-trouvé-ci-dessus>
```

Les noms d'options changent alors (`Duplex=DuplexNoTumble`, `ColorModel=RGB`
plutôt que `sides=` et `print-color-mode=`) : il faut ajuster les deux enums
mentionnés à la section 6, et rien d'autre dans l'application.

---

[← Retour au README](../README.md)
