<?php

namespace App\Support;

use App\Models\AchatAction;
use App\Models\CompteInvestissement;
use App\Models\Gestionnaire;
use App\Models\Investisseur;
use App\Models\PolitiqueInvestissement;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Reprise de l'existant : contrôle puis enregistrement d'un fichier Excel/CSV
 * dans la base (gestionnaires, investisseurs, achats, écritures financières).
 *
 * Deux temps volontairement séparés :
 *   1. analyser()  — ne touche jamais la base, produit un verdict ligne par ligne ;
 *   2. importer()  — n'enregistre que les lignes déclarées valides, dans une transaction.
 *
 * Toutes les écritures financières passent par CompteInvestissement::ajouterEcriture(),
 * seul point d'entrée autorisé pour faire bouger un solde : un import ne doit pas
 * pouvoir contourner la règle de calcul du solde.
 */
class ImportateurDonnees
{
    public const TYPES = [
        'gestionnaires' => 'Gestionnaires',
        'investisseurs' => 'Investisseurs',
        'achats' => 'Achats d\'actions',
        'ecritures' => 'Écritures financières',
    ];

    protected const CATEGORIES = [
        'commercial' => ['commercial', 'com', 'commerciale'],
        'waqf' => ['waqf', 'wakf', 'wagf'],
    ];

    protected const TYPES_PERSONNE = [
        'physique' => ['physique', 'personne physique', 'particulier', 'individu'],
        'morale' => ['morale', 'personne morale', 'entreprise', 'societe', 'société'],
    ];

    protected const TYPES_ACHAT = [
        'initial' => ['initial', 'initiale', 'premier achat'],
        'rajout' => ['rajout', 'ajout', 'supplementaire'],
        'complement' => ['complement', 'complement financier'],
        'benefice' => ['benefice', 'reinvestissement', 'dividende reinvesti'],
    ];

    protected const TYPES_ECRITURE = [
        'dividende' => ['dividende', 'dividendes', 'benefice'],
        'versement_complementaire' => ['versement_complementaire', 'versement complementaire', 'complement', 'complement financier', 'versement'],
        'achat_action' => ['achat_action', 'achat action', 'achat', 'achat d actions'],
        'paiement' => ['paiement', 'reglement', 'versement a l investisseur'],
        'radiation' => ['radiation', 'retrait'],
        'ajustement' => ['ajustement', 'correction', 'solde initial', 'solde de depart', 'report'],
    ];

    /** Sens imposé par la comptabilité de l'application (voir EcritureCompteFinancier). */
    protected const SENS_ECRITURE = [
        'dividende' => 'credit',
        'versement_complementaire' => 'credit',
        'radiation' => 'credit',
        'achat_action' => 'debit',
        'paiement' => 'debit',
        'ajustement' => 'libre',
    ];

    // ------------------------------------------------------------- Schémas

