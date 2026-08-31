# Session de tests complète — Projet AMANAH

*À suivre dans l'ordre, cochez au fur et à mesure. Notez tout ce qui ne correspond pas — capture d'écran + description précise, ça va beaucoup plus vite à corriger.*

---

## 0. Avant de commencer

- [x] Toutes les migrations sont passées : `php artisan migrate:status` (aucune ligne sans "Ran")
- [x] `npm run build` a été lancé après les derniers fichiers reçus
- [x] Base de données propre et reseedée si besoin (`vider_donnees_test.sql` + `DonneesTestSeeder`)

---

## 1. Authentification & rôles

- [x] Connexion avec votre compte administrateur → dashboard vue globale
- [x] Connexion avec un gestionnaire de test → dashboard vue portefeuille
- [x] Connexion avec un investisseur → redirection directe vers `/mon-compte`
- [x] **Mot de passe oublié** : `/login` → lien → email reçu → réinitialisation → reconnexion possible
- [x] **Nouveau compte créé** (gestionnaire ou investisseur) → email reçu avec identifiants → première connexion force le changement de mot de passe
- [x] `/register` (inscription libre) désactivée — confirmé fermée (404)

## 2. 2FA — *mis en pause, à reprendre plus tard*

- [ ] Activation sur `/profile` : QR code lisible, code de confirmation accepté
- [ ] Codes de récupération notés
- [ ] Déconnexion/reconnexion → écran de vérification du code avant le dashboard
- [ ] Un code de récupération fonctionne une fois, puis n'est plus accepté
- [ ] Désactivation du 2FA (avec mot de passe) fonctionne

## 3. Investisseurs

- [x] Liste : recherche, tri par colonne, filtre statut/gestionnaire, pagination
- [x] Création rapide : identifiant auto-généré, email inclus
- [x] Fiche détail : toutes les infos KYC s'affichent (pièce d'identité, convention, entreprise si "morale", bénéficiaire)
- [ ] "Modifier le dossier" : tous les champs se sauvegardent, upload pièce d'identité fonctionne
- [x] Bascule réinvestissement automatique Oui/Non par compte, dans "Modifier le dossier"
- [x] "Créer un accès portail" → email reçu → connexion investisseur fonctionne
- [x] "Réinitialiser le mot de passe" (gestionnaire ou investisseur) → nouvel email reçu

## 4. Achats

- [x] Création d'un achat Commercial → compte créé automatiquement si inexistant
- [x] Création d'un achat Waqf → idem
- [x] Mode de paiement (liste), facture jointe, visible ensuite dans l'historique
- [ ] Historique : tri, recherche, filtre type, export CSV et PDF (respectent les filtres)
- [ ] Attestation PDF téléchargeable depuis une ligne d'achat

## 5. Dividendes

- [x] Fixer un barème pour un nouveau mois → comptes crédités, réinvestissement auto déclenché si activé
- [x] Relancer le calcul sur une période déjà traitée → champs verrouillés, aucun doublon
- [x] Un investisseur créé après coup avec un achat backdaté reçoit bien le rattrapage sur les mois manqués
- [x] Délai de carence en fin de mois : modifiez-le, vérifiez l'effet sur un achat de fin de mois
- [x] Correction d'un barème existant → répercussion automatique sur tous les comptes concernés, visible dans le journal d'audit

## 6. Réinvestissement & Complément financier

- [x] Compte avec réinvestissement désactivé : le solde s'accumule sans achat automatique
- [x] "Acheter avec le solde" : aperçu correct, achat confirmé
- [x] "Complément financier" : versement + achat automatique immédiat, justificatif joint

## 7. Radiations & Paiements

- [x] Radiation : impossible de radier plus d'actions que détenues
- [x] Statut Payé/Partiel/Non payé correct après un versement partiel
- [x] Lien "Verser →" pré-rempli avec le bon montant restant
- [x] Règle de versement du capital radié indépendante de celle des dividendes (Waqf : dividendes non versables, mais capital radié si `versement_capital_radiation_possible` = oui)
- [x] Attestation de radiation et de paiement téléchargeables

## 8. Ajustements

- [x] Réservé Direction/Administrateur (gestionnaire ne doit pas y avoir accès)
- [x] Motif obligatoire (min. 15 caractères)
- [x] Solde corrigé visible immédiatement, tracé dans le journal d'audit

## 9. Gestionnaires

- [x] Création : email reçu, changement de mot de passe forcé à la 1ère connexion
- [x] Désactivation : le gestionnaire désactivé ne peut plus se connecter
- [x] Nombre d'investisseurs cliquable → filtre bien la liste

## 10. Journal d'audit

- [x] Chaque action testée ci-dessus (achat, radiation, ajustement, etc.) apparaît bien dans `/audit`
- [x] "Détails" affiche Avant/Après correctement
- [x] Filtres (action, entité, dates, recherche) fonctionnent
- [x] Export CSV et PDF respectent les filtres actifs

## 11. Rapports & Exports

- [x] Relevé PDF investisseur : historique complet, puis avec une période (Du/Au) → solde de départ et solde final cohérents
- [x] Exports globaux (bas de `/investisseurs`) : Achats/Écritures/Radiations, CSV et PDF
- [x] Exports par compte (sur la fiche investisseur) : idem, filtrés correctement

