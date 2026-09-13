# Sécurité : ce qui est réellement en place

[← Retour au README](../README.md)

Ce document décrit les mesures **implémentées**, avec le fichier où chacune vit,
pour qu'un audit ultérieur puisse les vérifier une par une plutôt que de les
croire sur parole. Ce qui n'est délibérément pas fait est dit aussi, à la fin.

---

## Authentification

| Mesure                                                                             | Où                                                                 |
| ---------------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| Hachage des mots de passe par le driver par défaut de Laravel (bcrypt, coût 12)    | `config/hashing.php`, `BCRYPT_ROUNDS`                              |
| Limitation des tentatives de connexion : 5 par minute et par couple identifiant/IP | `FortifyServiceProvider::configureRateLimiting()`                  |
| Cookie de session `http_only` et `same_site=lax`                                   | `config/session.php`                                               |
| Cookie de session `secure` en production                                           | `SESSION_SECURE_COOKIE=true` (voir [deployment.md](deployment.md)) |
| Session régénérée et autres sessions fermées au changement de mot de passe         | `ForcedPasswordChangeController::update()`                         |
| Aucune inscription publique                                                        | `config/fortify.php` : `features` vide                             |

Un compte désactivé est refusé **dès la connexion**
(`FortifyServiceProvider::configureAuthentication()`), avec le message d'erreur
générique des identifiants invalides : distinguer « compte désactivé » de « mot
de passe faux » révélerait à un inconnu qu'un identifiant existe bel et bien.

Les membres se connectent avec un **identifiant**, pas une adresse email :
l'application n'envoyant aucun message, une adresse n'aurait servi à rien, et
tous les membres du club n'en ont pas forcément une.

### Mots de passe oubliés, sans email

Le club ne dispose d'aucun SMTP, et `MAIL_MAILER=log` garantit qu'aucun message
ne part. Le flux classique de réinitialisation par email est donc retiré, code
compris — pages, contrôleurs, table `password_reset_tokens`.

À la place, un administrateur génère un mot de passe temporaire
(`Str::password(12)`) depuis la fiche du membre. Ce mot de passe :

- n'est **jamais** stocké en clair : seul son hachage est enregistré ;
- n'est **jamais** journalisé, y compris dans le journal d'audit ;
- transite par le flash de session, le temps d'une redirection, et disparaît au
  premier rafraîchissement de page ;
- oblige son porteur à en choisir un autre avant toute autre action, via le
  middleware `EnsureUserHasChosenPassword`.

Ce dernier point compte : un mot de passe communiqué de vive voix ou par SMS est
compromis par construction, il ne doit servir qu'une fois.

## Autorisation

| Garde                         | Portée                                                                                              |
| ----------------------------- | --------------------------------------------------------------------------------------------------- |
| `EnsureUserIsActive`          | tout le périmètre authentifié : une désactivation ferme la session en cours dès la requête suivante |
| `EnsureUserIsAdmin`           | toutes les routes `/admin/*`                                                                        |
| `EnsureUserHasChosenPassword` | tout, sauf la page de changement et la déconnexion                                                  |
| `PrintJobPolicy`              | un membre ne duplique que ses propres tâches, et ne relit que ses propres documents                 |

Aucun contrôleur ne se contente d'un identifiant d'URL : la relance
administrateur utilise des **liaisons de modèle scopées**
(`routes/admin.php`), qui imposent que la tâche appartienne au membre présent
dans l'URL, sinon la route répond 404.

Deux garde-fous évitent qu'un administrateur ne se verrouille dehors : il ne
peut ni se désactiver, ni se retirer ses propres droits.

## Dépôt de fichiers

| Mesure                                                                                    | Où                                               |
| ----------------------------------------------------------------------------------------- | ------------------------------------------------ |
| Type réel vérifié sur le contenu (`mimes:pdf` + `mimetypes:application/pdf`, via `finfo`) | `StorePrintJobRequest`                           |
| Taille plafonnée côté serveur                                                             | `StorePrintJobRequest`, `PRINT_MAX_FILE_SIZE_KB` |
| Nom sur disque tiré au hasard (UUID)                                                      | `PrintJobSubmissionService::submitUpload()`      |
| Stockage hors du webroot                                                                  | disque `print-jobs` → `storage/app/print-jobs/`  |

Le nom fourni par le membre n'est conservé que pour l'affichage, et passe par
`basename()` : il ne peut donc pas porter de chemin.

Un document déposé peut être **relu** par son propriétaire, et par un
administrateur — qui doit pouvoir vérifier ce qui est sorti de l'imprimante,
notamment avant de relancer une tâche. Il n'est jamais servi par nginx : la
route `print.jobs.document` le lit sur un disque situé hors du webroot, après
que `PrintJobPolicy::view()` a tranché. La réponse porte
`Cache-Control: private, no-store`, pour que ni Cloudflare ni le navigateur
n'en gardent une copie.

Consulter n'est pas imprimer : la duplication reste réservée au propriétaire, la
relance d'un document par un administrateur passant par le back-office, qui la
journalise.

Un test vérifie qu'un script shell simplement renommé en `.pdf` est refusé, et
un autre qu'un nom contenant de la syntaxe shell n'atteint jamais le disque.

## Exécution de commandes système