    /**
     * Colonnes attendues par type de fichier. La clé est le nom technique de colonne
     * (les en-têtes du fichier y sont ramenés par LecteurTableur::normaliserEntete()).
     */
    public static function schema(string $type): array
    {
        return match ($type) {
            'gestionnaires' => [
                'nom' => ['libelle' => 'Nom', 'obligatoire' => true, 'exemple' => 'Diallo'],
                'prenom' => ['libelle' => 'Prénom', 'obligatoire' => false, 'exemple' => 'Fatoumata'],
                'email' => ['libelle' => 'Email (identifiant de connexion)', 'obligatoire' => true, 'exemple' => 'fatoumata.diallo@exemple.sn'],
                'telephone' => ['libelle' => 'Téléphone', 'obligatoire' => false, 'exemple' => '771234500'],
            ],
            'investisseurs' => [
                'identifiant_externe' => ['libelle' => 'Identifiant', 'obligatoire' => false, 'exemple' => 'A0001', 'aide' => 'Repris de l\'ancien fichier. Laissé vide, il est généré automatiquement (A0001, A0002...).'],
                'type_personne' => ['libelle' => 'Type de personne', 'obligatoire' => false, 'exemple' => 'physique', 'aide' => 'physique ou morale — par défaut physique.'],
                'nom' => ['libelle' => 'Nom', 'obligatoire' => true, 'exemple' => 'Ndiaye', 'aide' => 'Pour une personne morale, la raison sociale suffit.'],
                'prenom' => ['libelle' => 'Prénom', 'obligatoire' => false, 'exemple' => 'Fatou'],
                'telephone' => ['libelle' => 'Téléphone', 'obligatoire' => false, 'exemple' => '771234501'],
                'email' => ['libelle' => 'Email', 'obligatoire' => false, 'exemple' => 'fatou.ndiaye@exemple.sn'],
                'gestionnaire_email' => ['libelle' => 'Email du gestionnaire', 'obligatoire' => false, 'exemple' => 'fatoumata.diallo@exemple.sn', 'aide' => 'Rattache le dossier à un gestionnaire existant.'],
                'statut' => ['libelle' => 'Statut', 'obligatoire' => false, 'exemple' => 'actif', 'aide' => 'actif ou inactif. Un décès se déclare depuis la fiche, pas par import.'],
                'type_identification' => ['libelle' => 'Type de pièce', 'obligatoire' => false, 'exemple' => 'CNI'],
                'numero_identification' => ['libelle' => 'Numéro de pièce', 'obligatoire' => false, 'exemple' => '1234567890123'],
                'date_delivrance_piece' => ['libelle' => 'Date de délivrance', 'obligatoire' => false, 'exemple' => '12/03/2020'],
                'lieu_delivrance_piece' => ['libelle' => 'Lieu de délivrance', 'obligatoire' => false, 'exemple' => 'Dakar'],
                'date_expiration_piece' => ['libelle' => 'Date d\'expiration', 'obligatoire' => false, 'exemple' => '12/03/2030'],
                'pays' => ['libelle' => 'Pays', 'obligatoire' => false, 'exemple' => 'Sénégal'],
                'adresse' => ['libelle' => 'Adresse', 'obligatoire' => false, 'exemple' => 'Sacré-Cœur 3, villa 45'],
                'ville' => ['libelle' => 'Ville', 'obligatoire' => false, 'exemple' => 'Dakar'],
                'date_naissance' => ['libelle' => 'Date de naissance', 'obligatoire' => false, 'exemple' => '05/06/1985'],
                'lieu_naissance' => ['libelle' => 'Lieu de naissance', 'obligatoire' => false, 'exemple' => 'Thiès'],
                'nationalite' => ['libelle' => 'Nationalité', 'obligatoire' => false, 'exemple' => 'Sénégalaise'],
                'raison_sociale' => ['libelle' => 'Raison sociale', 'obligatoire' => false, 'exemple' => '', 'aide' => 'Personne morale uniquement.'],
                'rccm' => ['libelle' => 'RCCM', 'obligatoire' => false, 'exemple' => ''],
                'ninea' => ['libelle' => 'NINEA', 'obligatoire' => false, 'exemple' => ''],
                'representant_legal_nom' => ['libelle' => 'Représentant légal', 'obligatoire' => false, 'exemple' => ''],
                'representant_legal_telephone' => ['libelle' => 'Téléphone du représentant', 'obligatoire' => false, 'exemple' => ''],
                'beneficiaire_nom' => ['libelle' => 'Bénéficiaire désigné', 'obligatoire' => false, 'exemple' => 'Ndiaye Awa'],
                'beneficiaire_lien' => ['libelle' => 'Lien du bénéficiaire', 'obligatoire' => false, 'exemple' => 'Épouse'],
                'beneficiaire_telephone' => ['libelle' => 'Téléphone du bénéficiaire', 'obligatoire' => false, 'exemple' => '771234502'],
                'date_signature_convention' => ['libelle' => 'Date de signature de la convention', 'obligatoire' => false, 'exemple' => '01/02/2024'],
                'notes_internes' => ['libelle' => 'Notes internes', 'obligatoire' => false, 'exemple' => ''],
            ],
            'achats' => [
                'identifiant_externe' => ['libelle' => 'Identifiant investisseur', 'obligatoire' => true, 'exemple' => 'A0001', 'aide' => 'L\'investisseur doit déjà exister dans la base.'],
                'categorie' => ['libelle' => 'Catégorie', 'obligatoire' => true, 'exemple' => 'commercial', 'aide' => 'commercial ou waqf. Le compte est créé s\'il n\'existe pas encore.'],
                'date_achat' => ['libelle' => 'Date de l\'achat', 'obligatoire' => true, 'exemple' => '15/01/2024'],
                'nombre_actions' => ['libelle' => 'Nombre d\'actions', 'obligatoire' => true, 'exemple' => '4'],
                'prix_unitaire' => ['libelle' => 'Prix unitaire', 'obligatoire' => false, 'exemple' => '25000', 'aide' => 'Vide = prix de la politique d\'investissement en vigueur.'],
                'montant' => ['libelle' => 'Montant total', 'obligatoire' => false, 'exemple' => '100000', 'aide' => 'Vide = nombre d\'actions × prix unitaire. Sinon la cohérence est vérifiée.'],
                'type_achat' => ['libelle' => 'Type d\'achat', 'obligatoire' => false, 'exemple' => 'initial', 'aide' => 'initial, rajout, complement ou benefice — par défaut initial.'],
                'mode_paiement' => ['libelle' => 'Mode de paiement', 'obligatoire' => false, 'exemple' => 'Wave'],
                'reference_facture' => ['libelle' => 'Référence facture', 'obligatoire' => false, 'exemple' => 'FACT-2024-001'],
                'numero_achat' => ['libelle' => 'Numéro d\'achat', 'obligatoire' => false, 'exemple' => '', 'aide' => 'Laissé vide, un numéro IMP-xxxxx est attribué.'],
                'reinvestissement_auto' => ['libelle' => 'Réinvestissement automatique', 'obligatoire' => false, 'exemple' => 'oui', 'aide' => 'Applique le réglage au compte concerné (oui/non).'],
                'observations' => ['libelle' => 'Observations', 'obligatoire' => false, 'exemple' => 'Reprise historique'],
            ],
            'ecritures' => [
                'identifiant_externe' => ['libelle' => 'Identifiant investisseur', 'obligatoire' => true, 'exemple' => 'A0001'],
                'categorie' => ['libelle' => 'Catégorie', 'obligatoire' => true, 'exemple' => 'commercial', 'aide' => 'commercial ou waqf.'],
                'type_ecriture' => ['libelle' => 'Type d\'écriture', 'obligatoire' => true, 'exemple' => 'ajustement', 'aide' => 'dividende, versement_complementaire, achat_action, paiement, radiation ou ajustement. Pour un solde de départ : ajustement.'],
                'montant' => ['libelle' => 'Montant', 'obligatoire' => true, 'exemple' => '50000', 'aide' => 'Crédit positif, débit négatif. Le sens est corrigé automatiquement selon le type.'],
                'date_ecriture' => ['libelle' => 'Date', 'obligatoire' => true, 'exemple' => '31/01/2024'],
                'observations' => ['libelle' => 'Observations', 'obligatoire' => false, 'exemple' => 'Solde repris de l\'ancien fichier'],
            ],
            default => throw new \InvalidArgumentException("Type d'import inconnu : {$type}"),
        };
    }

