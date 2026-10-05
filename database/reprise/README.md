# Reprise de l'existant — classeurs Excel → AMANAH

Scripts ayant servi à reprendre dans la plateforme l'historique tenu jusque-là sous
Excel (`Gestion_Actionnaires_2024-2025.xlsm` et `Gestion_Actionnaires_2026.xlsm`).

Opération menée le 4 et 5 octobre 2026 sur `amanah_repetition`. Chaque étape a été
répétée sur une copie de la base avant d'être appliquée, et chacune est tracée dans
le journal d'audit.

## Ce qui n'est pas ici

Le classeur source et le grand livre extrait ne sont **pas versionnés** — voir
`.gitignore`. Ils portent les noms, téléphones et numéros de pièce d'identité des
actionnaires, ainsi que leur situation financière mois par mois. Dans un dépôt, ces
données resteraient indéfiniment dans l'historique.

Les scripts qui en dépendent attendent donc, dans ce dossier :

| Fichier | Produit par |
|---|---|
| `c2026.xlsm` | copie du classeur de travail |
| `grand_livre.json` | extraction des 24 feuilles mensuelles |
| `taux_precis.json` | taux de dividende en précision complète |

## Déroulé

Dans l'ordre où cela s'est passé.

**1. Investisseurs, achats, soldes** — via l'écran `/import` de la plateforme, avec
des fichiers CSV produits depuis le classeur : 12 investisseurs manquants, 993 achats
réels, 250 lignes de réinvestissement agrégé, 435 soldes.

**2. `lancer.php`** → `rejouer_grand_livre.php` — remplace l'agrégat par le grand livre
mois par mois : 36 barèmes, 5 152 dividendes, 1 452 réinvestissements datés,
106 versements, 64 radiations. Les écarts résiduels avec le classeur deviennent des
écritures d'ajustement étiquetées plutôt que des arrondis silencieux.

**3. `verifier.php`** — compare position et solde de chaque compte au classeur.

**4. `corriger.php`** — supprime les comptes ouverts sans contenu par le rejeu, et
recale la date d'inscription des dossiers sur leur premier achat.

**5. `reenregistrer_taux.php`** — après la migration élargissant
`benefice_par_action` à huit décimales, réenregistre les taux en précision complète.

**6. `affecter_gestionnaire.php`** — rattache les dossiers restés sans gestionnaire.

**7. Corrections ponctuelles**, chacune tranchée par la direction :

- `corriger_waqf.php` — aligne A0107 sur la constante `NOM_WAQF_CARITATIF`, sans
  quoi une succession waqf aurait créé un second compte caritatif vide ; supprime
  son compte commercial, qui ne portait qu'une action inexistante
- `corriger_a0052_a0085.php` — ACH-478 n'était pas un doublon mais un complément ;
  A0085 a tout repris le 16/09/2025
- `corriger_radiations_a0013.php` — recale 54 radiations sur le registre RADIEES
  (numéro, date, mode de paiement) ; retire les ajustements qui recopiaient une
  formule inversée du classeur
- `corriger_libelle_dons.php` — 17 sorties requalifiées en dons au fonctionnement
  du Waqf, 209 172 CFA
- `corriger_r030.php` — A0192 a repris 4 actions et non une
- `retirer_dividende.php <identifiant> <AAAA-MM>` — retire un dividende versé sur
  des actions que le compte ne détenait plus

## Anomalies du classeur relevées en chemin

Elles ne sont pas des défauts de la reprise, et plusieurs restent à corriger côté
Excel :

- **A0107** porte une action commerciale qu'aucun achat ne justifie, apparue en
  décembre 2025 ; le même mois, l'achat ACH-513 de 2 actions waqf n'a jamais été
  repris dans le récapitulatif
- **A0013** : la cellule « solde définitif » de la ligne de juillet calcule `W−U`
  au lieu de `U−W`, dans les deux classeurs
- **A0192** : le registre annonce 5 actions radiées, le grand livre mensuel une seule
- **A0052** : l'achat ACH-478 figure deux fois (lignes 214 et 481)
- **95 actions radiées** sans ligne correspondante dans RADIEES, reconstituées par
  différence et sans date ni montant d'origine

## Où en est la base

Position et soldes concordent avec le classeur, aux cinq corrections près décidées
contre lui — 41 521,13 CFA au total, tous des montants que le classeur portait à tort.

`php artisan amanah:verifier-coherence` ne signale plus que les soldes
momentanément négatifs : quatre découverts passagers venus du classeur, quatre creux
de centimes dus à l'ordre des écritures du rejeu. Aucun compte n'est à découvert.