`CupsPrintService` est le seul endroit qui parle à `lp`, `lpstat` et `pdfinfo`.
Toutes les commandes y sont construites **en tableau d'arguments** et exécutées
par `Symfony\Component\Process\Process`, donc sans shell : aucune valeur ne peut
être réinterprétée comme de la syntaxe shell, quelle que soit son origine.

Les options d'impression ne peuvent contenir que des valeurs connues :

- `duplex` et `color_mode` sont des enums PHP castés par Eloquent, sur des
  colonnes elles-mêmes contraintes en base — un test vérifie que la base refuse
  toute autre valeur, même écrite directement en SQL ;
- `copies` est un simple entier : il est donc **re-validé explicitement** juste
  avant la construction de la commande, indépendamment de la Form Request, car
  une tâche peut aussi naître d'une duplication ou d'une relance ;
- `PRINTER_NAME` vient de la configuration serveur, jamais d'une requête.

## Base de données

Eloquent et le query builder exclusivement, aucune requête SQL concaténée. Les
colonnes sensibles (`is_admin`, `is_active`, `must_change_password`) sont **hors
de `$fillable`** : elles ne peuvent pas être modifiées par assignation de masse
depuis une requête, seulement par `forceFill()` dans un contrôleur qui a
d'abord vérifié les droits.

## En-têtes HTTP

`SecurityHeaders` ajoute à chaque réponse :

```
Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-…';
  style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:;
  connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self';
  frame-ancestors 'none'
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Referrer-Policy: same-origin
Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()
```

Le nonce est régénéré à chaque requête : aucun script inline ne s'exécute sans
lui, ce qui rend inopérante l'injection d'un `<script>` dans la page.

`style-src` conserve `unsafe-inline`, et c'est un compromis assumé : les
composants Radix positionnent leurs menus via des attributs `style`, qu'un nonce
ne couvre pas — et un nonce dans `style-src` désactiverait justement
`unsafe-inline` aux yeux du navigateur. Le risque résiduel (injection de CSS)
est sans commune mesure avec celui d'un script.

Côté rendu, React échappe tout par défaut ; le projet n'utilise
`dangerouslySetInnerHTML` nulle part.

## Réseau

- CUPS n'écoute que sur `localhost:631`, à vérifier explicitement (voir
  [cups-printer-setup.md](cups-printer-setup.md)) ;
- nginx n'écoute que sur `127.0.0.1:8000` ;
- `cloudflared` n'établit que des connexions **sortantes** : aucun port entrant
  n'est ouvert sur la box, et l'adresse IP du club n'est pas exposée ;
- la configuration d'ingress du tunnel se termine par `http_status:404`, de
  sorte que le tunnel ne dessert rien d'autre que l'application ;
- l'application ne fait confiance aux en-têtes `X-Forwarded-*` que s'ils
  viennent de la boucle locale (`bootstrap/app.php`), c'est-à-dire de
  `cloudflared` lui-même. Un en-tête forgé ne peut donc ni détourner les URL
  générées vers un autre domaine, ni brouiller l'adresse retenue par la
  limitation des tentatives de connexion.

## Journal d'audit

Création de compte, activation, désactivation, réinitialisation de mot de passe
et relance sont journalisées avec `admin_id` et `user_id`, et rien d'autre. Un
test vérifie précisément le contenu du contexte journalisé, pour garantir
qu'aucun mot de passe temporaire ne s'y glisse.

## Chaîne de développement

- CI sur chaque _pull request_ : tests, Pint, PHPStan, oxlint, `tsc --noEmit` ;
- `composer audit` et `npm audit` à chaque exécution de la CI, pour détecter une
  dépendance vulnérable ;
- Dependabot surveille les actions GitHub utilisées ;
- `.env` n'est pas versionné ; `.env.example` ne contient aucune valeur
  sensible ;
- les PDF déposés sont exclus du dépôt.

## Ce qui n'est volontairement pas fait

- **Pas de double authentification, pas de passkeys.** Le starter kit les
  proposait ; elles ont été retirées, code compris. Pour une dizaine de
  bénévoles qui impriment des convocations, elles ajouteraient surtout du
  support à assurer.
- **Pas d'adresse email du tout.** Les comptes sont créés par un administrateur
  qui connaît les membres ; un identifiant suffit à les distinguer, et
  l'application n'a aucun message à leur envoyer.
- **Un mot de passe administrateur initial trivial** (`admin`/`admin`), à la
  demande explicite du club. Le risque est borné par le middleware
  `EnsureUserHasChosenPassword`, qui interdit toute autre action tant que ce mot
  de passe n'a pas été remplacé, et par les règles de production — douze
  caractères, casse mixte, chiffres et symboles — qui s'appliquent au
  remplaçant. La fenêtre reste ouverte tant que personne ne s'est connecté :
  `ADMIN_PASSWORD` permet d'y couper court si le déploiement n'est pas suivi
  d'une connexion immédiate.
- **Pas de chiffrement des PDF au repos.** Ils sont hors du webroot et
  inaccessibles par HTTP ; le chiffrement supposerait une gestion de clés qui
  n'apporterait rien tant que le conteneur lui-même n'est pas compromis.
- **Pas de protection de branche côté GitHub.** Elle demande un abonnement
  GitHub Pro sur un dépôt privé, ce qui contredirait la contrainte de coût
  mensuel nul. La discipline (branche de fonctionnalité, _pull request_, CI
  verte) est tenue côté processus, sans être imposée par le serveur.

---

[← Retour au README](../README.md)