    /**
     * En-têtes couramment employés dans les fichiers existants, ramenés au nom technique
     * de la colonne. Évite d'obliger le client à réécrire les titres de son tableau :
     * « Date de naissance », « Nb actions » ou « N° pièce » sont reconnus tels quels.
     *
     * Les clés sont déjà sous forme normalisée (minuscules, sans accent, underscores).
     */
    public static function alias(string $type): array
    {
        $communs = [
            'identifiant' => 'identifiant_externe',
            'identifiant_investisseur' => 'identifiant_externe',
            'id_investisseur' => 'identifiant_externe',
            'code_investisseur' => 'identifiant_externe',
            'numero_investisseur' => 'identifiant_externe',
            'n_investisseur' => 'identifiant_externe',
            'code' => 'identifiant_externe',
            'prenoms' => 'prenom',
            'tel' => 'telephone',
            'numero_telephone' => 'telephone',
            'mail' => 'email',
            'e_mail' => 'email',
            'courriel' => 'email',
            'adresse_email' => 'email',
            // Libellé exact du modèle téléchargeable (ModeleImportController) : doit
            // rester reconnu si le fichier est réimporté sans modifier les en-têtes.
            'email_identifiant_de_connexion' => 'email',
        ];

        $particuliers = match ($type) {
            'gestionnaires' => [],
            'investisseurs' => [
                'type' => 'type_personne',
                'type_de_personne' => 'type_personne',
                'personne' => 'type_personne',
                'gestionnaire' => 'gestionnaire_email',
                'email_gestionnaire' => 'gestionnaire_email',
                'email_du_gestionnaire' => 'gestionnaire_email',
                'date_de_naissance' => 'date_naissance',
                'ne_le' => 'date_naissance',
                'naissance' => 'date_naissance',
                'lieu_de_naissance' => 'lieu_naissance',
                'type_de_piece' => 'type_identification',
                'type_piece' => 'type_identification',
                'piece_identite' => 'type_identification',
                'numero_piece' => 'numero_identification',
                'numero_de_piece' => 'numero_identification',
                'n_piece' => 'numero_identification',
                'cni' => 'numero_identification',
                'numero_cni' => 'numero_identification',
                'date_de_delivrance' => 'date_delivrance_piece',
                'delivrance' => 'date_delivrance_piece',
                'lieu_de_delivrance' => 'lieu_delivrance_piece',
                'date_expiration' => 'date_expiration_piece',
                'date_d_expiration' => 'date_expiration_piece',
                'expiration' => 'date_expiration_piece',
                'denomination' => 'raison_sociale',
                'denomination_sociale' => 'raison_sociale',
                'representant_legal' => 'representant_legal_nom',
                'telephone_representant' => 'representant_legal_telephone',
                'telephone_du_representant' => 'representant_legal_telephone',
                'beneficiaire' => 'beneficiaire_nom',
                'beneficiaire_designe' => 'beneficiaire_nom',
                'lien_beneficiaire' => 'beneficiaire_lien',
                'lien_de_parente' => 'beneficiaire_lien',
                'lien_du_beneficiaire' => 'beneficiaire_lien',
                'telephone_beneficiaire' => 'beneficiaire_telephone',
                'telephone_du_beneficiaire' => 'beneficiaire_telephone',
                'date_convention' => 'date_signature_convention',
                'date_signature' => 'date_signature_convention',
                'date_de_signature_de_la_convention' => 'date_signature_convention',
                'notes' => 'notes_internes',
                'remarques' => 'notes_internes',
                'observations' => 'notes_internes',
            ],
            'achats' => [
                'date' => 'date_achat',
                'date_d_achat' => 'date_achat',
                'date_de_l_achat' => 'date_achat',
                'type' => 'type_achat',
                'type_d_achat' => 'type_achat',
                'categorie_de_compte' => 'categorie',
                'type_de_compte' => 'categorie',
                'compte' => 'categorie',
                'nb_actions' => 'nombre_actions',
                'nombre_d_actions' => 'nombre_actions',
                'actions' => 'nombre_actions',
                'quantite' => 'nombre_actions',
                'prix' => 'prix_unitaire',
                'prix_de_l_action' => 'prix_unitaire',
                'prix_unitaire_action' => 'prix_unitaire',
                'montant_total' => 'montant',
                'montant_paye' => 'montant',
                'total' => 'montant',
                'mode_de_paiement' => 'mode_paiement',
                'moyen_de_paiement' => 'mode_paiement',
                'paiement' => 'mode_paiement',
                'facture' => 'reference_facture',
                'numero_facture' => 'reference_facture',
                'n_facture' => 'reference_facture',
                'numero_d_achat' => 'numero_achat',
                'n_achat' => 'numero_achat',
                'reinvestissement' => 'reinvestissement_auto',
                'reinvestissement_automatique' => 'reinvestissement_auto',
                'observation' => 'observations',
                'commentaire' => 'observations',
                'libelle' => 'observations',
            ],
            'ecritures' => [
                'date' => 'date_ecriture',
                'date_operation' => 'date_ecriture',
                'date_de_l_ecriture' => 'date_ecriture',
                'type' => 'type_ecriture',
                'type_d_ecriture' => 'type_ecriture',
                'operation' => 'type_ecriture',
                'nature' => 'type_ecriture',
                'categorie_de_compte' => 'categorie',
                'type_de_compte' => 'categorie',
                'compte' => 'categorie',
                'somme' => 'montant',
                'montant_cfa' => 'montant',
                'observation' => 'observations',
                'commentaire' => 'observations',
                'libelle' => 'observations',
                'description' => 'observations',
            ],
            default => [],
        };

        return array_merge($communs, $particuliers);
    }

    /**
     * Renomme les colonnes reconnues sous un autre intitulé. Un alias est ignoré si la
     * colonne officielle est déjà présente dans le fichier : les données explicites priment.
     *
     * @param  array{entetes: array, lignes: array}  $contenu  sortie de LecteurTableur::lire()
     */
    public static function appliquerAlias(string $type, array $contenu): array
    {
        $canoniques = array_keys(static::schema($type));
        $alias = static::alias($type);

        $renommages = [];
        $dejaPrises = array_intersect($contenu['entetes'], $canoniques);

        foreach ($contenu['entetes'] as $entete) {
            if (in_array($entete, $canoniques, true)) {
                continue;
            }

            $cible = $alias[$entete] ?? null;

            if ($cible === null || in_array($cible, $dejaPrises, true)) {
                continue;
            }

            $renommages[$entete] = $cible;
            $dejaPrises[] = $cible;
        }

        if ($renommages === []) {
            return $contenu;
        }

        $contenu['entetes'] = array_map(fn ($entete) => $renommages[$entete] ?? $entete, $contenu['entetes']);

        foreach ($contenu['lignes'] as $index => $ligne) {
            $valeurs = [];
            foreach ($ligne['valeurs'] as $colonne => $valeur) {
                $valeurs[$renommages[$colonne] ?? $colonne] = $valeur;
            }
            $contenu['lignes'][$index]['valeurs'] = $valeurs;
        }

        return $contenu;
    }

    /** Colonnes affichées dans le tableau de contrôle (les autres restent importées). */
    public static function colonnesApercu(string $type): array
    {
        return match ($type) {
            'gestionnaires' => ['nom', 'prenom', 'email', 'telephone'],
            'investisseurs' => ['identifiant_externe', 'nom', 'prenom', 'telephone', 'gestionnaire_email'],
            'achats' => ['identifiant_externe', 'categorie', 'date_achat', 'nombre_actions', 'prix_unitaire', 'montant'],
            'ecritures' => ['identifiant_externe', 'categorie', 'type_ecriture', 'date_ecriture', 'montant'],
            default => [],
        };
    }

    public static function colonnesObligatoires(string $type): array
    {
        return array_keys(array_filter(static::schema($type), fn ($colonne) => $colonne['obligatoire']));
    }

    // ------------------------------------------------------------ Analyse

    /**
     * Contrôle chaque ligne sans rien enregistrer.
     *
     * @param  array  $lignes  sortie de LecteurTableur::lire()['lignes']
     * @return array<int, array{numero: int, statut: string, messages: array, donnees: array}>
     */
    public function analyser(string $type, array $lignes): array
    {
        $reference = $this->donneesDeReference($type);
        $vuesDansLeFichier = [];
        $resultats = [];

        foreach ($lignes as $ligne) {
            $resultats[] = $this->analyserLigne($type, $ligne['numero'], $ligne['valeurs'], $reference, $vuesDansLeFichier);
        }

        return $resultats;
    }

