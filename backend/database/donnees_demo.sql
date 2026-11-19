-- Home Made : 18 annonces fictives, 3 vendeurs, 3 zones et 8 offres actives de repas.
-- À exécuter après home_made.sql ; les lignes existantes sont conservées.
-- Images : visuels de démonstration générés par IA, dans storage/app/public/produits.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET SESSION time_zone = '+00:00';
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
USE home_made_db;

START TRANSACTION;

-- Fermer les anciennes offres fictives avant de préparer les prochaines dates.
UPDATE offres_repas o
JOIN annonces a ON a.id = o.annonce_id
JOIN users u ON u.id = a.vendeur_id
SET o.est_active = FALSE
WHERE o.limite_achat <= UTC_TIMESTAMP()
    AND u.email IN ('vendeur.demo@example.test', 'atelier.demo@example.test', 'cuisine.demo@example.test');

-- 1. Vendeur fictif ; mot de passe initial : DemoHomeMade2026! (haché ci-dessous).
INSERT INTO users (first_name, last_name, email, password)
SELECT 'Vendeur', 'démo', 'vendeur.demo@example.test', '$2y$12$jbFQf4Wd9mvK6fNQt7psTeepXkJdEgvEQ0MW5Ic1gFt91VQk1Kr/G'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'vendeur.demo@example.test');

SET @demo_vendeur = (SELECT id FROM users WHERE email = 'vendeur.demo@example.test');

-- 2. Zone, commune et adresse fictive du retrait.
INSERT INTO zones (nom)
SELECT 'Namur' WHERE NOT EXISTS (SELECT 1 FROM zones WHERE nom = 'Namur');
SET @demo_zone = (SELECT id FROM zones WHERE nom = 'Namur');

INSERT INTO communes (zone_id, nom, code_postal)
SELECT @demo_zone, 'Namur', '5000'
WHERE NOT EXISTS (
    SELECT 1 FROM communes WHERE zone_id = @demo_zone AND nom = 'Namur' AND code_postal = '5000'
);
SET @demo_commune = (
    SELECT id FROM communes WHERE zone_id = @demo_zone AND nom = 'Namur' AND code_postal = '5000'
);

INSERT INTO lieux_retrait (vendeur_id, zone_id, commune_id, type, adresse)
SELECT @demo_vendeur, @demo_zone, @demo_commune, 'POINT', 'Point de démonstration fictif'
WHERE NOT EXISTS (
    SELECT 1 FROM lieux_retrait
    WHERE vendeur_id = @demo_vendeur AND zone_id = @demo_zone AND commune_id = @demo_commune
        AND type = 'POINT' AND adresse = 'Point de démonstration fictif'
);
SET @demo_lieu = (
    SELECT MIN(id) FROM lieux_retrait
    WHERE vendeur_id = @demo_vendeur AND zone_id = @demo_zone AND commune_id = @demo_commune
        AND type = 'POINT' AND adresse = 'Point de démonstration fictif'
);

-- 3. Deux objets uniques, disponibles à la vente.
INSERT INTO annonces (vendeur_id, type, titre, description, prix, image, categorie, etat_objet, lieu_retrait_id, debut_retrait, fin_retrait)
SELECT @demo_vendeur, 'OBJET', 'Bougie artisanale', 'Bougie faite main, annonce de démonstration.',
    12.00, '/storage/produits/demo-bougie.jpg', 'Décoration', 'DISPONIBLE', @demo_lieu,
    TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '17:00:00'),
    TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '18:00:00')
WHERE NOT EXISTS (
    SELECT 1 FROM annonces WHERE vendeur_id = @demo_vendeur AND type = 'OBJET' AND titre = 'Bougie artisanale'
);

INSERT INTO annonces (vendeur_id, type, titre, description, prix, image, categorie, etat_objet, lieu_retrait_id, debut_retrait, fin_retrait)
SELECT @demo_vendeur, 'OBJET', 'Sac cousu main', 'Sac en tissu, annonce de démonstration.',
    18.00, '/storage/produits/demo-sac.jpg', 'Accessoires', 'DISPONIBLE', @demo_lieu,
    TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '17:00:00'),
    TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '18:00:00')
