-- =====================================================================
--  Migration : demandes de désistement initiées par le candidat (BM-04)
--  Le candidat envoie une demande -> l'agent du département est notifié
--  -> l'agent enregistre (ou rejette) le désistement.
--  À exécuter UNE FOIS (phpMyAdmin > base > onglet SQL).
-- =====================================================================

USE gestion_listes_attente;

CREATE TABLE demandes_desistement (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inscription_id INT,
    candidat_id INT NOT NULL,
    filiere_id INT NOT NULL,
    motif VARCHAR(255),
    statut ENUM('en_attente', 'traitee', 'rejetee') NOT NULL DEFAULT 'en_attente',
    date_demande DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_traitement DATETIME NULL,
    agent_id INT NULL,
    FOREIGN KEY (inscription_id) REFERENCES inscriptions(id),
    FOREIGN KEY (candidat_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (filiere_id) REFERENCES filieres(id),
    FOREIGN KEY (agent_id) REFERENCES utilisateurs(id)
);