    /**
     * Tout ce qui existe déjà en base et sert à valider les lignes : chargé une seule
     * fois, sinon un fichier de 2 000 lignes déclencherait autant de requêtes.
     */
    protected function donneesDeReference(string $type): array
    {
        $reference = [];

        if ($type === 'gestionnaires') {
            $reference['emails'] = User::pluck('email')->map(fn ($email) => strtolower($email))->flip()->all();
        }

        if ($type === 'investisseurs') {
            $reference['identifiants'] = Investisseur::pluck('identifiant_externe')->map(fn ($id) => strtoupper($id))->flip()->all();
            $reference['gestionnaires'] = Gestionnaire::with('user')->get()
                ->mapWithKeys(fn ($g) => [strtolower($g->user->email) => ['id' => $g->id, 'actif' => $g->actif]])
                ->all();
        }

        if (in_array($type, ['achats', 'ecritures'], true)) {
            $reference['investisseurs'] = Investisseur::get(['id', 'identifiant_externe', 'nom', 'prenom', 'statut'])
                ->mapWithKeys(fn ($i) => [strtoupper($i->identifiant_externe) => [
                    'id' => $i->id,
                    'nom' => trim($i->nom . ' ' . $i->prenom),
                    'statut' => $i->statut,
                ]])
                ->all();
        }

        if ($type === 'achats') {
            $reference['numeros_achat'] = AchatAction::pluck('numero_achat')->flip()->all();
            $reference['prix'] = PolitiqueInvestissement::pluck('prix_unitaire_action', 'categorie')->all();
            // Signature d'un achat déjà en base : évite de réimporter deux fois le même fichier.
            $reference['achats_existants'] = AchatAction::query()
                ->join('comptes_investissement', 'comptes_investissement.id', '=', 'achats_actions.compte_id')
                ->join('investisseurs', 'investisseurs.id', '=', 'comptes_investissement.investisseur_id')
                ->get(['investisseurs.identifiant_externe', 'comptes_investissement.categorie', 'achats_actions.date_achat', 'achats_actions.nombre_actions', 'achats_actions.montant'])
                ->map(fn ($a) => strtoupper($a->identifiant_externe) . '|' . $a->categorie . '|' . substr((string) $a->date_achat, 0, 10) . '|' . (int) $a->nombre_actions . '|' . number_format((float) $a->montant, 2, '.', ''))
                ->flip()
                ->all();
        }

        if ($type === 'ecritures') {
            // Solde actuel de chaque compte : permet de dérouler le solde ligne après ligne
            // pendant l'analyse et de signaler une écriture qui le ferait passer sous zéro.
            $reference['soldes'] = \Illuminate\Support\Facades\DB::table('ecritures_compte_financier as e')
                ->joinSub(
                    \Illuminate\Support\Facades\DB::table('ecritures_compte_financier')
                        ->selectRaw('compte_id, MAX(id) as dernier_id')
                        ->groupBy('compte_id'),
                    'derniere',
                    'derniere.dernier_id',
                    '=',
                    'e.id'
                )
                ->join('comptes_investissement as c', 'c.id', '=', 'e.compte_id')
                ->join('investisseurs as i', 'i.id', '=', 'c.investisseur_id')
                ->get(['i.identifiant_externe', 'c.categorie', 'e.solde_apres'])
                ->mapWithKeys(fn ($s) => [strtoupper($s->identifiant_externe) . '|' . $s->categorie => (float) $s->solde_apres])
                ->all();

            $reference['ecritures_existantes'] = \App\Models\EcritureCompteFinancier::query()
                ->join('comptes_investissement', 'comptes_investissement.id', '=', 'ecritures_compte_financier.compte_id')
                ->join('investisseurs', 'investisseurs.id', '=', 'comptes_investissement.investisseur_id')
                ->get(['investisseurs.identifiant_externe', 'comptes_investissement.categorie', 'ecritures_compte_financier.type_ecriture', 'ecritures_compte_financier.date_ecriture', 'ecritures_compte_financier.montant'])
                ->map(fn ($e) => strtoupper($e->identifiant_externe) . '|' . $e->categorie . '|' . $e->type_ecriture . '|' . substr((string) $e->date_ecriture, 0, 10) . '|' . number_format((float) $e->montant, 2, '.', ''))
                ->flip()
                ->all();
        }

        return $reference;
    }

    protected function analyserLigne(string $type, int $numero, array $valeurs, array $reference, array &$vuesDansLeFichier): array
    {
        $donnees = [];
        foreach (array_keys(static::schema($type)) as $colonne) {
            $donnees[$colonne] = trim((string) ($valeurs[$colonne] ?? ''));
        }

        $ligne = [
            'numero' => $numero,
            'statut' => 'valide',
            'messages' => [],
            'donnees' => $donnees,
        ];

        return match ($type) {
            'gestionnaires' => $this->analyserGestionnaire($ligne, $reference, $vuesDansLeFichier),
            'investisseurs' => $this->analyserInvestisseur($ligne, $reference, $vuesDansLeFichier),
            'achats' => $this->analyserAchat($ligne, $reference, $vuesDansLeFichier),
            'ecritures' => $this->analyserEcriture($ligne, $reference, $vuesDansLeFichier),
        };
    }

    protected function analyserGestionnaire(array $ligne, array $reference, array &$vus): array
    {
        $d = &$ligne['donnees'];

        $erreurs = $this->valider($d, [
            'nom' => 'required|string|max:150',
            'prenom' => 'nullable|string|max:150',
            'email' => 'required|email|max:190',
            'telephone' => 'nullable|string|max:30',
        ]);

        if ($erreurs) {
            return $this->enErreur($ligne, $erreurs);
        }

        $email = strtolower($d['email']);
        $d['email'] = $email;

        // Voir App\Support\Telephone : "+221771234500", "00221771234500" et "771234500"
        // doivent être reconnus comme le même numéro, pas trois lignes différentes.
        // Un numéro étranger dont l'indicatif n'est pas reconnaissable n'est jamais
        // accepté en silence — le fichier doit préciser l'indicatif (ex: +33...).
        if (Telephone::estAmbigu($d['telephone'])) {
            return $this->enErreur($ligne, "Téléphone « {$d['telephone']} » : numéro étranger, précisez l'indicatif pays devant (ex : +33 pour la France, +221 pour le Sénégal).");
        }
        $d['telephone'] = (string) (Telephone::normaliser($d['telephone']) ?? '');

        if (isset($reference['emails'][$email])) {
            return $this->enDoublon($ligne, 'Un compte utilisateur existe déjà avec cet email.');
        }

        if (isset($vus[$email])) {
            return $this->enDoublon($ligne, "Email déjà présent ligne {$vus[$email]} du fichier.");
        }

        $vus[$email] = $ligne['numero'];

        return $ligne;
    }