WHERE NOT EXISTS (
    SELECT 1 FROM annonces WHERE vendeur_id = @demo_vendeur AND type = 'OBJET' AND titre = 'Sac cousu main'
);

-- 4. Deux plats : les prix sont dans annonces, les portions dans offres_repas.
INSERT INTO annonces (vendeur_id, type, titre, description, prix, image, categorie, etat_objet)
SELECT @demo_vendeur, 'REPAS', 'Couscous maison', 'Couscous aux légumes, plat de démonstration.',
    8.50, '/storage/produits/demo-couscous.jpg', 'Plats préparés', NULL
WHERE NOT EXISTS (
    SELECT 1 FROM annonces WHERE vendeur_id = @demo_vendeur AND type = 'REPAS' AND titre = 'Couscous maison'
);

INSERT INTO annonces (vendeur_id, type, titre, description, prix, image, categorie, etat_objet)
SELECT @demo_vendeur, 'REPAS', 'Lasagnes maison', 'Lasagnes aux légumes, plat de démonstration.',
    9.50, '/storage/produits/demo-lasagnes.jpg', 'Plats préparés', NULL
WHERE NOT EXISTS (
    SELECT 1 FROM annonces WHERE vendeur_id = @demo_vendeur AND type = 'REPAS' AND titre = 'Lasagnes maison'
);

SET @demo_couscous_annonce = (
    SELECT MIN(id) FROM annonces WHERE vendeur_id = @demo_vendeur AND type = 'REPAS' AND titre = 'Couscous maison'
);
SET @demo_lasagnes_annonce = (
    SELECT MIN(id) FROM annonces WHERE vendeur_id = @demo_vendeur AND type = 'REPAS' AND titre = 'Lasagnes maison'
);

INSERT INTO repas (annonce_id, vendeur_id)
SELECT @demo_couscous_annonce, @demo_vendeur
WHERE NOT EXISTS (SELECT 1 FROM repas WHERE annonce_id = @demo_couscous_annonce);

INSERT INTO repas (annonce_id, vendeur_id)
SELECT @demo_lasagnes_annonce, @demo_vendeur
WHERE NOT EXISTS (SELECT 1 FROM repas WHERE annonce_id = @demo_lasagnes_annonce);

SET @demo_couscous_repas = (SELECT id FROM repas WHERE annonce_id = @demo_couscous_annonce);
SET @demo_lasagnes_repas = (SELECT id FROM repas WHERE annonce_id = @demo_lasagnes_annonce);

-- 5. Retrait après-demain, de 17 h à 18 h UTC ; validation avant 15 h UTC.
SET @demo_debut = TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '17:00:00');
SET @demo_fin = DATE_ADD(@demo_debut, INTERVAL 1 HOUR);
SET @demo_limite = DATE_SUB(@demo_debut, INTERVAL 2 HOUR);

INSERT INTO offres_repas
    (repas_id, annonce_id, vendeur_id, lieu_retrait_id, portions_restantes, limite_achat, debut_retrait, fin_retrait)
SELECT @demo_couscous_repas, @demo_couscous_annonce, @demo_vendeur, @demo_lieu, 10,
    @demo_limite, @demo_debut, @demo_fin
WHERE NOT EXISTS (
    SELECT 1 FROM offres_repas
    WHERE repas_id = @demo_couscous_repas AND est_active = TRUE
);

INSERT INTO offres_repas
    (repas_id, annonce_id, vendeur_id, lieu_retrait_id, portions_restantes, limite_achat, debut_retrait, fin_retrait)
SELECT @demo_lasagnes_repas, @demo_lasagnes_annonce, @demo_vendeur, @demo_lieu, 5,
    @demo_limite, @demo_debut, @demo_fin
WHERE NOT EXISTS (
    SELECT 1 FROM offres_repas
    WHERE repas_id = @demo_lasagnes_repas AND est_active = TRUE
);

-- 6. Deux vendeurs fictifs avec le même mot de passe initial : DemoHomeMade2026!
INSERT INTO users (first_name, last_name, email, password)
SELECT 'Atelier', 'démo Bruxelles', 'atelier.demo@example.test', password
FROM users
WHERE id = @demo_vendeur
    AND NOT EXISTS (SELECT 1 FROM users WHERE email = 'atelier.demo@example.test');

