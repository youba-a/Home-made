# Home Made

**Projet académique en cours de développement · 2026–2027**

Home Made est une place de marché locale conçue dans le cadre d’un bachelier en informatique, orientation développement d’applications. Elle permet de découvrir des objets artisanaux et des repas faits maison, puis de consulter leurs conditions de retrait sur rendez-vous. Le service Catalogue fonctionne en local. Le parcours d’achat fait partie des prochaines étapes.

## Fonctionnalités réalisées

- Catalogue public de créations et de repas, avec recherche, filtres par type et zone, et pagination.
- Fiches avec photo, prix, disponibilité et informations publiques de retrait. L’adresse exacte est exclue des réponses publiques.
- Inscription, connexion et déconnexion, avec session Laravel et protection CSRF. Le profil permet de renseigner un prénom, un nom et une commune facultative.
- Publication et modification d’objets et de repas avec photo, commune et créneau de retrait. Une offre de repas indique aussi les portions et la limite d’achat.
- Espace **Mes annonces** réservé au vendeur, avec modification et suppression soumises aux règles de propriété et de commande.
- Interface adaptative pour ordinateur et mobile, avec menu de navigation mobile.

## Technologies et architecture

| Dossier | Rôle | Technologies |
| --- | --- | --- |
| `frontend/` | Interface et appels à l’API | React, JavaScript, Vite, CSS |
| `backend/` | Service Catalogue, comptes et API REST | PHP 8.3+, Laravel 13, Eloquent |
| `backend/database/` | Schéma, migrations et données de démonstration | MySQL, SQL |

En local, React appelle les routes `/api/v1` via le proxy Vite. Laravel valide les requêtes, lit ou écrit dans MySQL et renvoie du JSON. Les photos sont stockées sur le disque public Laravel. MySQL en conserve le chemin.

## Compétences mises en pratique

- **Conception UML et modélisation des données** avec un diagramme de classes, un diagramme entité-association et des diagrammes de séquence.
- **Développement frontend et backend** avec des composants React réutilisables, des formulaires, des contrôleurs Laravel et des tests de parcours.
- **API et bases de données** avec des routes REST, une authentification par session, des migrations, des relations et des transactions.
- **Git et organisation inspirée d’Agile/Scrum** avec le suivi des changements dans Git, la priorisation des fonctionnalités restantes et une progression par incréments.

## Installation locale

Prérequis : PHP 8.3 ou plus avec les extensions du projet, dont PDO MySQL, Fileinfo et GD, ainsi que Composer, Node.js 22.12 ou plus, npm et MySQL en cours d’exécution. L’extension PDO SQLite est utilisée par les tests.

```bash
git clone https://github.com/youba-a/Home-made.git
cd Home-made/backend
composer install
```

Copier `.env.example` vers `.env` (`cp .env.example .env` ou `Copy-Item .env.example .env` dans PowerShell). Créer une base MySQL vide, par exemple `home_made_db`, puis renseigner les paramètres `DB_*` dans le fichier local. Définir `APP_URL=http://127.0.0.1:8000` pour les liens vers les photos.

```bash
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8000
```

Le seeder charge les communes et les exemples de catalogue. Dans un deuxième terminal, depuis la racine du projet :

```bash
cd frontend
npm ci
npm run dev
```

Ouvrir [Home Made en local](http://127.0.0.1:5173). Les détails du démarrage sous Windows sont dans [ENVIRONNEMENT.md](ENVIRONNEMENT.md).

## Vérifications

- Depuis `backend/`, exécuter `php artisan test`. Les tests utilisent SQLite en mémoire et couvrent notamment les comptes, le catalogue et la gestion des annonces.
- Depuis `frontend/`, exécuter `npm run lint` et `npm run build`.
- [GitHub Actions](https://github.com/youba-a/Home-made/actions) contient un premier workflow de vérification déclenché sur `main`. Les tests et la compilation sont actuellement lancés en local.

## Prochaines étapes

1. Mettre en place le service Laravel **Commandes**.
2. Développer l’achat simulé, les propositions de prix et les vérifications de stock.
3. Brancher **Mes achats** et **Mes ventes** sur les commandes réelles du projet.
4. Préparer la conteneurisation Docker et le déploiement.
