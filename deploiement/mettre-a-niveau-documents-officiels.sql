-- --------------------------------------------------------------------------
-- Met la base en ligne à niveau pour les documents officiels — en un seul
-- passage, et en disant ce qu'il fait.
--
-- Ce script remplace ajouter-documents-officiels.sql et
-- ajouter-pastille-documents.sql : il fait le travail des deux, et il annonce
-- sur quelle base il opère. C'est ce dernier point qui manquait : lancé sans
-- qu'on voie la base sélectionnée, un script peut réussir ailleurs que là où
-- on le croit, et le contrôle d'après dire « rien ».
--
-- MODE D'EMPLOI
--   1. phpMyAdmin → dans la colonne de GAUCHE, cliquer sur la base de
--      l'application (celle que porte DB_DATABASE dans le .env du serveur).
--   2. Onglet SQL → coller tout ce fichier → Exécuter.
--   3. Lire le DERNIER tableau de résultats. Les trois colonnes doivent
--      valoir 1, et `base` doit porter le nom attendu.
--
-- Il ne détruit rien et se relance sans dommage : chaque pièce n'est posée
-- que si elle manque.
-- --------------------------------------------------------------------------

SET NAMES utf8mb4;

-- ---- 1. Où sommes-nous, et dans quel état ? ------------------------------
-- Si `base` n'est pas la base de l'application, ARRÊTEZ-VOUS ICI : rien de ce
-- qui suit n'aura servi. Sélectionnez la bonne base à gauche et recommencez.

SELECT
    DATABASE() AS base,
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'migrations') AS est_bien_amanah,
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents_officiels') AS table_avant,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'documents_officiels_vus_le') AS colonne_avant;


-- ---- 2. La table des documents officiels ---------------------------------

CREATE TABLE IF NOT EXISTS `documents_officiels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `titre` varchar(200) NOT NULL,
  `description` text DEFAULT NULL COMMENT 'Ce que le document contient, pour qui hésite à l''ouvrir.',
  `fichier_path` varchar(255) NOT NULL COMMENT 'Chemin sur le disque privé — jamais servi directement.',
  `nom_fichier` varchar(255) NOT NULL COMMENT 'Le nom d''origine, rendu au téléchargement.',
  `taille` bigint(20) unsigned NOT NULL COMMENT 'En octets, pour l''annoncer avant le clic.',
  `publie_par` bigint(20) unsigned DEFAULT NULL,
  `date_document` date DEFAULT NULL COMMENT 'La date que porte le document, qui n''est pas celle du dépôt.',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `documents_officiels_publie_par_foreign` (`publie_par`),
  KEY `documents_officiels_created_at_index` (`created_at`),
  CONSTRAINT `documents_officiels_publie_par_foreign`
    FOREIGN KEY (`publie_par`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---- 3. La colonne de la pastille ----------------------------------------
-- Posée par requête préparée plutôt que par ADD COLUMN IF NOT EXISTS : la
-- forme courte est propre à MariaDB, celle-ci passe partout, et surtout elle
-- ne fait rien — sans erreur — quand la colonne est déjà là. Un script qui
-- s'arrête sur une erreur au milieu laisse le travail à moitié fait.

SET @colonne_manque := (
    SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'users'
       AND COLUMN_NAME = 'documents_officiels_vus_le'
);

SET @poser := IF(@colonne_manque,
    'ALTER TABLE `users` ADD COLUMN `documents_officiels_vus_le` timestamp NULL DEFAULT NULL COMMENT ''Dernier passage sur les documents officiels ; null = jamais.''',
    'DO 0');

PREPARE requete FROM @poser;
EXECUTE requete;
DEALLOCATE PREPARE requete;


-- ---- 4. Le journal des migrations ----------------------------------------
-- Pour que Laravel tienne ces deux migrations pour faites et ne tente jamais
-- de reposer une table ou une colonne déjà présentes.

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_10_170000_cree_les_documents_officiels', 8 FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM `migrations`
                    WHERE `migration` = '2026_10_10_170000_cree_les_documents_officiels');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_11_140000_retient_la_derniere_visite_des_documents', 9 FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM `migrations`
                    WHERE `migration` = '2026_10_11_140000_retient_la_derniere_visite_des_documents');


-- ---- 5. Le verdict -------------------------------------------------------
-- Les trois colonnes doivent valoir 1. C'est ce tableau qu'il faut lire.

SELECT
    DATABASE() AS base,
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents_officiels') AS table_posee,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'documents_officiels_vus_le') AS colonne_posee,
    -- Les deux lignes attendues, ramenées à un oui ou un non pour que les
    -- trois colonnes se lisent de la même façon.
    (SELECT COUNT(*) = 2 FROM `migrations`
      WHERE `migration` IN (
            '2026_10_10_170000_cree_les_documents_officiels',
            '2026_10_11_140000_retient_la_derniere_visite_des_documents')) AS journal_a_jour;