INSERT INTO users (first_name, last_name, email, password)
SELECT 'Cuisine', 'démo Liège', 'cuisine.demo@example.test', password
FROM users
WHERE id = @demo_vendeur
    AND NOT EXISTS (SELECT 1 FROM users WHERE email = 'cuisine.demo@example.test');

SET @demo_atelier = (SELECT id FROM users WHERE email = 'atelier.demo@example.test');
SET @demo_cuisine = (SELECT id FROM users WHERE email = 'cuisine.demo@example.test');

-- 7. Deux zones et communes supplémentaires.
INSERT INTO zones (nom)
SELECT 'Bruxelles' WHERE NOT EXISTS (SELECT 1 FROM zones WHERE nom = 'Bruxelles');

INSERT INTO zones (nom)
SELECT 'Liège' WHERE NOT EXISTS (SELECT 1 FROM zones WHERE nom = 'Liège');

SET @demo_zone_bruxelles = (SELECT id FROM zones WHERE nom = 'Bruxelles');
SET @demo_zone_liege = (SELECT id FROM zones WHERE nom = 'Liège');

INSERT INTO communes (zone_id, nom, code_postal)
SELECT @demo_zone_bruxelles, 'Bruxelles', '1000'
WHERE NOT EXISTS (
    SELECT 1 FROM communes
    WHERE zone_id = @demo_zone_bruxelles AND nom = 'Bruxelles' AND code_postal = '1000'
);

INSERT INTO communes (zone_id, nom, code_postal)
SELECT @demo_zone_liege, 'Liège', '4000'
WHERE NOT EXISTS (
    SELECT 1 FROM communes
    WHERE zone_id = @demo_zone_liege AND nom = 'Liège' AND code_postal = '4000'
);

SET @demo_commune_bruxelles = (
    SELECT id FROM communes
    WHERE zone_id = @demo_zone_bruxelles AND nom = 'Bruxelles' AND code_postal = '1000'
);
SET @demo_commune_liege = (
    SELECT id FROM communes
    WHERE zone_id = @demo_zone_liege AND nom = 'Liège' AND code_postal = '4000'
);

-- 8. Trois lieux fictifs : un vendeur peut avoir plusieurs lieux.
INSERT INTO lieux_retrait (vendeur_id, zone_id, commune_id, type, adresse)
SELECT @demo_vendeur, @demo_zone, @demo_commune, 'DOMICILE', 'Domicile fictif démo Namur'
WHERE NOT EXISTS (
    SELECT 1 FROM lieux_retrait
    WHERE vendeur_id = @demo_vendeur AND zone_id = @demo_zone AND commune_id = @demo_commune
        AND type = 'DOMICILE' AND adresse = 'Domicile fictif démo Namur'
);

INSERT INTO lieux_retrait (vendeur_id, zone_id, commune_id, type, adresse)
SELECT @demo_atelier, @demo_zone_bruxelles, @demo_commune_bruxelles,
    'POINT', 'Point fictif démo Bruxelles'
WHERE NOT EXISTS (
    SELECT 1 FROM lieux_retrait
    WHERE vendeur_id = @demo_atelier AND zone_id = @demo_zone_bruxelles
        AND commune_id = @demo_commune_bruxelles
        AND type = 'POINT' AND adresse = 'Point fictif démo Bruxelles'
);

INSERT INTO lieux_retrait (vendeur_id, zone_id, commune_id, type, adresse)
SELECT @demo_cuisine, @demo_zone_liege, @demo_commune_liege,
    'DOMICILE', 'Domicile fictif démo Liège'
WHERE NOT EXISTS (
    SELECT 1 FROM lieux_retrait
    WHERE vendeur_id = @demo_cuisine AND zone_id = @demo_zone_liege
        AND commune_id = @demo_commune_liege
        AND type = 'DOMICILE' AND adresse = 'Domicile fictif démo Liège'
);

