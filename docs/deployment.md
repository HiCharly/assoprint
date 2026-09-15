# Déploiement

[← Retour au README](../README.md)

Déroulé complet, du conteneur vide à la première page imprimée depuis
l'extérieur. Trois documents sont appelés en cours de route :

1. [proxmox-lxc-setup.md](proxmox-lxc-setup.md) — le conteneur LXC
2. [cups-printer-setup.md](cups-printer-setup.md) — CUPS et la file d'impression
3. [cloudflare-tunnel-setup.md](cloudflare-tunnel-setup.md) — l'accès distant

Sauf mention contraire, tout s'exécute **dans le conteneur**, en `root`.

---

## 1. Le conteneur et l'imprimante

Suivez [proxmox-lxc-setup.md](proxmox-lxc-setup.md) jusqu'à ce que le conteneur
**joigne l'imprimante** sur le réseau, puis
[cups-printer-setup.md](cups-printer-setup.md) jusqu'à ce qu'une page sorte avec
`lp`, **en recto verso et en couleur**. N'allez pas plus loin tant que ces deux
points ne sont pas acquis : l'application ne peut rien imprimer que CUPS ne
sache déjà imprimer, et une file mal créée ignore le recto verso sans le
moindre message d'erreur.

## 2. Les paquets

```bash
apt update && apt install -y nginx git unzip curl ca-certificates acl \
    php8.4-fpm php8.4-cli php8.4-sqlite3 php8.4-xml php8.4-curl \
    php8.4-mbstring php8.4-zip php8.4-intl \
    nodejs npm composer
```

```bash
php -v && composer --version && node -v
```

PHP doit être en 8.3 minimum (Laravel 13). Sur Debian 13 le paquet s'appelle
`php8.4-*` ; adaptez les noms si votre distribution fournit une autre version.

### Limites d'envoi de PHP

Les valeurs par défaut (2 Mo) rejetteraient tout PDF un peu conséquent, **avant**
même que la validation Laravel ne s'exécute. Dans
`/etc/php/8.4/fpm/conf.d/99-assoprint.ini` :

```ini
; Doit rester supérieur à PRINT_MAX_FILE_SIZE_KB (50 Mo par défaut)
upload_max_filesize = 64M
post_max_size = 64M
```

```bash
systemctl restart php8.4-fpm
```

## 3. Le code

```bash
useradd --system --home /var/www/assoprint --shell /usr/sbin/nologin assoprint
```

```bash
git clone git@github.com:HiCharly/assoprint.git /var/www/assoprint
```

```bash
cd /var/www/assoprint
```

Composer refuse de travailler en root sans confirmation, et pose la question à
chaque commande : `Do not run Composer as root/super user! Continue as
root/super user [yes]?`. Dans un conteneur dédié à cette seule application,
installer en root est sans conséquence — la mise en garde vise les postes
partagés, où des dépendances installées en root deviendraient inaccessibles aux
autres comptes. Autant couper l'invite :

```bash
export COMPOSER_ALLOW_SUPERUSER=1
```

```bash
composer install --no-dev --optimize-autoloader
```

```bash
npm ci && npm run build
```

`npm run build` régénère au passage les helpers de routes typés (Wayfinder), PHP
étant disponible ici.

Ces deux commandes créent `vendor/`, `node_modules/` et `public/build/` **au nom
de root**, donc hors de portée de www-data. C'est pour cette raison que la
section 6 vient après et non avant : c'est elle qui donne au serveur web la
lecture du code qu'il doit servir. Ne l'intervertissez pas avec la suite.

## 4. La configuration

```bash
cp .env.example .env
```

```bash
php artisan key:generate
```

Éditez ensuite `.env` :

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://impression.mondomaine.fr

DB_CONNECTION=sqlite
QUEUE_CONNECTION=database
MAIL_MAILER=log

SESSION_SECURE_COOKIE=true

PRINTER_NAME=HP_Color_LaserJet_Pro_M254dw
PRINT_MAX_COPIES=100
PRINT_MAX_FILE_SIZE_KB=51200

