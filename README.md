# AssoPrint — impression à distance

Application web d'impression à distance pour un club de basket : un membre
dépose un PDF depuis n'importe où, choisit ses options d'impression, et le
document sort sur l'imprimante du club — une HP Color LaserJet Pro M254dw
branchée en USB à un conteneur LXC Proxmox.

L'ensemble tourne sur l'infrastructure Proxmox existante, sans aucun service
payant : SQLite comme base de données, CUPS pour l'impression, et un tunnel
Cloudflare (gratuit) pour l'accès distant, sans ouvrir le moindre port entrant.

## Fonctionnalités

### Membre

- Dépôt d'un PDF avec choix du nombre de copies, du recto/verso et de la couleur
- Suivi des tâches d'impression, rafraîchi tout seul tant qu'une tâche bouge :
  en attente, impression en cours, imprimé, erreur — avec le message de
  l'imprimante quand ça coince
- Duplication d'une tâche déjà envoyée, en ajustant librement les réglages
- Tableau de bord : compteur de pages, impressions en cours, dernières tâches

### Administrateur

- Création, modification, activation et désactivation des comptes
- Réinitialisation du mot de passe d'un membre : mot de passe temporaire affiché
  une seule fois, à transmettre hors de l'application, qui devra être remplacé à
  la première connexion
- Compteur cumulé de pages imprimées par membre
- Vue globale de toutes les tâches, tous membres confondus, pour diagnostiquer
  l'imprimante
- Relance d'une tâche d'un membre, réglages ajustables

## Stack technique

| Couche          | Choix                                                                      |
| --------------- | -------------------------------------------------------------------------- |
| Backend         | Laravel 13 (PHP 8.3+), Inertia 3, Fortify                                  |
| Frontend        | React 19, TypeScript, Tailwind CSS 4, shadcn/ui                            |
| Base de données | SQLite (simple fichier, aucun service à administrer)                       |
| File d'attente  | Driver `database` + `php artisan queue:work` en service systemd            |
| Impression      | CUPS (`lp`, `lpstat`), `pdfinfo` (poppler-utils) pour le comptage de pages |
| Accès distant   | Cloudflare Tunnel (`cloudflared`)                                          |

L'authentification s'appuie sur Laravel Fortify, dont seules les briques utiles
sont activées : connexion par session et changement de mot de passe. Inscription
publique, vérification d'email, réinitialisation par email, double
authentification et passkeys sont désactivées et leur code retiré du projet.

## Documentation

| Document                                                           | Contenu                                                                       |
| ------------------------------------------------------------------ | ----------------------------------------------------------------------------- |
| [docs/deployment.md](docs/deployment.md)                           | le déploiement de bout en bout, du conteneur vide à la première page imprimée |
| [docs/proxmox-lxc-setup.md](docs/proxmox-lxc-setup.md)             | création du conteneur LXC et passthrough USB de l'imprimante                  |
| [docs/cups-printer-setup.md](docs/cups-printer-setup.md)           | installation de CUPS, détection de la M254dw, impression de test              |
| [docs/cloudflare-tunnel-setup.md](docs/cloudflare-tunnel-setup.md) | tunnel Cloudflare, DNS et sous-domaine                                        |
| [docs/security.md](docs/security.md)                               | les mesures de sécurité réellement en place, fichier par fichier              |

## Démarrage en local

Le projet a besoin de PHP 8.3+, de `pdfinfo` et des clients CUPS. Pour ne rien
avoir à installer sur le poste de développement, une image Docker de
développement fournit l'ensemble ; Node tourne, lui, directement sur le poste.

> La production, elle, n'utilise pas Docker : PHP y est installé nativement dans
> le conteneur LXC.

```bash
docker compose -f docker-compose.dev.yml build
```

```bash
cp .env.example .env
```

```bash
docker compose -f docker-compose.dev.yml run --rm app composer install
```

```bash
docker compose -f docker-compose.dev.yml run --rm app php artisan key:generate
```

```bash
docker compose -f docker-compose.dev.yml run --rm app php artisan migrate --seed
```

```bash
npm install && npm run build
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

Sans imprimante branchée sur le poste, les tâches finissent en erreur avec un
message explicite : c'est le comportement attendu, et c'est aussi une façon de
vérifier que la chaîne complète fonctionne.

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

```bash
docker compose -f docker-compose.dev.yml run --rm app php artisan print-jobs:purge --older-than=90 --dry-run
```

Les helpers de routes typés (`resources/js/routes`, `resources/js/actions`) sont
générés par Wayfinder, qui a besoin de PHP. Sur un poste sans PHP local, le
plugin Vite correspondant se désactive tout seul : après toute modification de
`routes/`, régénérez-les avec

```bash
docker compose -f docker-compose.dev.yml run --rm app php artisan wayfinder:generate --with-form
```

## Organisation du dépôt

```
app/
  Enums/            Duplex, ColorMode, PrintJobStatus — les seules valeurs admises
  Http/Middleware/  actif, administrateur, mot de passe à changer, en-têtes de sécurité
  Jobs/             envoi à CUPS puis suivi de la tâche jusqu'à sa sortie de file
  Policies/         cloisonnement des tâches entre membres
  Services/         CupsPrintService (unique point de contact avec lp/lpstat/pdfinfo)
docker/dev/         image de développement uniquement
docs/               installation, déploiement, sécurité
resources/js/       pages Inertia et composants React
```

## Contribution

Développement par branches de fonctionnalité et _pull requests_, avec CI
obligatoire (tests, Pint, PHPStan, oxlint, TypeScript, `composer audit`,
`npm audit`). La protection de branche côté GitHub demanderait un abonnement
payant sur un dépôt privé : la discipline est donc tenue côté processus, comme
expliqué dans [docs/security.md](docs/security.md).