SET @demo_domicile_namur = (
    SELECT MIN(id) FROM lieux_retrait
    WHERE vendeur_id = @demo_vendeur AND zone_id = @demo_zone AND commune_id = @demo_commune
        AND type = 'DOMICILE' AND adresse = 'Domicile fictif démo Namur'
);
SET @demo_lieu_bruxelles = (
    SELECT MIN(id) FROM lieux_retrait
    WHERE vendeur_id = @demo_atelier AND zone_id = @demo_zone_bruxelles
        AND commune_id = @demo_commune_bruxelles
        AND type = 'POINT' AND adresse = 'Point fictif démo Bruxelles'
);
SET @demo_lieu_liege = (
    SELECT MIN(id) FROM lieux_retrait
    WHERE vendeur_id = @demo_cuisine AND zone_id = @demo_zone_liege
        AND commune_id = @demo_commune_liege
        AND type = 'DOMICILE' AND adresse = 'Domicile fictif démo Liège'
);

-- 9. Huit autres objets uniques, tous disponibles.
INSERT INTO annonces (vendeur_id, type, titre, description, prix, image, categorie, etat_objet, lieu_retrait_id, debut_retrait, fin_retrait)
SELECT @demo_vendeur, 'OBJET', 'Vase en céramique',
    'Vase façonné à la main, annonce de démonstration.',
    22.00, '/storage/produits/demo-vase.jpg', 'Décoration', 'DISPONIBLE', @demo_lieu,
    TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '17:00:00'),
    TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '18:00:00')
WHERE NOT EXISTS (
    SELECT 1 FROM annonces
    WHERE vendeur_id = @demo_vendeur AND type = 'OBJET' AND titre = 'Vase en céramique'
);

INSERT INTO annonces (vendeur_id, type, titre, description, prix, image, categorie, etat_objet, lieu_retrait_id, debut_retrait, fin_retrait)
SELECT @demo_vendeur, 'OBJET', 'Bracelet de perles',
    'Bracelet assemblé à la main, annonce de démonstration.',
    9.00, '/storage/produits/demo-bracelet.jpg', 'Bijoux', 'DISPONIBLE', @demo_lieu,
    TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '17:00:00'),
    TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '18:00:00')
WHERE NOT EXISTS (
    SELECT 1 FROM annonces
    WHERE vendeur_id = @demo_vendeur AND type = 'OBJET' AND titre = 'Bracelet de perles'
);

INSERT INTO annonces (vendeur_id, type, titre, description, prix, image, categorie, etat_objet, lieu_retrait_id, debut_retrait, fin_retrait)
SELECT @demo_atelier, 'OBJET', 'Carnet relié main',
    'Carnet à couverture en tissu, annonce de démonstration.',
    14.00, '/storage/produits/demo-carnet.jpg', 'Papeterie', 'DISPONIBLE', @demo_lieu_bruxelles,
    TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '17:00:00'),
    TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '18:00:00')
WHERE NOT EXISTS (
    SELECT 1 FROM annonces
    WHERE vendeur_id = @demo_atelier AND type = 'OBJET' AND titre = 'Carnet relié main'
);

INSERT INTO annonces (vendeur_id, type, titre, description, prix, image, categorie, etat_objet, lieu_retrait_id, debut_retrait, fin_retrait)
SELECT @demo_atelier, 'OBJET', 'Porte-clés en macramé',
    'Porte-clés noué à la main, annonce de démonstration.',
    6.50, '/storage/produits/demo-porte-cles.jpg', 'Accessoires', 'DISPONIBLE', @demo_lieu_bruxelles,
    TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '17:00:00'),
    TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '18:00:00')
WHERE NOT EXISTS (
    SELECT 1 FROM annonces
    WHERE vendeur_id = @demo_atelier AND type = 'OBJET' AND titre = 'Porte-clés en macramé'
);

INSERT INTO annonces (vendeur_id, type, titre, description, prix, image, categorie, etat_objet, lieu_retrait_id, debut_retrait, fin_retrait)
SELECT @demo_atelier, 'OBJET', 'Housse de coussin',
    'Housse cousue dans un tissu imprimé, annonce de démonstration.',
    16.00, '/storage/produits/demo-coussin.jpg', 'Décoration', 'DISPONIBLE', @demo_lieu_bruxelles,
    TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '17:00:00'),
    TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '18:00:00')