    protected function analyserInvestisseur(array $ligne, array $reference, array &$vus): array
    {
        $d = &$ligne['donnees'];

        // Une personne morale peut n'avoir qu'une raison sociale : elle tient lieu de nom.
        if ($d['nom'] === '' && $d['raison_sociale'] !== '') {
            $d['nom'] = $d['raison_sociale'];
        }

        $erreurs = $this->valider($d, [
            'identifiant_externe' => 'nullable|string|max:20',
            'nom' => 'required|string|max:150',
            'prenom' => 'nullable|string|max:150',
            'telephone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:190',
            'gestionnaire_email' => 'nullable|email|max:190',
            'raison_sociale' => 'nullable|string|max:255',
            'notes_internes' => 'nullable|string|max:2000',
        ]);

        if ($erreurs) {
            return $this->enErreur($ligne, $erreurs);
        }

        // Voir App\Support\Telephone : uniformise "+221...", "00221..." et le format
        // local. Un numéro étranger dont l'indicatif n'est pas reconnaissable n'est
        // jamais accepté en silence — le fichier doit préciser l'indicatif (ex: +33...).
        if (Telephone::estAmbigu($d['telephone'])) {
            return $this->enErreur($ligne, "Téléphone « {$d['telephone']} » : numéro étranger, précisez l'indicatif pays devant (ex : +33 pour la France, +221 pour le Sénégal).");
        }
        $d['telephone'] = (string) (Telephone::normaliser($d['telephone']) ?? '');

        $messages = [];

        // Type de personne
        if ($d['type_personne'] === '') {
            $d['type_personne'] = $d['raison_sociale'] !== '' ? 'morale' : 'physique';
        } else {
            $choix = $this->normaliserChoix($d['type_personne'], static::TYPES_PERSONNE);
            if ($choix === null) {
                return $this->enErreur($ligne, ["Type de personne « {$d['type_personne']} » non reconnu (attendu : physique ou morale)."]);
            }
            $d['type_personne'] = $choix;
        }

        // Statut
        if ($d['statut'] === '') {
            $d['statut'] = 'actif';
        } else {
            $statut = strtolower(LecteurTableur::sansAccents($d['statut']));
            if (in_array($statut, ['decede', 'decedee', 'deces'], true)) {
                return $this->enErreur($ligne, ['Un décès ne peut pas être importé : déclarez-le depuis la fiche de l\'investisseur (acte de décès obligatoire).']);
            }
            if (! in_array($statut, ['actif', 'inactif'], true)) {
                return $this->enErreur($ligne, ["Statut « {$d['statut']} » non reconnu (attendu : actif ou inactif)."]);
            }
            $d['statut'] = $statut;
        }

        // Dates
        foreach (['date_naissance', 'date_delivrance_piece', 'date_expiration_piece', 'date_signature_convention'] as $champ) {
            if ($d[$champ] === '') {
                continue;
            }
            $date = $this->normaliserDate($d[$champ]);
            if ($date === null) {
                return $this->enErreur($ligne, [
                    sprintf('%s : « %s » n\'est pas une date lisible (formats acceptés : 31/12/2024 ou 2024-12-31).', static::schema('investisseurs')[$champ]['libelle'], $d[$champ]),
                ]);
            }
            $d[$champ] = $date;
        }

        // Gestionnaire de rattachement
        $d['gestionnaire_id'] = null;
        if ($d['gestionnaire_email'] !== '') {
            $email = strtolower($d['gestionnaire_email']);
            if (! isset($reference['gestionnaires'][$email])) {
                return $this->enErreur($ligne, ["Aucun gestionnaire ne correspond à l'email « {$d['gestionnaire_email']} ». Importez d'abord les gestionnaires."]);
            }
            $d['gestionnaire_id'] = $reference['gestionnaires'][$email]['id'];
            if (! $reference['gestionnaires'][$email]['actif']) {
                $messages[] = 'Le gestionnaire rattaché est désactivé.';
            }
        }

        // Identifiant
        if ($d['identifiant_externe'] !== '') {
            $identifiant = strtoupper($d['identifiant_externe']);
            $d['identifiant_externe'] = $identifiant;

            if (isset($reference['identifiants'][$identifiant])) {
                return $this->enDoublon($ligne, "L'identifiant {$identifiant} existe déjà en base.");
            }

            if (isset($vus[$identifiant])) {
                return $this->enDoublon($ligne, "Identifiant {$identifiant} déjà présent ligne {$vus[$identifiant]} du fichier.");
            }

            $vus[$identifiant] = $ligne['numero'];
        } else {
            $messages[] = 'Identifiant généré automatiquement à l\'enregistrement.';
        }

        $ligne['messages'] = $messages;

        return $ligne;
    }

