SET FOREIGN_KEY_CHECKS = 0;

-- On détache d'abord les comptes de connexion investisseur avant de les supprimer,
-- pour éviter tout conflit de contrainte avec investisseurs.user_id
UPDATE investisseurs SET user_id = NULL;
DELETE FROM users WHERE role = 'investisseur';

TRUNCATE TABLE audit_logs;
TRUNCATE TABLE dons;
TRUNCATE TABLE heritiers;
TRUNCATE TABLE ecritures_compte_financier;
TRUNCATE TABLE dividendes;
TRUNCATE TABLE baremes_dividendes;
TRUNCATE TABLE achats_actions;
TRUNCATE TABLE radiations;
TRUNCATE TABLE historique_affectations;
TRUNCATE TABLE comptes_investissement;
TRUNCATE TABLE investisseurs;

SET FOREIGN_KEY_CHECKS = 1;