## 12. Sécurité fine (le plus important à valider rigoureusement)

Avec un compte **`lecture`** :
- [ ] Voit les listes/fiches, mais **aucun bouton d'action** nulle part
- [ ] Accès direct par URL à une page de création/modification → **403**
- [ ] Menu : seuls Dashboard et Investisseurs visibles

Avec un compte **`gestionnaire`** :
- [ ] Peut créer/modifier investisseurs, achats, paiements, radiations
- [ ] **Ne peut pas** accéder à `/dividendes/calculer`, `/gestionnaires`, `/audit`, ajustements, **ni déclarer un décès/gérer une succession** (403)

Avec un compte **`investisseur`** :
- [ ] Ne voit que `/mon-compte`, jamais les pages de gestion (même en tapant l'URL)
- [ ] Ne voit que ses propres données (essayez de changer l'URL vers un autre id — doit échouer)

## 13. Affichage mobile

- [x] Menu, listes, formulaires principaux testés en réduisant la fenêtre du navigateur (ou mode responsive F12)

---

## 14. Dons entre investisseurs

- [x] Don d'**actions** entre deux comptes de **même catégorie** : le nombre d'actions diminue chez le donateur, augmente chez le destinataire, aucune écriture financière ne bouge
- [x] La recherche du destinataire ne propose **que** des comptes de la même catégorie (jamais Commercial→Waqf)
- [x] Don de **solde** : écriture "don sortant" chez le donateur, "don entrant" chez le destinataire, réinvestissement automatique déclenché chez le destinataire si activé
- [x] Tentative de don supérieur à ce qui est disponible (actions ou solde) → refusé avec message clair
- [x] Les deux dons testés apparaissent dans `/audit`

## 15. Succession — Déclaration de décès

- [x] "Déclarer un décès" (Direction/Administrateur) : date + acte de décès obligatoire + case de confirmation
- [x] Une fois déclaré : bannière noire sur la fiche, **tous** les boutons d'action disparaissent (achat, complément, paiement, don, radiation)
- [x] Accès direct par URL à une page de création (ex: `/investisseurs/{id}/achats/creer`) sur un compte gelé → refusé
- [x] Le bouton "Créer un accès portail" / "Réinitialiser le mot de passe" disparaît aussi pour un investisseur décédé

## 16. Succession — Mandataire et règlement

- [x] "Gérer la succession" : formulaire demandant CNI, certificat d'hérédité, et procuration — **les 3 obligatoires**, messages d'erreur en français propre (pas de nom de champ technique)
- [x] Un investisseur avec un compte **Waqf** : après règlement, le capital Waqf part automatiquement vers **"Waqf Dolel Xamxam"** (vérifiable dans `/investisseurs`, un nouvel investisseur "morale" de ce nom doit exister)
- [x] Mode **"Transfert vers un compte investisseur"** (Commercial) : le mandataire reçoit un nouveau dossier investisseur (ou son dossier existant est utilisé), actions + solde transférés
- [x] Mode **"Paiement direct"** (Commercial) : après "Régler la succession", un encart **"Versement(s) en attente"** apparaît — le bouton radio change bien de couleur au clic (avant de valider)
- [x] Depuis cet encart, "Verser au mandataire →" ouvre un écran dédié (mode de paiement, preuve obligatoire) — comme "Paiement à l'investisseur"
- [x] Une fois versé : l'écriture "paiement" associée affiche un lien **"📄 Attestation (Décès)"**, distinct de l'attestation classique
- [x] Le PDF de cette attestation mentionne bien : date de décès, mandataire, nombre d'actions liquidées + montant, solde, montant total perçu

## 17. Emails automatiques

- [x] Création d'un gestionnaire → email reçu avec identifiants
- [x] Réinitialisation de mot de passe (gestionnaire ou investisseur) → email reçu
- [x] "Mot de passe oublié" depuis `/login` → email reçu, en français, avec le bon design (fond vert AMANAH)
- [x] Le mot de passe reste aussi affiché à l'écran après chaque action (filet de sécurité si l'email n'arrive pas)

## 18. Traductions

- [x] Pagination : "Affichage de X à Y sur Z résultat(s)" (pas de "Showing... of... results")
- [x] Messages de validation : "Le champ [nom propre] est obligatoire" (pas de nom de variable technique du type `piece_identite_upload`)
- [x] Dates en toutes lettres dans les PDF : mois en français ("août", pas "august")
- [x] Connexion, mot de passe oublié, réinitialisation : entièrement en français

## 19. Sauvegardes

- [x] `php artisan backup:run` fonctionne sans erreur — confirmé
- [x] Le fichier `.zip` contient le dump SQL **et** les fichiers uploadés (`storage/app/public`) — confirmé
- [x] Fichier ouvert depuis l'archive non corrompu — confirmé

---

## Points non techniques à garder en tête

- Le champ "procuration" du module succession n'a **pas encore été validé juridiquement** — à faire vérifier par un juriste/notaire avant tout usage réel avec de vraies successions.
- Envisager une **double validation** (deux personnes) avant le règlement définitif d'une succession — pas encore construit.
- Déploiement en production mis en pause — hébergement à finaliser plus tard.

---

## Une fois cette checklist terminée

On sera prêts à passer à la préparation du déploiement en production, avec une bonne confiance dans la solidité de l'ensemble.