    protected function analyserAchat(array $ligne, array $reference, array &$vus): array
    {
        $d = &$ligne['donnees'];
        $messages = [];

        $erreurs = $this->valider($d, [
            'identifiant_externe' => 'required|string|max:20',
            'categorie' => 'required|string',
            'date_achat' => 'required|string',
            'nombre_actions' => 'required|string',
            'mode_paiement' => 'nullable|string|max:50',
            'reference_facture' => 'nullable|string|max:100',
            'numero_achat' => 'nullable|string|max:30',
            'observations' => 'nullable|string|max:1000',
        ]);

        if ($erreurs) {
            return $this->enErreur($ligne, $erreurs);
        }

        // Investisseur
        $identifiant = strtoupper($d['identifiant_externe']);
        $d['identifiant_externe'] = $identifiant;

        if (! isset($reference['investisseurs'][$identifiant])) {
            return $this->enErreur($ligne, ["Aucun investisseur ne porte l'identifiant {$identifiant}. Importez d'abord les investisseurs."]);
        }

        $investisseur = $reference['investisseurs'][$identifiant];

        if ($investisseur['statut'] === 'decede') {
            return $this->enErreur($ligne, "{$investisseur['nom']} est déclaré décédé : son compte est gelé, aucun achat ne peut y être ajouté.");
        }

        $d['investisseur_id'] = $investisseur['id'];
        $d['investisseur_nom'] = $investisseur['nom'];

        // Catégorie
        $categorie = $this->normaliserChoix($d['categorie'], static::CATEGORIES);
        if ($categorie === null) {
            return $this->enErreur($ligne, ["Catégorie « {$d['categorie']} » non reconnue (attendu : commercial ou waqf)."]);
        }
        $d['categorie'] = $categorie;

        // Type d'achat
        if ($d['type_achat'] === '') {
            $d['type_achat'] = 'initial';
        } else {
            $typeAchat = $this->normaliserChoix($d['type_achat'], static::TYPES_ACHAT);
            if ($typeAchat === null) {
                return $this->enErreur($ligne, ["Type d'achat « {$d['type_achat']} » non reconnu (attendu : initial, rajout, complement ou benefice)."]);
            }
            $d['type_achat'] = $typeAchat;
        }

        // Date
        $date = $this->normaliserDate($d['date_achat']);
        if ($date === null) {
            return $this->enErreur($ligne, ["Date d'achat « {$d['date_achat']} » illisible (formats acceptés : 31/12/2024 ou 2024-12-31)."]);
        }
        if ($date > now()->toDateString()) {
            return $this->enErreur($ligne, "Date d'achat dans le futur ({$date}).");
        }
        $d['date_achat'] = $date;

        // Quantité
        $nombreActions = $this->normaliserNombre($d['nombre_actions']);
        if ($nombreActions === null || $nombreActions < 1 || floor($nombreActions) != $nombreActions) {
            return $this->enErreur($ligne, ["Nombre d'actions « {$d['nombre_actions']} » invalide : un entier supérieur ou égal à 1 est attendu."]);
        }
        $d['nombre_actions'] = (int) $nombreActions;

        // Prix unitaire
        if ($d['prix_unitaire'] === '') {
            $prix = (float) ($reference['prix'][$categorie] ?? 0);
            if ($prix <= 0) {
                return $this->enErreur($ligne, "Aucun prix unitaire dans le fichier et aucune politique d'investissement définie pour la catégorie {$categorie}.");
            }
            $messages[] = 'Prix unitaire repris de la politique : ' . $this->formaterMontant($prix) . '.';
        } else {
            $prix = $this->normaliserNombre($d['prix_unitaire']);
            if ($prix === null || $prix <= 0) {
                return $this->enErreur($ligne, "Prix unitaire « {$d['prix_unitaire']} » invalide.");
            }
        }
        $d['prix_unitaire'] = $prix;

        // Montant
        $montantAttendu = round($d['nombre_actions'] * $prix, 2);
        if ($d['montant'] === '') {
            $d['montant'] = $montantAttendu;
        } else {
            $montant = $this->normaliserNombre($d['montant']);
            if ($montant === null) {
                return $this->enErreur($ligne, "Montant « {$d['montant']} » illisible.");
            }
            if (abs($montant - $montantAttendu) > 0.01) {
                return $this->enErreur($ligne, sprintf(
                    'Montant incohérent : %s dans le fichier, %s attendu (%d × %s).',
                    $this->formaterMontant($montant),
                    $this->formaterMontant($montantAttendu),
                    $d['nombre_actions'],
                    $this->formaterMontant($prix)
                ));
            }
            $d['montant'] = $montantAttendu;
        }

        // Réinvestissement automatique
        if ($d['reinvestissement_auto'] !== '') {
            $booleen = $this->normaliserBooleen($d['reinvestissement_auto']);
            if ($booleen === null) {
                return $this->enErreur($ligne, "Réinvestissement automatique « {$d['reinvestissement_auto']} » non reconnu (attendu : oui ou non).");
            }
            $d['reinvestissement_auto'] = $booleen;
        } else {
            $d['reinvestissement_auto'] = null;
        }

        // Numéro d'achat fourni : doit rester unique
        if ($d['numero_achat'] !== '') {
            if (isset($reference['numeros_achat'][$d['numero_achat']])) {
                return $this->enDoublon($ligne, "Le numéro d'achat {$d['numero_achat']} existe déjà en base.");
            }
            if (isset($vus['numero:' . $d['numero_achat']])) {
                return $this->enDoublon($ligne, "Numéro d'achat {$d['numero_achat']} déjà présent ligne " . $vus['numero:' . $d['numero_achat']] . ' du fichier.');
            }
            $vus['numero:' . $d['numero_achat']] = $ligne['numero'];
        }

        // Même investisseur, même compte, même date, même quantité, même montant : déjà importé.
        $signature = $identifiant . '|' . $categorie . '|' . $date . '|' . $d['nombre_actions'] . '|' . number_format((float) $d['montant'], 2, '.', '');

        if (isset($reference['achats_existants'][$signature])) {
            return $this->enDoublon($ligne, 'Un achat identique (même compte, date, quantité et montant) existe déjà en base.');
        }

        if (isset($vus['achat:' . $signature])) {
            return $this->enDoublon($ligne, 'Achat identique à la ligne ' . $vus['achat:' . $signature] . ' du fichier.');
        }

        $vus['achat:' . $signature] = $ligne['numero'];

        $ligne['messages'] = $messages;

        return $ligne;
    }

    protected function analyserEcriture(array $ligne, array $reference, array &$vus): array
    {
        $d = &$ligne['donnees'];
        $messages = [];

        $erreurs = $this->valider($d, [
            'identifiant_externe' => 'required|string|max:20',
            'categorie' => 'required|string',
            'type_ecriture' => 'required|string',
            'montant' => 'required|string',
            'date_ecriture' => 'required|string',
            'observations' => 'nullable|string|max:1000',
        ]);

        if ($erreurs) {
            return $this->enErreur($ligne, $erreurs);
        }

        $identifiant = strtoupper($d['identifiant_externe']);
        $d['identifiant_externe'] = $identifiant;

        if (! isset($reference['investisseurs'][$identifiant])) {
            return $this->enErreur($ligne, ["Aucun investisseur ne porte l'identifiant {$identifiant}. Importez d'abord les investisseurs."]);
        }

        $investisseur = $reference['investisseurs'][$identifiant];

        if ($investisseur['statut'] === 'decede') {
            return $this->enErreur($ligne, "{$investisseur['nom']} est déclaré décédé : son compte est gelé, aucune écriture ne peut y être ajoutée.");
        }

        $d['investisseur_id'] = $investisseur['id'];
        $d['investisseur_nom'] = $investisseur['nom'];

        $categorie = $this->normaliserChoix($d['categorie'], static::CATEGORIES);
        if ($categorie === null) {
            return $this->enErreur($ligne, ["Catégorie « {$d['categorie']} » non reconnue (attendu : commercial ou waqf)."]);
        }
        $d['categorie'] = $categorie;

        $typeEcriture = $this->normaliserChoix($d['type_ecriture'], static::TYPES_ECRITURE);
        if ($typeEcriture === null) {
            return $this->enErreur($ligne, ["Type d'écriture « {$d['type_ecriture']} » non reconnu (attendu : dividende, versement_complementaire, achat_action, paiement, radiation ou ajustement)."]);
        }
        $d['type_ecriture'] = $typeEcriture;

        $date = $this->normaliserDate($d['date_ecriture']);
        if ($date === null) {
            return $this->enErreur($ligne, "Date « {$d['date_ecriture']} » illisible (formats acceptés : 31/12/2024 ou 2024-12-31).");
        }
        if ($date > now()->toDateString()) {
            return $this->enErreur($ligne, "Date dans le futur ({$date}).");
        }
        $d['date_ecriture'] = $date;

        $montant = $this->normaliserNombre($d['montant']);
        if ($montant === null) {
            return $this->enErreur($ligne, "Montant « {$d['montant']} » illisible.");
        }
        if (abs($montant) < 0.01) {
            return $this->enErreur($ligne, 'Le montant ne peut pas être nul.');
        }

        // Le sens (crédit/débit) est imposé par le type : on corrige et on le signale
        // dans le tableau de contrôle, avant validation par l'utilisateur.
        $sens = static::SENS_ECRITURE[$typeEcriture];

        if ($sens === 'credit' && $montant < 0) {
            $montant = abs($montant);
            $messages[] = 'Un ' . str_replace('_', ' ', $typeEcriture) . ' est un crédit : le montant sera enregistré en +' . $this->formaterMontant($montant) . '.';
        }

        if ($sens === 'debit' && $montant > 0) {
            $montant = -$montant;
            $messages[] = 'Un ' . str_replace('_', ' ', $typeEcriture) . ' est un débit : le montant sera enregistré en ' . $this->formaterMontant($montant) . '.';
        }

        $d['montant'] = round($montant, 2);

        $signature = $identifiant . '|' . $categorie . '|' . $typeEcriture . '|' . $date . '|' . number_format((float) $d['montant'], 2, '.', '');

        if (isset($reference['ecritures_existantes'][$signature])) {
            return $this->enDoublon($ligne, 'Une écriture identique (même compte, type, date et montant) existe déjà en base.');
        }

        if (isset($vus['ecriture:' . $signature])) {
            return $this->enDoublon($ligne, 'Écriture identique à la ligne ' . $vus['ecriture:' . $signature] . ' du fichier.');
        }

        $vus['ecriture:' . $signature] = $ligne['numero'];

        // Solde déroulé dans l'ordre du fichier : un solde négatif révèle presque toujours
        // une ligne manquante ou un signe inversé, sans pour autant empêcher l'import
        // (le crédit correspondant peut arriver dans un fichier suivant).
        $cleSolde = 'solde:' . $identifiant . '|' . $categorie;
        $soldeCourant = $vus[$cleSolde] ?? ($reference['soldes'][$identifiant . '|' . $categorie] ?? 0.0);
        $soldeCourant = round($soldeCourant + (float) $d['montant'], 2);
        $vus[$cleSolde] = $soldeCourant;

        if ($soldeCourant < 0) {
            $messages[] = 'Attention : le solde du compte passe à ' . $this->formaterMontant($soldeCourant) . ' après cette écriture.';
        }

        $ligne['messages'] = $messages;

        return $ligne;
    }

