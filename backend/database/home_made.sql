-- Home Made : schéma MySQL du cahier des charges du 12 novembre 2026.
-- Catalogue : users, zones, communes, lieux_retrait, annonces, repas,
-- offres_repas et operations_disponibilite. Commandes : propositions_prix et commandes.
-- Chaque service écrit dans ses tables ; les références entre services passent par API.

-- 1. Préparer la connexion : UTF-8, dates UTC et validation stricte.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET SESSION time_zone = '+00:00';
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
SET SESSION foreign_key_checks = 1;

-- 2. Créer puis sélectionner la base du projet.
CREATE DATABASE IF NOT EXISTS home_made_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE home_made_db;

-- 3. Utilisateurs : un compte achète et vend ; password contient un hachage.
CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    email_verified_at TIMESTAMP NULL DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    CONSTRAINT chk_users_name CHECK (CHAR_LENGTH(TRIM(name)) > 0),
    CONSTRAINT chk_users_email CHECK (CHAR_LENGTH(TRIM(email)) > 0),
    CONSTRAINT chk_users_password CHECK (CHAR_LENGTH(password) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Zones géographiques de retrait.
CREATE TABLE zones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_zones_nom (nom),
    CONSTRAINT chk_zones_nom CHECK (CHAR_LENGTH(TRIM(nom)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Communes et codes postaux autorisés dans une zone.
CREATE TABLE communes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    zone_id BIGINT UNSIGNED NOT NULL,
    nom VARCHAR(120) NOT NULL,
    code_postal VARCHAR(10) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_communes_zone_nom_code (zone_id, nom, code_postal),
    UNIQUE KEY uq_communes_id_zone (id, zone_id),
    CONSTRAINT fk_communes_zone FOREIGN KEY (zone_id) REFERENCES zones (id),
    CONSTRAINT chk_communes_nom CHECK (CHAR_LENGTH(TRIM(nom)) > 0),
    CONSTRAINT chk_communes_code CHECK (CHAR_LENGTH(TRIM(code_postal)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Lieux : la commune choisie doit appartenir à la zone indiquée.
CREATE TABLE lieux_retrait (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    vendeur_id BIGINT UNSIGNED NOT NULL,
    zone_id BIGINT UNSIGNED NOT NULL,
    commune_id BIGINT UNSIGNED NOT NULL,
    type ENUM('DOMICILE', 'POINT') NOT NULL,
    adresse VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lieux_id_vendeur (id, vendeur_id),
    KEY idx_lieux_vendeur (vendeur_id),
    KEY idx_lieux_zone (zone_id),
    KEY idx_lieux_commune_zone (commune_id, zone_id),
    CONSTRAINT fk_lieux_vendeur FOREIGN KEY (vendeur_id) REFERENCES users (id),
    CONSTRAINT fk_lieux_zone FOREIGN KEY (zone_id) REFERENCES zones (id),
    CONSTRAINT fk_lieux_commune_zone FOREIGN KEY (commune_id, zone_id)
        REFERENCES communes (id, zone_id),
    CONSTRAINT chk_lieux_type CHECK (type IN ('DOMICILE', 'POINT')),
    CONSTRAINT chk_lieux_adresse CHECK (CHAR_LENGTH(TRIM(adresse)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Annonces : informations communes ; seuls les objets ont un état de vente.
CREATE TABLE annonces (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    vendeur_id BIGINT UNSIGNED NOT NULL,
    type ENUM('OBJET', 'REPAS') NOT NULL,
    titre VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    prix DECIMAL(10, 2) NOT NULL,
    image VARCHAR(2048) NOT NULL,
    categorie VARCHAR(100) NOT NULL,
    etat_objet ENUM('DISPONIBLE', 'VENDU') NULL DEFAULT NULL,
    lieu_retrait_id BIGINT UNSIGNED NULL,
    debut_retrait DATETIME NULL,
    fin_retrait DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_annonces_id_type_vendeur (id, type, vendeur_id),
    KEY idx_annonces_vendeur (vendeur_id),
    KEY idx_annonces_type_categorie (type, categorie),
    KEY idx_annonces_lieu_vendeur (lieu_retrait_id, vendeur_id),
    CONSTRAINT fk_annonces_lieu FOREIGN KEY (lieu_retrait_id, vendeur_id)
        REFERENCES lieux_retrait (id, vendeur_id),
    CONSTRAINT chk_annonces_retrait CHECK (
        (type = 'OBJET' AND lieu_retrait_id IS NOT NULL
            AND debut_retrait IS NOT NULL AND fin_retrait IS NOT NULL
            AND debut_retrait < fin_retrait)
        OR (type = 'REPAS' AND lieu_retrait_id IS NULL
            AND debut_retrait IS NULL AND fin_retrait IS NULL)
    ),
    CONSTRAINT fk_annonces_vendeur FOREIGN KEY (vendeur_id) REFERENCES users (id),
    CONSTRAINT chk_annonces_prix CHECK (prix > 0),
    CONSTRAINT chk_annonces_titre CHECK (CHAR_LENGTH(TRIM(titre)) > 0),
    CONSTRAINT chk_annonces_contenu CHECK (
        CHAR_LENGTH(TRIM(description)) > 0
        AND CHAR_LENGTH(TRIM(image)) > 0
        AND CHAR_LENGTH(TRIM(categorie)) > 0
    ),
    CONSTRAINT chk_annonces_etat CHECK (
        (type = 'OBJET' AND etat_objet IS NOT NULL AND etat_objet IN ('DISPONIBLE', 'VENDU'))
        OR (type = 'REPAS' AND etat_objet IS NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Repas : la relation composée impose une annonce REPAS du bon vendeur.
CREATE TABLE repas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    annonce_id BIGINT UNSIGNED NOT NULL,
    type_annonce ENUM('OBJET', 'REPAS') NOT NULL DEFAULT 'REPAS',
    vendeur_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_repas_annonce (annonce_id),
    UNIQUE KEY uq_repas_id_annonce_vendeur (id, annonce_id, vendeur_id),
    KEY idx_repas_annonce_type_vendeur (annonce_id, type_annonce, vendeur_id),
    CONSTRAINT fk_repas_annonce FOREIGN KEY (annonce_id, type_annonce, vendeur_id)
        REFERENCES annonces (id, type, vendeur_id),
    CONSTRAINT chk_repas_type CHECK (type_annonce = 'REPAS')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Offres : portions et horaires pour un repas et un lieu du même vendeur.
CREATE TABLE offres_repas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    repas_id BIGINT UNSIGNED NOT NULL,
    annonce_id BIGINT UNSIGNED NOT NULL,
    vendeur_id BIGINT UNSIGNED NOT NULL,
    lieu_retrait_id BIGINT UNSIGNED NOT NULL,
    portions_restantes INT NOT NULL,
    est_active BOOLEAN NOT NULL DEFAULT TRUE,
    repas_actif_id BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN est_active = TRUE THEN repas_id ELSE NULL END
    ) STORED,
    limite_achat DATETIME NOT NULL,
    debut_retrait DATETIME NOT NULL,
    fin_retrait DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_offres_repas_actif (repas_actif_id),
    UNIQUE KEY uq_offres_identite (id, annonce_id, vendeur_id, lieu_retrait_id),
    KEY idx_offres_repas_annonce_vendeur (repas_id, annonce_id, vendeur_id),
    KEY idx_offres_lieu_vendeur (lieu_retrait_id, vendeur_id),
    KEY idx_offres_lieu_date (lieu_retrait_id, debut_retrait),
    KEY idx_offres_date (debut_retrait),
    KEY idx_offres_limite (limite_achat),
    CONSTRAINT fk_offres_repas FOREIGN KEY (repas_id, annonce_id, vendeur_id)
        REFERENCES repas (id, annonce_id, vendeur_id),
    CONSTRAINT fk_offres_lieu FOREIGN KEY (lieu_retrait_id, vendeur_id)
        REFERENCES lieux_retrait (id, vendeur_id),
    CONSTRAINT chk_offres_active CHECK (est_active IN (FALSE, TRUE)),
    CONSTRAINT chk_offres_portions CHECK (portions_restantes >= 0),
    CONSTRAINT chk_offres_horaires CHECK (
        limite_achat < debut_retrait AND debut_retrait < fin_retrait
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Service Commandes : proposition de prix, sans réservation de l'objet.
CREATE TABLE propositions_prix (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    annonce_id BIGINT UNSIGNED NOT NULL,
    acheteur_id BIGINT UNSIGNED NOT NULL,
    vendeur_id BIGINT UNSIGNED NOT NULL,
    montant DECIMAL(10, 2) NOT NULL,
    statut ENUM('EN_ATTENTE', 'ACCEPTEE', 'REFUSEE', 'CLOTUREE') NOT NULL DEFAULT 'EN_ATTENTE',
    decision_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_propositions_acheteur (acheteur_id, created_at),
    KEY idx_propositions_vendeur (vendeur_id, statut),
    KEY idx_propositions_annonce (annonce_id, statut),
    CONSTRAINT chk_propositions_montant CHECK (montant > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Service Commandes : achat direct ou au prix d'une proposition acceptée.
-- Références Catalogue, identité et prix contrôlés par les futures API.
CREATE TABLE commandes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cle_achat CHAR(36) NOT NULL,
    acheteur_id BIGINT UNSIGNED NOT NULL,
    vendeur_id BIGINT UNSIGNED NOT NULL,
    annonce_id BIGINT UNSIGNED NOT NULL,
    type_annonce ENUM('OBJET', 'REPAS') NOT NULL,
    proposition_prix_id BIGINT UNSIGNED NULL,
    offre_repas_id BIGINT UNSIGNED NULL,
    lieu_retrait_id BIGINT UNSIGNED NOT NULL,
    quantite INT NOT NULL,
    prix_unitaire DECIMAL(10, 2) NOT NULL,
    montant_total DECIMAL(20, 2) GENERATED ALWAYS AS (prix_unitaire * quantite) STORED,
    statut ENUM('EN_COURS', 'CONFIRMEE', 'ECHOUEE') NOT NULL DEFAULT 'EN_COURS',
    instantane_titre VARCHAR(255) NOT NULL,
    instantane_zone VARCHAR(120) NOT NULL,
    instantane_commune VARCHAR(120) NOT NULL,
    instantane_code_postal VARCHAR(10) NOT NULL,
    instantane_adresse VARCHAR(255) NOT NULL,
    limite_achat DATETIME NULL,
    debut_retrait DATETIME NOT NULL,
    fin_retrait DATETIME NOT NULL,
    parametres_achat JSON NOT NULL,
    derniere_tentative_at DATETIME NULL,
    erreur VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_commandes_cle (cle_achat),
    UNIQUE KEY uq_commandes_proposition (proposition_prix_id),
    KEY idx_commandes_acheteur_date (acheteur_id, created_at),
    KEY idx_commandes_vendeur_statut (vendeur_id, statut),
    KEY idx_commandes_annonce (annonce_id),
    KEY idx_commandes_offre (offre_repas_id),
    CONSTRAINT fk_commandes_proposition FOREIGN KEY (proposition_prix_id) REFERENCES propositions_prix (id),
    CONSTRAINT chk_commandes_quantite CHECK (quantite > 0),
    CONSTRAINT chk_commandes_prix CHECK (prix_unitaire > 0),
    CONSTRAINT chk_commandes_retrait CHECK (debut_retrait < fin_retrait),
    CONSTRAINT chk_commandes_type CHECK (
        (type_annonce = 'OBJET' AND quantite = 1 AND offre_repas_id IS NULL AND limite_achat IS NULL)
        OR (type_annonce = 'REPAS' AND offre_repas_id IS NOT NULL
            AND proposition_prix_id IS NULL AND limite_achat IS NOT NULL
            AND limite_achat < debut_retrait)
    ),
    CONSTRAINT chk_commandes_parametres CHECK (JSON_TYPE(parametres_achat) = 'OBJECT')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Service Catalogue : journal des achats réussis.
-- commande_id vient de l'API Commandes ; UNIQUE empêche deux succès pour cette commande.
CREATE TABLE operations_disponibilite (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    commande_id BIGINT UNSIGNED NOT NULL,
    parametres JSON NOT NULL,
    resultat JSON NOT NULL,
    executee_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_operations_commande (commande_id),
    CONSTRAINT chk_operations_parametres CHECK (JSON_TYPE(parametres) = 'OBJECT'),
    CONSTRAINT chk_operations_resultat CHECK (JSON_TYPE(resultat) = 'OBJECT')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Vérifier les tables créées ; les données seront ajoutées ensuite.
-- Laravel gérera les droits, les délais et les achats avec verrouillage.
SHOW TABLES;