WHERE NOT EXISTS (
    SELECT 1 FROM annonces
    WHERE vendeur_id = @demo_atelier AND type = 'OBJET' AND titre = 'Housse de coussin'
);

INSERT INTO annonces (vendeur_id, type, titre, description, prix, image, categorie, etat_objet, lieu_retrait_id, debut_retrait, fin_retrait)
SELECT @demo_atelier, 'OBJET', 'Panier tressé',
    'Petit panier de rangement fait main, annonce de démonstration.',
    24.00, '/storage/produits/demo-panier.jpg', 'Décoration', 'DISPONIBLE', @demo_lieu_bruxelles,
    TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '17:00:00'),
    TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '18:00:00')
WHERE NOT EXISTS (
    SELECT 1 FROM annonces
    WHERE vendeur_id = @demo_atelier AND type = 'OBJET' AND titre = 'Panier tressé'
);

INSERT INTO annonces (vendeur_id, type, titre, description, prix, image, categorie, etat_objet, lieu_retrait_id, debut_retrait, fin_retrait)
SELECT @demo_cuisine, 'OBJET', 'Dessous de verre en liège',
    'Pièce décorée à la main, annonce de démonstration.',
    10.00, '/storage/produits/demo-dessous-verre.jpg', 'Maison', 'DISPONIBLE', @demo_lieu_liege,
    TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '17:00:00'),
    TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '18:00:00')
WHERE NOT EXISTS (
    SELECT 1 FROM annonces
    WHERE vendeur_id = @demo_cuisine AND type = 'OBJET' AND titre = 'Dessous de verre en liège'
);

INSERT INTO annonces (vendeur_id, type, titre, description, prix, image, categorie, etat_objet, lieu_retrait_id, debut_retrait, fin_retrait)
SELECT @demo_cuisine, 'OBJET', 'Savon artisanal',
    'Savon décoratif fait main, annonce de démonstration.',
    4.50, '/storage/produits/demo-savon.jpg', 'Créations artisanales', 'DISPONIBLE', @demo_lieu_liege,
    TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '17:00:00'),
    TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '18:00:00')
WHERE NOT EXISTS (
    SELECT 1 FROM annonces
    WHERE vendeur_id = @demo_cuisine AND type = 'OBJET' AND titre = 'Savon artisanal'
);

-- 10. Six autres plats ; le prix correspond à une portion.
INSERT INTO annonces (vendeur_id, type, titre, description, prix, image, categorie, etat_objet)
SELECT @demo_vendeur, 'REPAS', 'Tarte aux légumes',
    'Une portion de tarte salée maison, plat de démonstration.',
    7.50, '/storage/produits/demo-tarte-legumes.jpg', 'Plats préparés', NULL
WHERE NOT EXISTS (
    SELECT 1 FROM annonces
    WHERE vendeur_id = @demo_vendeur AND type = 'REPAS' AND titre = 'Tarte aux légumes'
);

INSERT INTO annonces (vendeur_id, type, titre, description, prix, image, categorie, etat_objet)
SELECT @demo_atelier, 'REPAS', 'Soupe au potiron',
    'Une portion de soupe maison, plat de démonstration.',
    5.50, '/storage/produits/demo-soupe-potiron.jpg', 'Soupes', NULL
WHERE NOT EXISTS (
    SELECT 1 FROM annonces
    WHERE vendeur_id = @demo_atelier AND type = 'REPAS' AND titre = 'Soupe au potiron'
);

INSERT INTO annonces (vendeur_id, type, titre, description, prix, image, categorie, etat_objet)
SELECT @demo_atelier, 'REPAS', 'Quiche aux épinards',
    'Une portion de quiche maison, plat de démonstration.',
    7.00, '/storage/produits/demo-quiche-epinards.jpg', 'Plats préparés', NULL
WHERE NOT EXISTS (
    SELECT 1 FROM annonces
    WHERE vendeur_id = @demo_atelier AND type = 'REPAS' AND titre = 'Quiche aux épinards'
);

