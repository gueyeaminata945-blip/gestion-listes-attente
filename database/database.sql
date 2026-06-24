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

-- =====================================================================
--  Données de test partagées (export nettoyé).
--  Connexion : admin@esp.sn / admin123   |   agents / agent123
-- =====================================================================

INSERT INTO utilisateurs (id, nom, prenom, email, telephone, mot_de_passe, role, service, departement, numero_candidat, date_naissance) VALUES
(1, 'Gueye', 'Aminata', 'admin@esp.sn', '770926393', '$2y$10$LQjkfBRVrTbAgT75FxjleOs3JRsw.tvbhG9l4qdDNbRv1Kmeg7tzm', 'administrateur', 'Direction', NULL, NULL, NULL),
(8, 'Diallo', 'Ousmane', 'agent@esp.sn', '770000000', '$2y$10$HEKPddojoAd8PUcuQLVKQOtl0lW5EfuoSSl.AAeHzzFL.Vb2bFvkG', 'agent', NULL, 'Génie Informatique', NULL, NULL),
(11, 'Diallo', 'Ousmane', 'ousmane@esp.sn', '771111111', '$2y$10$HEKPddojoAd8PUcuQLVKQOtl0lW5EfuoSSl.AAeHzzFL.Vb2bFvkG', 'agent', NULL, 'Génie Informatique', NULL, NULL),
(12, 'Ndour', 'Aïssatou', 'aissatou@esp.sn', '772222222', '$2y$10$HEKPddojoAd8PUcuQLVKQOtl0lW5EfuoSSl.AAeHzzFL.Vb2bFvkG', 'agent', NULL, 'Génie Civil', NULL, NULL),
(13, 'Sy', 'Babacar', 'babacar@esp.sn', '773333333', '$2y$10$HEKPddojoAd8PUcuQLVKQOtl0lW5EfuoSSl.AAeHzzFL.Vb2bFvkG', 'agent', NULL, 'Génie Électrique', NULL, NULL),
(24, 'Sarr', 'Modou', 'modou@esp.sn', '770000001', '-', 'candidat', NULL, NULL, 'C001', NULL),
(25, 'Kane', 'Bineta', 'bineta@esp.sn', '770000002', '-', 'candidat', NULL, NULL, 'C002', NULL),
(26, 'Diop', 'Moussa', 'moussa@esp.sn', '770000003', '-', 'candidat', NULL, NULL, 'C003', NULL),
(27, 'Sow', 'Fatou', 'fatou@esp.sn', '770000004', '-', 'candidat', NULL, NULL, 'C004', NULL),
(28, 'Ba', 'Ibrahima', 'c005@esp.sn', '770000005', '-', 'candidat', NULL, NULL, 'C005', NULL),
(29, 'Fall', 'Awa', 'awa@esp.sn', '770000006', '-', 'candidat', NULL, NULL, 'C006', NULL),
(30, 'Ndiaye', 'Cheikh', 'cheikh@esp.sn', '770000007', '-', 'candidat', NULL, NULL, 'C007', NULL),
(31, 'Faye', 'Mariama', 'mariama@esp.sn', '770000008', '-', 'candidat', NULL, NULL, 'C008', NULL),
(32, 'Gueye', 'Abdou', 'abdou@esp.sn', '770000009', '-', 'candidat', NULL, NULL, 'C009', NULL),
(33, 'Thiam', 'Khady', 'khady@esp.sn', '770000010', '-', 'candidat', NULL, NULL, 'C010', NULL);

INSERT INTO concours (id, type, annee, departement) VALUES
(1, 'DUT', 2026, 'Génie Informatique'),
(2, 'DUT', 2026, 'Génie Civil'),
(3, 'DIC', 2026, 'Génie Électrique'),
(4, 'LICENCE', 2026, 'Gestion'),
(8, 'DIC', 2026, 'Génie informatique');

INSERT INTO filieres (id, nom, departement, capacite_accueil, concours_id) VALUES
(1, 'Génie Informatique', 'Informatique', 30, 1),
(2, 'Réseaux et Télécoms', 'Informatique', 25, 1),
(3, 'Génie Civil', 'Civil', 20, 2),
(4, 'Génie Électrique', 'Électrique', 25, 3),
(5, 'Gestion des Entreprises', 'Gestion', 35, 4),
(9, 'GLSI', 'Génie informatique', 25, 8);

INSERT INTO listes (id, type, nombre_etudiants, filiere_id) VALUES
(1, 'principale', 1, 1),
(2, 'attente', 0, 1),
(3, 'principale', 0, 2),
(4, 'attente', 0, 2),
(5, 'principale', 0, 3),
(6, 'attente', 0, 3),
(7, 'principale', 0, 4),
(8, 'attente', 0, 4),
(9, 'principale', 0, 5),
(10, 'attente', 0, 5),
(17, 'principale', 0, 9),
(18, 'attente', 0, 9);

INSERT INTO inscriptions (id, rang, date_inscription, candidat_id, liste_id) VALUES
(5, 1, '2026-06-01', 28, 1),
(6, 1, '2026-06-01', 29, 3),
(7, 1, '2026-06-01', 30, 4),
(8, 2, '2026-06-01', 31, 4),
(9, 1, '2026-06-01', 32, 5),
(10, 1, '2026-06-01', 33, 6);

-- Les tables desistements, notifications, codes_verification et historiques
-- sont volontairement vides : elles se remplissent à l'usage de l'application.
