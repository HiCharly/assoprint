# AssoPrint — impression à distance

Application web d'impression à distance pour un club de basket : un membre
dépose un PDF depuis n'importe où, choisit ses options d'impression, et le
document sort sur l'imprimante du club — une HP Color LaserJet M254dw branchée
en USB à un conteneur LXC Proxmox.

L'ensemble tourne sur l'infrastructure Proxmox existante, sans aucun service
payant : SQLite comme base de données, CUPS pour l'impression, et un tunnel
Cloudflare (gratuit) pour l'accès distant, sans ouvrir le moindre port entrant.

## Fonctionnalités

### Membre

- Dépôt d'un PDF avec choix du nombre de copies, du recto/verso et de la couleur
- Suivi en temps réel de ses tâches d'impression (en attente, en cours, imprimé, erreur)
- Duplication d'une tâche déjà envoyée, en ajustant librement les réglages

### Administrateur

- Création, modification, activation et désactivation des comptes
- Réinitialisation du mot de passe d'un membre (mot de passe temporaire affiché
  une seule fois — l'application n'envoie aucun email)
- Compteur cumulé de pages imprimées par membre
- Vue globale de toutes les tâches d'impression, pour diagnostiquer l'imprimante
- Relance d'une tâche d'un membre, réglages ajustables

## Stack technique

| Couche          | Choix                                                                      |
| --------------- | -------------------------------------------------------------------------- |
| Backend         | Laravel 13 (PHP 8.3), Inertia 2                                            |
| Frontend        | React 19, TypeScript, Tailwind CSS 4, shadcn/ui                            |
| Base de données | SQLite (simple fichier, aucun service à administrer)                       |
| File d'attente  | Driver `database` + `php artisan queue:work` en service systemd            |
| Impression      | CUPS (`lp`, `lpstat`), `pdfinfo` (poppler-utils) pour le comptage de pages |
| Accès distant   | Cloudflare Tunnel (`cloudflared`)                                          |

L'authentification s'appuie sur Laravel Fortify, dont seules les briques utiles
sont activées : connexion par session et changement de mot de passe. Inscription
publique, vérification d'email, réinitialisation par email, double
authentification et passkeys sont désactivées et leur code retiré du projet.

## Démarrage en local

Le projet a besoin de PHP 8.3, de `pdfinfo` et des clients CUPS. Pour ne rien
avoir à installer sur le poste de développement, une image Docker de
développement fournit l'ensemble ; Node tourne, lui, directement sur le poste.

> La production, elle, n'utilise pas Docker : PHP y est installé nativement dans
> le conteneur LXC.

```bash
docker compose -f docker-compose.dev.yml build
cp .env.example .env
docker compose -f docker-compose.dev.yml run --rm app composer install
docker compose -f docker-compose.dev.yml run --rm app php artisan key:generate
docker compose -f docker-compose.dev.yml run --rm app php artisan migrate --seed
docker compose -f docker-compose.dev.yml run --rm app php artisan wayfinder:generate --with-form
npm install
```

`php artisan migrate --seed` crée le compte administrateur initial et affiche son
mot de passe **une seule fois** : notez-le avant de fermer le terminal.

Ensuite, deux terminaux :

```bash
docker compose -f docker-compose.dev.yml up
```

```bash
npm run dev
```

L'application répond sur http://localhost:8000. Le premier terminal fait tourner
le serveur web _et_ le worker de queue, sans lequel aucune impression ne part.

### Commandes utiles

```bash
docker compose -f docker-compose.dev.yml run --rm app php artisan test
```

```bash
docker compose -f docker-compose.dev.yml run --rm app composer lint
```

```bash
docker compose -f docker-compose.dev.yml run --rm app composer types:check
```

```bash
npm run check && npm run types:check
```

Les helpers de routes typés (`resources/js/routes`, `resources/js/actions`) sont
générés par Wayfinder, qui a besoin de PHP. Sur un poste sans PHP local, le
plugin Vite correspondant se désactive tout seul : après toute modification de
`routes/`, régénérez-les avec `php artisan wayfinder:generate --with-form` dans
le conteneur.