ADMIN_LOGIN=admin
ADMIN_PASSWORD=admin
```

`APP_DEBUG=false` n'est pas un détail : en `true`, la moindre erreur afficherait
une trace complète — chemins, extraits de code et valeurs de configuration — à
n'importe quel visiteur.

Laissez `SESSION_SECURE_COOKIE=false` tant que le tunnel n'est pas en service,
sinon plus aucune connexion n'aboutit.

## 5. La base et le compte administrateur

```bash
touch database/database.sqlite
```

```bash
php artisan migrate --force
```

```bash
php artisan db:seed --force
```

Le seeder crée le compte administrateur avec les identifiants ci-dessus.

**Connectez-vous immédiatement après cette étape.** Le mot de passe par défaut
est trivial et l'application est joignable depuis Internet : `admin`/`admin` est
ce que teste en premier n'importe quel robot. L'application impose le
remplacement du mot de passe dès la première connexion, ce qui referme la
fenêtre — mais elle reste ouverte tant que personne ne s'est connecté.

Si le déploiement n'est pas suivi d'une connexion immédiate, changez
`ADMIN_PASSWORD` dans le `.env` **avant** de lancer le seeder.

## 6. Les droits

Deux identités se partagent le dossier : `assoprint`, qui porte le code, et
`www-data`, sous lequel tournent PHP-FPM et le worker de queue. La règle tient
en une phrase : **www-data lit le code, il ne l'écrit jamais**, et n'obtient
l'écriture que sur les trois chemins qui en ont besoin — `storage/`,
`bootstrap/cache/` et `database/`. Une faille d'exécution ne peut alors pas se
déposer à demeure dans `app/` ou dans `public/`.

### Le socle

```bash
chown -R assoprint:www-data /var/www/assoprint
```

```bash
chmod -R u=rwX,g=rX,o= /var/www/assoprint
```

Le `X` majuscule ne pose le bit d'exécution que sur les dossiers, jamais sur un
PDF ni sur un fichier `.php`. Le `o=` ferme l'arbre aux autres comptes de la
machine : ni les documents des membres ni la base n'ont de raison d'être
lisibles au-delà des deux identités ci-dessus.

### Les droits des fichiers à venir

`chmod` ne règle que les fichiers présents à l'instant où il passe. Ceux créés
ensuite tiennent leurs droits de l'umask du processus qui les crée : un
`artisan` lancé en root laisse derrière lui un `laravel.log` que www-data ne
peut plus écrire, et PHP-FPM dépose des fichiers que le déployeur ne peut plus
reprendre. La panne qui s'ensuit est silencieuse — Laravel n'a aucun moyen de
journaliser qu'il n'arrive pas à journaliser, et la page ne montre qu'un 500 nu.

Les ACL par défaut règlent la question à la source : tout ce qui naîtra dans ces
dossiers en hérite, quel que soit son créateur et quel que soit son umask.

```bash
setfacl -R -m u:www-data:rwX -m u:assoprint:rwX /var/www/assoprint/storage /var/www/assoprint/bootstrap/cache /var/www/assoprint/database
```

```bash
setfacl -dR -m u:www-data:rwX -m u:assoprint:rwX /var/www/assoprint/storage /var/www/assoprint/bootstrap/cache /var/www/assoprint/database
```

La première commande vaut pour l'existant, la seconde — `-d`, pour _default_ —
pour tout ce qui sera créé ensuite. Les deux posent les droits dans les deux
sens, pour www-data **et** pour assoprint : une ACL à sens unique ne ferait que
déplacer le blocage vers l'autre identité.

`database/` figure dans la liste au même titre que les deux autres : SQLite
écrit son journal **à côté** de la base, le dossier doit donc être inscriptible,
pas seulement le fichier.

### Vérifier

```bash
sudo -u www-data php artisan optimize:clear
```

Cette forme, plutôt que `php artisan` en root, reste la bonne habitude : les
caches et les journaux naissent sous l'identité qui les relira. Les ACL font
qu'un oubli n'est plus fatal, elles ne le rendent pas souhaitable. `sudo -u` ne
bute pas sur le `nologin` de www-data : il exécute une commande, il n'ouvre pas
de session.

```bash
getfacl /var/www/assoprint/storage/logs
```

Une entrée qui annonce `rwx` mais s'affiche suivie de `#effective:r-x` signale un
masque ACL raboté par un `chmod` passé après le `setfacl`. L'ordre compte : le
`setfacl` vient toujours en dernier, à l'installation comme à chaque mise à jour
(section 12).

### CUPS

L'utilisateur qui exécute PHP doit par ailleurs appartenir au groupe `lp` pour
parler à l'imprimante :

```bash
usermod -aG lp www-data
```

## 7. Les caches de production

```bash
php artisan optimize
```

Une seule commande, qui met en cache la configuration, les événements, les
routes et les vues.

À refaire après **chaque** modification du `.env` : une valeur changée y reste
ignorée tant que le cache de configuration n'a pas été régénéré. C'est le piège
classique du « j'ai pourtant corrigé le fichier ».

L'inverse, utile en cas de doute ou pour diagnostiquer :

```bash
php artisan optimize:clear
```

## 8. nginx

`/etc/nginx/sites-available/assoprint` :

