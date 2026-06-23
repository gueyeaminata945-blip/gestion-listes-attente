-- =====================================================================
--  Migration : relances automatiques des notifications (BM-05)
--  À exécuter UNE FOIS (phpMyAdmin > base > onglet SQL).
-- =====================================================================

USE gestion_listes_attente;

ALTER TABLE notifications
    ADD COLUMN nb_relances INT NOT NULL DEFAULT 0 AFTER statut,
    ADD COLUMN date_relance DATETIME NULL AFTER nb_relances;