    // ------------------------------------------------------------ Import

    /**
     * Enregistre les lignes valides. À appeler dans une transaction (voir le composant
     * Livewire) : une erreur inattendue en cours de route ne doit rien laisser derrière.
     *
     * @param  array  $lignes  résultat d'analyser()
     * @return array{importees: int, ignorees: int, identifiants: array, mots_de_passe: array}
     */
    public function importer(string $type, array $lignes): array
    {
        $importees = 0;
        $ignorees = 0;
        $identifiants = [];
        $motsDePasse = [];

        foreach ($lignes as $ligne) {
            if ($ligne['statut'] !== 'valide') {
                $ignorees++;
                continue;
            }

            $resultat = match ($type) {
                'gestionnaires' => $this->importerGestionnaire($ligne['donnees']),
                'investisseurs' => $this->importerInvestisseur($ligne['donnees']),
                'achats' => $this->importerAchat($ligne['donnees']),
                'ecritures' => $this->importerEcriture($ligne['donnees']),
            };

            $importees++;

            if (isset($resultat['identifiant'])) {
                $identifiants[] = $resultat['identifiant'];
            }

            if (isset($resultat['mot_de_passe'])) {
                $motsDePasse[] = $resultat['mot_de_passe'];
            }
        }

        return [
            'importees' => $importees,
            'ignorees' => $ignorees,
            'identifiants' => $identifiants,
            'mots_de_passe' => $motsDePasse,
        ];
    }

    /**
     * Aucun email n'est envoyé ici : un import crée potentiellement des dizaines de
     * comptes d'un coup. Le mot de passe temporaire est affiché à l'écran, et le bouton
     * « Réinitialiser le mot de passe » de la page Gestionnaires envoie l'email au cas par cas.
     */
    protected function importerGestionnaire(array $d): array
    {
        $motDePasse = MotDePasseTemporaire::generer();

        $user = User::create([
            'nom' => $d['nom'],
            'prenom' => $d['prenom'] ?: '',
            'email' => $d['email'],
            'telephone' => $d['telephone'] ?: null,
            'password' => Hash::make($motDePasse),
            'role' => 'gestionnaire',
            'actif' => true,
            'doit_changer_mot_de_passe' => true,
        ]);

        Gestionnaire::create(['user_id' => $user->id, 'actif' => true]);

        return [
            'identifiant' => $d['email'],
            'mot_de_passe' => [
                'nom' => trim($d['nom'] . ' ' . $d['prenom']),
                'email' => $d['email'],
                'mot_de_passe' => $motDePasse,
            ],
        ];
    }

    protected function importerInvestisseur(array $d): array
    {
        $identifiant = $d['identifiant_externe'] !== '' ? $d['identifiant_externe'] : Investisseur::prochainIdentifiant();

        $investisseur = Investisseur::create([
            'identifiant_externe' => $identifiant,
            'type_personne' => $d['type_personne'],
            'nom' => $d['nom'],
            'prenom' => $d['prenom'] ?: null,
            'telephone' => $d['telephone'] ?: null,
            'email' => $d['email'] ?: null,
            'type_identification' => $d['type_identification'] ?: null,
            'numero_identification' => $d['numero_identification'] ?: null,
            'date_delivrance_piece' => $d['date_delivrance_piece'] ?: null,
            'lieu_delivrance_piece' => $d['lieu_delivrance_piece'] ?: null,
            'date_expiration_piece' => $d['date_expiration_piece'] ?: null,
            'pays' => $d['pays'] ?: null,
            'adresse' => $d['adresse'] ?: null,
            'ville' => $d['ville'] ?: null,
            'date_naissance' => $d['date_naissance'] ?: null,
            'lieu_naissance' => $d['lieu_naissance'] ?: null,
            'nationalite' => $d['nationalite'] ?: null,
            'raison_sociale' => $d['raison_sociale'] ?: null,
            'rccm' => $d['rccm'] ?: null,
            'ninea' => $d['ninea'] ?: null,
            'representant_legal_nom' => $d['representant_legal_nom'] ?: null,
            'representant_legal_telephone' => $d['representant_legal_telephone'] ?: null,
            'beneficiaire_nom' => $d['beneficiaire_nom'] ?: null,
            'beneficiaire_lien' => $d['beneficiaire_lien'] ?: null,
            'beneficiaire_telephone' => $d['beneficiaire_telephone'] ?: null,
            'date_signature_convention' => $d['date_signature_convention'] ?: null,
            'notes_internes' => $d['notes_internes'] ?: null,
            'gestionnaire_id' => $d['gestionnaire_id'] ?: null,
            'statut' => $d['statut'],
        ]);

        return ['identifiant' => $investisseur->identifiant_externe];
    }

    protected function importerAchat(array $d): array
    {
        $investisseur = Investisseur::findOrFail($d['investisseur_id']);
        $compte = $investisseur->compteOuCree($d['categorie']);

        // Même règle qu'une saisie manuelle : un achat antérieur à l'ouverture connue
        // recale la date d'ouverture, sans quoi le rattrapage des dividendes l'ignorerait.
        if ($d['date_achat'] < $compte->date_ouverture->toDateString()) {
            $compte->update(['date_ouverture' => $d['date_achat']]);
        }

        if ($d['reinvestissement_auto'] !== null && $compte->reinvestissement_auto !== $d['reinvestissement_auto']) {
            $compte->update(['reinvestissement_auto' => $d['reinvestissement_auto']]);
        }

        $achat = $compte->achats()->create([
            'numero_achat' => $d['numero_achat'] !== '' ? $d['numero_achat'] : $this->prochainNumeroAchat(),
            'date_achat' => $d['date_achat'],
            'type_achat' => $d['type_achat'],
            'nombre_actions' => $d['nombre_actions'],
            'prix_unitaire' => $d['prix_unitaire'],
            'montant' => $d['montant'],
            'mode_paiement' => $d['mode_paiement'] ?: null,
            'reference_facture' => $d['reference_facture'] ?: null,
            'observations' => $d['observations'] ?: null,
            'saisi_par' => Auth::id(),
        ]);

        return ['identifiant' => $achat->numero_achat];
    }

