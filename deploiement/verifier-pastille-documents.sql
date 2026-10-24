-- --------------------------------------------------------------------------
-- Contrôle : la pastille des documents officiels est-elle en place ?
--
-- À coller dans phpMyAdmin, onglet SQL, après avoir sélectionné à gauche la
-- base waqfdole_gestionactionnaires_bd. La requête ne lit que l'état du
-- schéma : elle ne modifie rien et peut se relancer sans risque.
--
-- Les deux colonnes du résultat doivent valoir 1 :
--   colonne_posee   la colonne existe bien sur `users` ;
--   journal_a_jour  Laravel sait que la migration est faite, et ne la
--                   rejouera pas.
--
-- Un 0 à la première : relancer ajouter-pastille-documents.sql.
-- Un 0 à la seconde seulement : la colonne est là, mais une migration
-- ultérieure tenterait de la reposer — relancer le même script, qui
-- n'écrit la ligne du journal que si elle manque.
-- --------------------------------------------------------------------------

SELECT
    (SELECT COUNT(*)
       FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'documents_officiels_vus_le') AS colonne_posee,

    (SELECT COUNT(*)
       FROM `migrations`
      WHERE `migration` = '2026_10_11_140000_retient_la_derniere_visite_des_documents') AS journal_a_jour;
