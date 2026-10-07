-- =====================================================================
-- generateur_cv.sql : base de données (Question 2)
-- À importer une seule fois dans phpMyAdmin (onglet « Importer »)
-- ou avec : mysql -u root < generateur_cv.sql
-- =====================================================================

DROP DATABASE IF EXISTS generateur_cv;
CREATE DATABASE generateur_cv CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE generateur_cv;

-- ---------------------------------------------------------------------
-- Utilisateur : identifié par son adresse email (clé primaire).
-- L'email doit être validé (email_valide = 1) avant de pouvoir se connecter.
-- Les champs du CV sont NULL tant que l'utilisateur ne les a pas remplis.
-- role : 'candidat' (Partie 2) ou 'entreprise' (partie facultative).
-- ---------------------------------------------------------------------
CREATE TABLE utilisateur (
    email            VARCHAR(100) NOT NULL,
    mot_de_passe     VARCHAR(255) NOT NULL,          -- haché avec password_hash()
    role             ENUM('candidat', 'entreprise') NOT NULL DEFAULT 'candidat',
    nom              VARCHAR(100) NULL,              -- nom de famille, ou nom de l'entreprise
    prenom           VARCHAR(50)  NULL,
    telephone        VARCHAR(20)  NULL,
    adresse          VARCHAR(255) NULL,
    photo            VARCHAR(255) NULL,              -- chemin du fichier dans uploads/photos
    email_valide     TINYINT(1)   NOT NULL DEFAULT 0,
    token_validation VARCHAR(64)  NULL,              -- code du lien de confirmation
    date_inscription DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (email)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- Stages et formations (un utilisateur en a plusieurs)
-- ---------------------------------------------------------------------
CREATE TABLE experience (
    id_experience INT          NOT NULL AUTO_INCREMENT,
    email         VARCHAR(100) NOT NULL,
    type          ENUM('Stage', 'Formation') NOT NULL,
    titre         VARCHAR(100) NOT NULL,             -- intitulé du stage ou du diplôme
    organisme     VARCHAR(100) NULL,                 -- entreprise ou établissement
    lieu          VARCHAR(100) NULL,
    date_debut    DATE         NOT NULL,
    date_fin      DATE         NULL,                 -- vide = en cours
    description   TEXT         NULL,
    PRIMARY KEY (id_experience),
    FOREIGN KEY (email) REFERENCES utilisateur(email)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- Compétences
-- ---------------------------------------------------------------------
CREATE TABLE competence (
    id_competence INT          NOT NULL AUTO_INCREMENT,
    email         VARCHAR(100) NOT NULL,
    libelle       VARCHAR(100) NOT NULL,
    niveau        ENUM('Débutant', 'Intermédiaire', 'Avancé') NOT NULL,
    PRIMARY KEY (id_competence),
    FOREIGN KEY (email) REFERENCES utilisateur(email)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- Langues
-- ---------------------------------------------------------------------
CREATE TABLE langue (
    id_langue  INT          NOT NULL AUTO_INCREMENT,
    email      VARCHAR(100) NOT NULL,
    nom_langue VARCHAR(50)  NOT NULL,
    niveau     ENUM('A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'Langue maternelle') NOT NULL,
    PRIMARY KEY (id_langue),
    FOREIGN KEY (email) REFERENCES utilisateur(email)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- Centres d'intérêt
-- ---------------------------------------------------------------------
CREATE TABLE centre_interet (
    id_centre INT          NOT NULL AUTO_INCREMENT,
    email     VARCHAR(100) NOT NULL,
    libelle   VARCHAR(100) NOT NULL,
    PRIMARY KEY (id_centre),
    FOREIGN KEY (email) REFERENCES utilisateur(email)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB;

-- =====================================================================
-- PARTIE FACULTATIVE : gestion des candidatures de stage par une entreprise
-- =====================================================================

-- Offre de stage publiée par une entreprise
CREATE TABLE offre_stage (
    id_offre         INT          NOT NULL AUTO_INCREMENT,
    email_entreprise VARCHAR(100) NOT NULL,
    titre            VARCHAR(100) NOT NULL,
    lieu             VARCHAR(100) NOT NULL,
    duree            VARCHAR(50)  NOT NULL,          -- ex. « 2 mois »
    description      TEXT         NOT NULL,
    date_publication DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ouverte          TINYINT(1)   NOT NULL DEFAULT 1, -- 0 = n'accepte plus de candidatures
    PRIMARY KEY (id_offre),
    FOREIGN KEY (email_entreprise) REFERENCES utilisateur(email)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB;

-- Candidature d'un candidat à une offre (une seule par offre et par candidat)
CREATE TABLE candidature (
    id_candidature   INT          NOT NULL AUTO_INCREMENT,
    id_offre         INT          NOT NULL,
    email_candidat   VARCHAR(100) NOT NULL,
    message          TEXT         NULL,              -- court message de motivation
    date_candidature DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    statut           ENUM('En attente', 'Acceptée', 'Refusée') NOT NULL DEFAULT 'En attente',
    PRIMARY KEY (id_candidature),
    UNIQUE (id_offre, email_candidat),
    FOREIGN KEY (id_offre) REFERENCES offre_stage(id_offre)
        ON DELETE CASCADE,
    FOREIGN KEY (email_candidat) REFERENCES utilisateur(email)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB;