    protected function importerEcriture(array $d): array
    {
        $investisseur = Investisseur::findOrFail($d['investisseur_id']);
        $compte = $investisseur->compteOuCree($d['categorie']);

        if ($d['date_ecriture'] < $compte->date_ouverture->toDateString()) {
            $compte->update(['date_ouverture' => $d['date_ecriture']]);
        }

        // ajouterEcriture() recalcule le solde à partir de la dernière écriture :
        // l'ordre des lignes du fichier est donc l'ordre du grand livre.
        $ecriture = $compte->ajouterEcriture(
            type: $d['type_ecriture'],
            montant: (float) $d['montant'],
            dateEcriture: $d['date_ecriture'],
            observations: $d['observations'] ?: 'Reprise de données (import)',
            userId: Auth::id(),
        );

        return ['identifiant' => $compte->numero_compte . ' — ' . $this->formaterMontant((float) $ecriture->montant)];
    }

    protected function prochainNumeroAchat(): string
    {
        do {
            $numero = 'IMP-' . str_pad((string) (AchatAction::max('id') + 1), 5, '0', STR_PAD_LEFT);
            $existe = AchatAction::where('numero_achat', $numero)->exists();

            if ($existe) {
                // Un numéro IMP déjà pris signifie que la séquence des id a été rejouée :
                // on bascule sur un suffixe aléatoire plutôt que de boucler indéfiniment.
                $numero = 'IMP-' . str_pad((string) (AchatAction::max('id') + 1), 5, '0', STR_PAD_LEFT) . '-' . random_int(100, 999);
                $existe = AchatAction::where('numero_achat', $numero)->exists();
            }
        } while ($existe);

        return $numero;
    }

    // ------------------------------------------------------------ Outils

    /** @return array<int, string> messages d'erreur en français, vides si la ligne passe */
    protected function valider(array $donnees, array $regles): array
    {
        $libelles = [];
        foreach (array_keys($regles) as $champ) {
            $libelles[$champ] = mb_strtolower($this->libelleColonne($champ));
        }

        $validateur = Validator::make($donnees, $regles, [], $libelles);

        return $validateur->fails() ? array_values($validateur->errors()->all()) : [];
    }

    protected function libelleColonne(string $colonne): string
    {
        foreach (array_keys(static::TYPES) as $type) {
            $schema = static::schema($type);
            if (isset($schema[$colonne])) {
                return $schema[$colonne]['libelle'];
            }
        }

        return $colonne;
    }

    protected function enErreur(array $ligne, array|string $messages): array
    {
        $ligne['statut'] = 'erreur';
        $ligne['messages'] = array_merge($ligne['messages'], (array) $messages);

        return $ligne;
    }

    protected function enDoublon(array $ligne, string $message): array
    {
        $ligne['statut'] = 'doublon';
        $ligne['messages'][] = $message;

        return $ligne;
    }

    /**
     * Reconnaît la valeur d'une liste fermée quelle que soit la casse, les accents
     * ou la formulation employée dans le fichier d'origine.
     */
    protected function normaliserChoix(string $valeur, array $options): ?string
    {
        $recherche = preg_replace('/[^a-z0-9]+/', ' ', strtolower(LecteurTableur::sansAccents(trim($valeur))));
        $recherche = trim((string) $recherche);

        foreach ($options as $cle => $alias) {
            foreach ($alias as $variante) {
                $variante = trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower(LecteurTableur::sansAccents($variante))));
                if ($recherche === $variante) {
                    return $cle;
                }
            }
        }

        return null;
    }

    protected function normaliserBooleen(string $valeur): ?bool
    {
        $valeur = strtolower(LecteurTableur::sansAccents(trim($valeur)));

        if (in_array($valeur, ['oui', 'o', 'yes', 'y', '1', 'vrai', 'true', 'x', 'actif'], true)) {
            return true;
        }

        if (in_array($valeur, ['non', 'n', 'no', '0', 'faux', 'false', 'inactif'], true)) {
            return false;
        }

        return null;
    }

    /**
     * Convention francophone : l'espace (y compris insécable) sépare les milliers,
     * la virgule est le séparateur décimal. « 1 250 000,50 » et « 1250000.50 » sont
     * donc lus de la même façon.
     */
    protected function normaliserNombre(string $valeur): ?float
    {
        $valeur = trim($valeur);

        if ($valeur === '') {
            return null;
        }

        $valeur = str_replace(["\xc2\xa0", "\xe2\x80\xaf", ' ', "'"], '', $valeur);
        $valeur = preg_replace('/(CFA|FCFA|XOF|F)$/i', '', $valeur);
        $valeur = trim((string) $valeur);

        // Les deux séparateurs présents : le dernier rencontré porte les décimales.
        if (str_contains($valeur, ',') && str_contains($valeur, '.')) {
            $valeur = strrpos($valeur, ',') > strrpos($valeur, '.')
                ? str_replace(['.', ','], ['', '.'], $valeur)
                : str_replace(',', '', $valeur);
        } else {
            $valeur = str_replace(',', '.', $valeur);
        }

        if (! is_numeric($valeur)) {
            return null;
        }

        return (float) $valeur;
    }

    /**
     * Accepte ce que produisent Excel et les saisies manuelles : 31/12/2024,
     * 31-12-2024, 2024-12-31, ou un numéro de série déjà converti par le lecteur.
     */
    protected function normaliserDate(string $valeur): ?string
    {
        $valeur = trim($valeur);

        if ($valeur === '') {
            return null;
        }

        // Un numéro de série brut (colonne non formatée en date dans Excel).
        if (preg_match('/^\d{5}(\.\d+)?$/', $valeur)) {
            return (new \DateTimeImmutable('1899-12-30'))
                ->modify('+' . (int) floor((float) $valeur) . ' days')
                ->format('Y-m-d');
        }

        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d', 'd/m/y', 'Y-m-d H:i:s', 'd/m/Y H:i'];

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $valeur);
            $erreurs = \DateTimeImmutable::getLastErrors();

            if ($date !== false && empty($erreurs['warning_count']) && empty($erreurs['error_count'])) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    protected function formaterMontant(float $montant): string
    {
        return \App\Support\Montant::avecDevise($montant);
    }
}
