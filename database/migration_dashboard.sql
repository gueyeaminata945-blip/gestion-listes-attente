-- =====================================================================
--  Migration : statistiques du tableau de bord (BM-06)
--  Relie chaque désistement à sa filière pour calculer les taux par filière.
--  À exécuter UNE FOIS (phpMyAdmin > base > onglet SQL).
-- =====================================================================

USE gestion_listes_attente;

ALTER TABLE desistements
    ADD COLUMN filiere_id INT NULL AFTER inscription_id,
    ADD CONSTRAINT fk_desistements_filiere
        FOREIGN KEY (filiere_id) REFERENCES filieres(id);
