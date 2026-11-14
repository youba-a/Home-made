# Home Made — interface React

Depuis la racine du projet, après démarrage de MySQL dans Wamp :

```powershell
node outils/demarrer.mjs
```

Ouvrir http://127.0.0.1:5173/. Le guide complet se trouve dans [ENVIRONNEMENT.md](../ENVIRONNEMENT.md).

- `src/App.jsx` : page, chargement, filtres et pagination.
- `src/api/annonces.js` : liste, détail et zones reçus de Laravel.
- `src/api/auth.js` : inscription, connexion et session.
- `src/components/ProductCard.jsx` : carte produit.
- `src/components/ProductDialog.jsx` : fiche avec retrait, vendeur et offre du repas.
- `src/components/AccountDialog.jsx` : formulaires et compte.
- `src/App.css` : présentation et adaptation aux petits écrans.
- `src/index.css` : styles communs.

Les produits viennent de MySQL via Laravel. Le type, la zone et la recherche portent sur tout le catalogue avant la pagination de 12 résultats. Un changement de filtre revient à la page 1. Les repas ont une seule offre publiée ; les adresses exactes ne sont pas exposées dans les fiches.

Les fonctions d’achat et de publication restent à développer.

Depuis ce dossier :

```powershell
npm.cmd run dev
npm.cmd run lint
npm.cmd run format:check
npm.cmd run build
```

`Ctrl+C` arrête le serveur du terminal. Les sources sont formatées avec deux espaces ; `npm.cmd run format` applique Prettier. `dist/` contient uniquement la compilation générée.

Le proxy Vite transmet `/api` à Laravel sur le port 8000. Il concerne le développement ; le futur serveur déployé devra gérer cette redirection.