```nginx
server {
    listen 127.0.0.1:8000;
    server_name impression.mondomaine.fr;

    # La racine est public/ : rien d'autre du dépôt n'est servi, et surtout pas
    # storage/app/print-jobs, où dorment les PDF des membres.
    root /var/www/assoprint/public;
    index index.php;

    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;

        # nginx ne réserve que 4 Ko aux en-têtes d'une réponse FastCGI. Une page
        # un peu fournie (CSP, cookies de session, en-têtes de sécurité) peut
        # s'en approcher, et le dépassement se solde par un 502 sans la moindre
        # trace côté Laravel : PHP a répondu, c'est nginx qui coupe.
        fastcgi_buffer_size 32k;
        fastcgi_buffers 8 32k;
        fastcgi_busy_buffers_size 64k;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

`listen 127.0.0.1:8000` : nginx n'écoute que sur la boucle locale. Le seul
chemin depuis l'extérieur passe par `cloudflared`.

```bash
ln -s /etc/nginx/sites-available/assoprint /etc/nginx/sites-enabled/
```

```bash
rm -f /etc/nginx/sites-enabled/default
```

```bash
nginx -t && systemctl reload nginx
```

## 9. Le worker de queue

Sans lui, les tâches restent indéfiniment « en attente » : rien n'est envoyé à
l'imprimante.

`/etc/systemd/system/laravel-queue.service` :

```ini
[Unit]
Description=AssoPrint queue worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=3
WorkingDirectory=/var/www/assoprint
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

```bash
systemctl daemon-reload && systemctl enable --now laravel-queue
```

```bash
systemctl status laravel-queue
```

`--max-time=3600` fait redémarrer le worker toutes les heures : c'est la parade
classique aux fuites de mémoire d'un processus PHP de longue durée, et
`Restart=always` le relance aussitôt.

## 10. Le tunnel

Suivez [cloudflare-tunnel-setup.md](cloudflare-tunnel-setup.md). Le service
pointe vers `http://localhost:8000`, c'est-à-dire nginx.

## 11. Test de bout en bout

1. Ouvrez `https://impression.mondomaine.fr` : la page de connexion s'affiche.
2. Connectez-vous avec le compte administrateur, choisissez un nouveau mot de
   passe (l'application l'impose).
3. Créez un compte membre : le mot de passe temporaire s'affiche une fois.
4. Déposez un PDF depuis « Imprimer un document ».
5. Sur « Mes impressions », la tâche passe de _en attente_ à _impression en
   cours_, puis à _imprimé_.
6. La page sort de l'imprimante.

Si la tâche reste en attente, le worker ne tourne pas :

```bash
journalctl -u laravel-queue -n 50 --no-pager
```

Si elle passe en erreur, le message affiché dans l'interface vient de CUPS
lui-même — il dit en général exactement ce qui manque (imprimante non
configurée, bac vide, file inconnue).

## 12. Mettre à jour l'application

```bash
cd /var/www/assoprint && git pull
```

```bash
composer install --no-dev --optimize-autoloader
```

```bash
npm ci && npm run build
```

```bash
chown -R assoprint:www-data /var/www/assoprint
```

```bash
chmod -R u=rwX,g=rX,o= /var/www/assoprint
```

```bash
setfacl -R -m u:www-data:rwX -m u:assoprint:rwX /var/www/assoprint/storage /var/www/assoprint/bootstrap/cache /var/www/assoprint/database
```

```bash
php artisan migrate --force
```

```bash
php artisan optimize
```

```bash
systemctl restart laravel-queue
```

Le `chown` et le `chmod` reprennent les fichiers déposés par `git pull`,
`composer` et `npm` : lancés en root, ils appartiennent à root et l'arbre étant
fermé aux autres comptes (`o=`), www-data ne pourrait pas lire le code neuf. Le
`setfacl` vient **après** eux, et non l'inverse : le `chmod` rabote au passage
le masque ACL des dossiers d'écriture, et cette commande le rétablit. Les
entrées par défaut posées à la section 6, elles, n'ont pas à être redonnées.

Le redémarrage du worker n'est pas facultatif : un worker déjà lancé garde en
mémoire l'ancienne version du code.

## 13. Sauvegarde

Tout l'état tient dans deux chemins :

| Chemin                     | Contenu                             |
| -------------------------- | ----------------------------------- |
| `database/database.sqlite` | comptes, tâches, compteurs de pages |
| `storage/app/print-jobs/`  | les PDF déposés                     |

```bash
sqlite3 /var/www/assoprint/database/database.sqlite ".backup '/var/backups/assoprint-$(date +%F).sqlite'"
```

`.backup` produit une copie cohérente même si l'application écrit pendant la
sauvegarde, ce qu'un simple `cp` ne garantit pas. Le plus simple reste la
sauvegarde de conteneur de Proxmox, qui prend les deux d'un coup.

## 14. Libérer de l'espace, plus tard

```bash
php artisan print-jobs:purge --older-than=90 --dry-run
```

```bash
php artisan print-jobs:purge --older-than=90
```

La commande n'est pas planifiée, et c'est voulu : tant qu'un PDF est là, sa
tâche reste duplicable. Les tâches elles-mêmes ne sont jamais supprimées — ce
sont elles qui portent les compteurs de pages.

---

[← Retour au README](../README.md)
