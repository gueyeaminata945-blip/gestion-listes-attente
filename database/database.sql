CREATE DATABASE IF NOT EXISTS gestion_listes_attente
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE gestion_listes_attente;

CREATE TABLE utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    telephone VARCHAR(20),
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('administrateur', 'agent', 'candidat') NOT NULL,
    service VARCHAR(100),
    departement VARCHAR(100),
    numero_candidat VARCHAR(50),
    date_naissance DATE,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE concours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('DUT', 'DIC', 'LICENCE') NOT NULL,
    annee INT NOT NULL,
    departement VARCHAR(100) NOT NULL
);

CREATE TABLE filieres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    departement VARCHAR(100) NOT NULL,
    capacite_accueil INT NOT NULL,
    concours_id INT,
    FOREIGN KEY (concours_id) REFERENCES concours(id)
);

CREATE TABLE listes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('principale', 'attente') NOT NULL,
    nombre_etudiants INT DEFAULT 0,
    filiere_id INT,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id)
);

CREATE TABLE inscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rang INT NOT NULL,
    date_inscription DATE NOT NULL,
    candidat_id INT,
    liste_id INT,
    FOREIGN KEY (candidat_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (liste_id) REFERENCES listes(id)
);

CREATE TABLE desistements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date_heure DATETIME DEFAULT CURRENT_TIMESTAMP,
    motif VARCHAR(255),
    inscription_id INT,
    filiere_id INT,
    agent_id INT,
    FOREIGN KEY (inscription_id) REFERENCES inscriptions(id),
    FOREIGN KEY (filiere_id) REFERENCES filieres(id),
    FOREIGN KEY (agent_id) REFERENCES utilisateurs(id)
);

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50),
    date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('envoyee', 'confirmee', 'echouee') DEFAULT 'envoyee',
    nb_relances INT NOT NULL DEFAULT 0,
    date_relance DATETIME,
    date_confirmation DATETIME,
    inscription_id INT,
    token VARCHAR(64),
    FOREIGN KEY (inscription_id) REFERENCES inscriptions(id)
);

CREATE TABLE codes_verification (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    code VARCHAR(6) NOT NULL,
    type ENUM('changement', 'reinitialisation') NOT NULL,
    nouveau_hash VARCHAR(255),
    expire_le DATETIME NOT NULL,
    utilise TINYINT(1) NOT NULL DEFAULT 0,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);

CREATE TABLE historiques (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operation VARCHAR(255) NOT NULL,
    cible VARCHAR(255),
    date_heure DATETIME DEFAULT CURRENT_TIMESTAMP,
    acteur_id INT,
    FOREIGN KEY (acteur_id) REFERENCES utilisateurs(id)
);

-- Un administrateur (mot de passe : admin123)
INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role, service)
VALUES ('Gueye', 'Aminata', 'admin@esp.sn',
        '$2y$10$REMPLACER_PAR_VOTRE_HASH', 'administrateur', 'Direction');

INSERT INTO concours (type, annee, departement)
VALUES ('DUT', 2026, 'Génie Informatique');

INSERT INTO filieres (nom, departement, capacite_accueil, concours_id)
VALUES ('Génie Informatique', 'Informatique', 30, 1);

INSERT INTO listes (type, nombre_etudiants, filiere_id)
VALUES ('principale', 0, 1),
       ('attente', 0, 1);

INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role, numero_candidat)
VALUES ('Diop', 'Moussa', 'moussa@esp.sn', '-', 'candidat', 'C001'),
       ('Sow', 'Fatou', 'fatou@esp.sn', '-', 'candidat', 'C002'),
       ('Ba', 'Ibrahima', 'ibrahima@esp.sn', '-', 'candidat', 'C003'),
       ('Fall', 'Awa', 'awa@esp.sn', '-', 'candidat', 'C004');

INSERT INTO inscriptions (rang, date_inscription, candidat_id, liste_id)
VALUES (1, '2026-06-01', (SELECT id FROM utilisateurs WHERE numero_candidat='C001'), 2),
       (2, '2026-06-01', (SELECT id FROM utilisateurs WHERE numero_candidat='C002'), 2),
       (3, '2026-06-01', (SELECT id FROM utilisateurs WHERE numero_candidat='C003'), 2),
       (4, '2026-06-01', (SELECT id FROM utilisateurs WHERE numero_candidat='C004'), 2);