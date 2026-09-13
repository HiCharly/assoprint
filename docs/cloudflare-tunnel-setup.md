# Accès distant par tunnel Cloudflare

[← Retour au README](../README.md)

L'application doit être joignable depuis n'importe où, sans ouvrir le moindre
port entrant sur la box du club. Un tunnel Cloudflare répond exactement à ce
besoin : `cloudflared` établit une connexion **sortante** vers Cloudflare, qui
lui renvoie le trafic du sous-domaine choisi. Le pare-feu n'a rien à laisser
entrer, et l'adresse IP du club n'est jamais exposée.

C'est gratuit, et ça le reste : le plan gratuit de Cloudflare couvre ce type
d'usage sans limite pertinente ici.

Prérequis : un domaine déjà géré par Cloudflare (les serveurs DNS du domaine
pointent vers Cloudflare). Le sous-domaine exact — `impression.mondomaine.fr`,
par exemple — est à choisir au moment du déploiement.

Toutes les commandes s'exécutent **dans le conteneur**.

---

## 1. Installer cloudflared

Le modèle Debian minimal ne fournit pas `curl`, et `ca-certificates` lui est
indispensable pour valider le certificat de GitHub :

```bash
apt update && apt install -y curl ca-certificates
```

```bash
curl -L https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64 -o /usr/local/bin/cloudflared
```

```bash
chmod +x /usr/local/bin/cloudflared
```

```bash
cloudflared --version
```

## 2. Associer le tunnel au compte Cloudflare

```bash
cloudflared tunnel login
```

La commande affiche une URL. Ouvrez-la depuis n'importe quel navigateur déjà
connecté à Cloudflare, puis choisissez le domaine à autoriser. Un certificat est
alors déposé dans `/root/.cloudflared/cert.pem`.

## 3. Créer le tunnel

```bash
cloudflared tunnel create impression-basket
```

La sortie donne un identifiant (`<TUNNEL_ID>`) et le chemin du fichier
d'identifiants, `/root/.cloudflared/<TUNNEL_ID>.json`. **Ce fichier est un
secret** : quiconque le possède peut publier du contenu sur le sous-domaine.

```bash
chmod 600 /root/.cloudflared/<TUNNEL_ID>.json
```

## 4. Pointer le sous-domaine vers le tunnel

```bash
cloudflared tunnel route dns impression-basket impression.mondomaine.fr
```

Cette commande crée l'enregistrement DNS côté Cloudflare. Rien à configurer chez
le registrar.

## 5. Déclarer ce que le tunnel dessert

Le dossier n'existe pas : `cloudflared` ne le crée qu'à l'installation du
service, c'est-à-dire après cette étape.

```bash
mkdir -p /etc/cloudflared
```

C'est bien ce chemin qu'il faut : `cloudflared service install` y lit sa
configuration. Un `config.yml` déposé dans `/root/.cloudflared/` ne sert, lui,
qu'aux exécutions manuelles de `cloudflared tunnel run`, et serait ignoré par le
service systemd.

`/etc/cloudflared/config.yml` :

```yaml
tunnel: <TUNNEL_ID>
credentials-file: /root/.cloudflared/<TUNNEL_ID>.json

ingress:
    - hostname: impression.mondomaine.fr
      service: http://localhost:8000
    # Tout ce qui n'est pas le sous-domaine ci-dessus est refusé : le tunnel ne
    # doit jamais servir de porte d'entrée vers autre chose, à commencer par
    # l'interface d'administration de CUPS sur le port 631.
    - service: http_status:404
```

Pour l'écrire sans recopier l'identifiant à la main :

```bash
ID=$(cloudflared tunnel list | awk '/impression-basket/ {print $1}'); cat > /etc/cloudflared/config.yml <<EOF
tunnel: $ID
credentials-file: /root/.cloudflared/$ID.json

ingress:
  - hostname: impression.mondomaine.fr
    service: http://localhost:8000
  - service: http_status:404
EOF
cat /etc/cloudflared/config.yml
```

`http://localhost:8000` est le port que sert nginx dans le conteneur (voir
[deployment.md](deployment.md)). Le trafic entre Cloudflare et le visiteur est
en HTTPS ; la liaison interne reste en clair, mais elle ne quitte jamais le
conteneur.

## 6. Lancer le service

```bash
cloudflared service install
```

```bash
systemctl enable --now cloudflared
```

```bash
systemctl status cloudflared
```

Quatre connexions sortantes établies vers des centres Cloudflare différents
signalent un tunnel en bonne santé.

## 7. Vérifier

```bash
curl -I https://impression.mondomaine.fr/login
```

Une réponse `200` accompagnée des en-têtes `content-security-policy` et
`x-frame-options` confirme que l'application répond bien à travers le tunnel.

Pensez alors à passer `APP_URL` et `SESSION_SECURE_COOKIE` dans le `.env` :

```
APP_URL=https://impression.mondomaine.fr
SESSION_SECURE_COOKIE=true
```

```bash
php artisan optimize:clear && php artisan optimize
```

`SESSION_SECURE_COOKIE=true` interdit au navigateur d'émettre le cookie de
session ailleurs qu'en HTTPS. À ne faire qu'une fois le tunnel en service :
activé trop tôt, plus personne ne peut se connecter en local.

## 8. Deux durcissements optionnels, gratuits

- **Cloudflare Access** : place une authentification Cloudflare _devant_
  l'application (code par email, Google, etc.). Le plan gratuit couvre jusqu'à
  50 utilisateurs. Utile si vous préférez que l'application ne soit même pas
  atteignable par un inconnu.
- **Règle de pays** : dans le WAF Cloudflare, refuser tout ce qui ne vient pas
  de France réduit d'un coup le bruit des scanners automatiques.

Ni l'un ni l'autre n'est nécessaire au fonctionnement : l'application se défend
déjà seule (voir [security.md](security.md)).

---

[← Retour au README](../README.md)
