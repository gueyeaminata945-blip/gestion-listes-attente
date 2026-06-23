-- =====================================================================
--  Migration : vérification par e-mail (mot de passe) + notifications
--  À exécuter UNE FOIS sur une base déjà créée avec database.sql.
--  (phpMyAdmin > base gestion_listes_attente > onglet SQL > coller > Exécuter)
-- =====================================================================

USE gestion_listes_attente;

-- Codes de vérification à 6 chiffres envoyés par e-mail.
--  * type 'changement'      : agent connecté qui modifie son mot de passe
--  * type 'reinitialisation': « mot de passe oublié » depuis la page de connexion
-- nouveau_hash contient le futur mot de passe (déjà hashé), appliqué seulement
-- une fois le code confirmé.
CREATE TABLE IF NOT EXISTS codes_verification (
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

-- Jeton unique permettant au candidat de confirmer la réception de sa
-- notification d'admission via un simple lien (sans avoir à se connecter).
ALTER TABLE notifications ADD COLUMN token VARCHAR(64) NULL AFTER inscription_id;