INSERT INTO annonces (vendeur_id, type, titre, description, prix, image, categorie, etat_objet)
SELECT @demo_cuisine, 'REPAS', 'Tajine de légumes',
    'Une portion de tajine maison, plat de démonstration.',
    10.00, '/storage/produits/demo-tajine.jpg', 'Plats préparés', NULL
WHERE NOT EXISTS (
    SELECT 1 FROM annonces
    WHERE vendeur_id = @demo_cuisine AND type = 'REPAS' AND titre = 'Tajine de légumes'
);

INSERT INTO annonces (vendeur_id, type, titre, description, prix, image, categorie, etat_objet)
SELECT @demo_cuisine, 'REPAS', 'Curry de pois chiches',
    'Une portion de curry et de riz, plat de démonstration.',
    8.00, '/storage/produits/demo-curry.jpg', 'Plats préparés', NULL
WHERE NOT EXISTS (
    SELECT 1 FROM annonces
    WHERE vendeur_id = @demo_cuisine AND type = 'REPAS' AND titre = 'Curry de pois chiches'
);

INSERT INTO annonces (vendeur_id, type, titre, description, prix, image, categorie, etat_objet)
SELECT @demo_cuisine, 'REPAS', 'Brownie au chocolat',
    'Une portion de brownie maison, dessert de démonstration.',
    3.50, '/storage/produits/demo-brownie.jpg', 'Desserts', NULL
WHERE NOT EXISTS (
    SELECT 1 FROM annonces
    WHERE vendeur_id = @demo_cuisine AND type = 'REPAS' AND titre = 'Brownie au chocolat'
);

-- 11. Créer les fiches réutilisables des six nouveaux plats.
INSERT INTO repas (annonce_id, vendeur_id)
SELECT annonces.id, annonces.vendeur_id
FROM annonces
WHERE annonces.type = 'REPAS'
    AND annonces.vendeur_id IN (@demo_vendeur, @demo_atelier, @demo_cuisine)
    AND annonces.titre IN (
        'Tarte aux légumes', 'Soupe au potiron', 'Quiche aux épinards',
        'Tajine de légumes', 'Curry de pois chiches', 'Brownie au chocolat'
    )
    AND NOT EXISTS (SELECT 1 FROM repas WHERE repas.annonce_id = annonces.id);

-- 12. Une offre pour chaque nouveau plat, dans le lieu de son vendeur.
INSERT INTO offres_repas (
    repas_id, annonce_id, vendeur_id, lieu_retrait_id,
    portions_restantes, limite_achat, debut_retrait, fin_retrait
)
SELECT
    repas.id, annonces.id, annonces.vendeur_id, lieux_retrait.id,
    CASE annonces.titre
        WHEN 'Tarte aux légumes' THEN 8
        WHEN 'Soupe au potiron' THEN 12
        WHEN 'Quiche aux épinards' THEN 6
        WHEN 'Tajine de légumes' THEN 9
        WHEN 'Curry de pois chiches' THEN 7
        WHEN 'Brownie au chocolat' THEN 15
    END,
    @demo_limite, @demo_debut, @demo_fin
FROM repas
JOIN annonces ON annonces.id = repas.annonce_id
JOIN lieux_retrait ON lieux_retrait.vendeur_id = annonces.vendeur_id
WHERE lieux_retrait.id IN (@demo_lieu, @demo_lieu_bruxelles, @demo_lieu_liege)
    AND annonces.titre IN (
        'Tarte aux légumes', 'Soupe au potiron', 'Quiche aux épinards',
        'Tajine de légumes', 'Curry de pois chiches', 'Brownie au chocolat'
    )
    AND NOT EXISTS (
        SELECT 1 FROM offres_repas
        WHERE offres_repas.repas_id = repas.id
            AND offres_repas.est_active = TRUE
    );

COMMIT;

-- 13. Vérifier les dix objets et les huit repas des trois vendeurs fictifs.
SELECT id, type, titre, prix, categorie, etat_objet
FROM annonces
WHERE vendeur_id IN (@demo_vendeur, @demo_atelier, @demo_cuisine)
ORDER BY id;
