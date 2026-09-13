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
apt update && apt install -y nginx git unzip \
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

```bash
composer install --no-dev --optimize-autoloader
```

```bash
npm ci && npm run build
```

`npm run build` régénère au passage les helpers de routes typés (Wayfinder), PHP
étant disponible ici.

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

ADMIN_EMAIL=admin@mondomaine.fr
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

Le seeder affiche **une seule fois** le mot de passe du compte administrateur.
Notez-le maintenant : il n'est stocké nulle part en clair, et devra être changé
dès la première connexion.

## 6. Les droits

```bash
chown -R assoprint:www-data /var/www/assoprint
```

```bash
chmod -R 775 /var/www/assoprint/storage /var/www/assoprint/bootstrap/cache
```

```bash
chmod 664 /var/www/assoprint/database/database.sqlite
```

```bash
chmod 775 /var/www/assoprint/database
```

SQLite écrit un fichier journal **à côté** de la base : le dossier `database/`
doit donc être inscriptible, pas seulement le fichier.

L'utilisateur qui exécute PHP doit par ailleurs appartenir au groupe `lp` pour
parler à CUPS :

```bash
usermod -aG lp www-data
```

## 7. Les caches de production

```bash
php artisan config:cache
```

```bash
php artisan route:cache
```

```bash
php artisan view:cache
```

À refaire après chaque modification du `.env` — une valeur changée dans `.env`
reste ignorée tant que `config:cache` n'a pas été relancé.

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
php artisan migrate --force
```

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

```bash
systemctl restart laravel-queue
```

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
