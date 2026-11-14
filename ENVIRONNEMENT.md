# Home Made : lancer et comprendre le projet

## Démarrer sur ce PC

1. Démarrer MySQL dans WampServer (port 3306).
2. Ouvrir un terminal dans le dossier `home made`.
3. Exécuter :

```powershell
node outils/demarrer.mjs
```

4. Ouvrir http://127.0.0.1:5173/ et garder le terminal ouvert.
5. `Ctrl+C` arrête les serveurs lancés par cette commande. Un serveur déjà ouvert dans un autre terminal doit être arrêté dans son propre terminal.

Ce script utilise les outils déjà préparés sur ce PC : PHP 8.3.6 de Wamp, Node.js, MySQL et la configuration `.local/php`. Il ne réinstalle rien. Il conserve les serveurs qui utilisent déjà les ports 8000 ou 5173.

Le site React utilise le port 5173 ; Laravel fournit l’API et les photos sur le port 8000. Wamp fournit MySQL ; Apache n’est pas nécessaire pour ces deux serveurs de développement.

Pour démarrer séparément :

```powershell
# Terminal 1, depuis la racine
. .\outils\activer.ps1
cd backend
php artisan serve --host=127.0.0.1 --port=8000
```

```powershell
# Terminal 2, depuis la racine
cd frontend
npm.cmd run dev
```

## Organisation actuelle

- `frontend/` : interface React, cartes, fiches et compte.
- `backend/` : premier service Laravel, Catalogue et comptes.
- `outils/` : activation de PHP et démarrage local.
- `.local/php/` : configuration PHP locale à conserver.

Une seule base MySQL est utilisée : `home_made_db`. Le cahier prévoit deux services métier Laravel, Catalogue et Commandes. Le service Commandes n’est pas encore créé ; React est leur interface, pas le deuxième service métier.

## Base de données

La connexion est définie dans `backend/.env` :

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=home_made_db
DB_USERNAME=root
DB_PASSWORD=
```

`backend/config/database.php` lit ces valeurs. Les dates sont enregistrées en UTC et affichées en heure belge.

- `backend/database/home_made.sql` : structure complète pour une base vide, utilisable dans phpMyAdmin. Ne pas le réimporter dans la base existante.
- `backend/database/migrations/2026_11_12_143135_prepare_home_made_database.php` : installation des tables manquantes et adaptation de l’ancienne structure. Déjà appliquée localement ; elle conserve les produits.
- `backend/database/donnees_demo.sql` : exemples avec photos ; réexécutable sans dupliquer les annonces.
- `backend/database/seeders/DatabaseSeeder.php` : permet de charger ces mêmes exemples avec `php artisan db:seed`.

Les dix tables métier sont réparties ainsi :

| Responsable prévu | Tables |
| --- | --- |
| Catalogue | `users`, `zones`, `communes`, `lieux_retrait`, `annonces`, `repas`, `offres_repas`, `operations_disponibilite` |
| Commandes | `propositions_prix`, `commandes` |

La table technique `migrations` mémorise les migrations exécutées. Aucun lien SQL entre les deux services métier : leurs futures API devront vérifier ces références.

Pour une installation vide, après avoir créé la base et renseigné `.env`, depuis `backend` avec l’environnement activé :

```powershell
php artisan migrate
php artisan db:seed
php artisan storage:link
```

Pour l’installation actuelle, tout cela est déjà préparé. Ne pas utiliser `migrate:fresh` : cette commande supprime les tables. La migration d’adaptation ne propose pas de retour arrière destructeur.

Les exemples contiennent 18 annonces, dont 10 objets et 8 repas, trois vendeurs fictifs et trois zones. Une seule offre est publiée par repas ; deux anciennes offres restent archivées. Chaque objet possède son lieu et son créneau de retrait. `limite_achat` remplace l’ancienne limite de validation des repas.

## Photos

Les fichiers se trouvent dans `backend/storage/app/public/produits/`. Le champ `annonces.image` conserve leur chemin, par exemple `/storage/produits/demo-bougie.jpg`. Le lien `backend/public/storage` les rend accessibles ; il pointe vers les mêmes fichiers et ne les duplique pas.

Le formulaire de publication et l’envoi de nouvelles photos restent à développer.

## Parcours d’un produit à l’écran

1. `frontend/src/App.jsx` charge la liste et mémorise les filtres.
2. `frontend/src/api/annonces.js` envoie une demande avec `fetch`.
3. `backend/routes/api.php` associe l’adresse au contrôleur.
4. `backend/app/Http/Controllers/Api/V1/AnnonceController.php` utilise le modèle `Annonce` pour lire MySQL.
5. Les ressources de `backend/app/Http/Resources/` choisissent les données publiques à renvoyer.
6. `ProductCard.jsx` affiche les cartes ; `ProductDialog.jsx` demande le détail réel à l’API.

Adresses de lecture :

- `/api/v1/annonces` : liste, 12 annonces par page.
- `/api/v1/annonces?type=REPAS&q=tarte&zone_id=1` : filtres appliqués avant la pagination.
- `/api/v1/annonces/3` : détail d’une annonce.
- `/api/v1/zones` : zones disponibles.

L’adresse exacte de retrait et les données privées du vendeur sont exclues de l’API publique, même pour un visiteur connecté.

## Comptes

Le bouton « Se connecter » ouvre `frontend/src/components/AccountDialog.jsx` : inscription, connexion, consultation du compte et déconnexion. `frontend/src/api/auth.js` communique avec `AuthController.php` via `/api/v1/auth/`.

Laravel conserve une session dans un cookie, hache les mots de passe et vérifie les données. Le formulaire récupère un jeton CSRF avant chaque envoi. Un seul compte servira pour acheter et vendre. Les trois vendeurs de démonstration utilisent initialement le mot de passe `DemoHomeMade2026!` : `vendeur.demo@example.test`, `atelier.demo@example.test` et `cuisine.demo@example.test`. MySQL enregistre son hash. Un nouvel import des exemples ne remplace pas le mot de passe d’un compte déjà présent.

## Vérifier et modifier

```powershell
# Dans frontend
npm.cmd run lint
npm.cmd run format:check
npm.cmd run build
```

```powershell
# Dans backend, après activation de l’environnement
php artisan test --compact
```

Les tests automatisés utilisent SQLite en mémoire, sans modifier MySQL. Les sources lisibles sont dans `frontend/src/` : `dist/` est une compilation générée. Le PHP utilise quatre espaces, le frontend deux espaces.

## Suite du travail

L’achat, les propositions de prix, la publication des annonces et les commandes restent à développer. Le schéma les prépare ; les boutons et API métier correspondants ne sont pas encore présents. Ensuite : deuxième application Laravel, échanges entre services, vérifications de stock, Docker, GitHub et déploiement selon les exigences du professeur.
