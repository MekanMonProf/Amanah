<?php

namespace Tests\Feature;

use App\Livewire\Achats\AchatModifier;
use App\Models\AchatAction;
use App\Models\Gestionnaire;
use App\Models\Investisseur;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La correction d'un achat : ses mentions, et rien de plus.
 *
 * Un achat était jusqu'ici définitif dans ses moindres détails — y compris le
 * mode de paiement mal choisi et la référence de facture mal recopiée, qu'il
 * fallait laisser faux. Cet écran les rouvre. Ce que ces tests surveillent est
 * l'autre moitié de la règle : que les actions, le prix, la date et le montant
 * restent hors d'atteinte, même pour qui s'adresse directement au composant
 * sans passer par le formulaire.
 */
class AchatCorrectionTest extends TestCase
{
    /** Les achats créés par Livewire::test échappent à la transaction : on les retient pour les effacer. */
    private array $aEffacer = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.database' => 'amanah_repetition',
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => '',
            'cache.default' => 'array',
        ]);
        DB::purge('mysql');

        // Le disque privé est détourné : sans cela, la facture d'essai
        // atterrirait dans storage/app/private avec les vraies.
        Storage::fake('local');

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();

        // Livewire::test ouvre ses propres connexions : ce que les composants
        // ont écrit a survécu au rollBack ci-dessus. L'ordre compte — les clés
        // étrangères sont en RESTRICT, un dossier ne s'effface pas avant ses
        // comptes.
        $ordre = ['audit_logs', 'achats_actions', 'comptes_investissement', 'investisseurs', 'gestionnaires', 'users'];

        foreach ($ordre as $table) {
            if ($ids = $this->aEffacer[$table] ?? null) {
                DB::table($table)->whereIn('id', $ids)->delete();
            }
        }

        parent::tearDown();
    }

    public function test_le_mode_de_paiement_et_la_reference_se_corrigent(): void
    {
        [$achat, $personnel] = $this->achatOrdinaire();

        Livewire::actingAs($personnel)
            ->test(AchatModifier::class, ['achat' => $achat])
            ->set('mode_paiement', 'Virement bancaire')
            ->set('reference_facture', 'FAC-2026-118')
            ->set('observations', 'Chèque remis en main propre, encaissé le 12.')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $relu = AchatAction::findOrFail($achat->id);

        $this->assertSame('Virement bancaire', $relu->mode_paiement);
        $this->assertSame('FAC-2026-118', $relu->reference_facture);
        $this->assertSame('Chèque remis en main propre, encaissé le 12.', $relu->observations);
    }

    public function test_les_montants_restent_hors_d_atteinte(): void
    {
        [$achat, $personnel] = $this->achatOrdinaire();

        // L'attaque telle qu'elle se présenterait : le composant est appelé
        // directement, avec les champs qu'on voudrait voir changer. Livewire
        // refuse de poser une propriété qui n'existe pas sur le composant.
        $essai = Livewire::actingAs($personnel)->test(AchatModifier::class, ['achat' => $achat]);

        foreach (['nombre_actions' => 999, 'prix_unitaire' => 1, 'montant' => 999, 'date_achat' => '2020-01-01', 'type_achat' => 'benefice'] as $champ => $valeur) {
            try {
                $essai->set($champ, $valeur);
                $this->fail("Le composant a accepté de poser « {$champ} ».");
            } catch (\Throwable $refus) {
                $this->assertStringContainsString($champ, $refus->getMessage());
            }
        }

        $essai->call('enregistrer')->assertHasNoErrors();

        $relu = AchatAction::findOrFail($achat->id);

        $this->assertSame(4, (int) $relu->nombre_actions);
        $this->assertSame(25000.0, (float) $relu->prix_unitaire);
        $this->assertSame(100000.0, (float) $relu->montant);
        $this->assertSame('2026-02-14', $relu->date_achat->toDateString());
        $this->assertSame('initial', $relu->type_achat);
    }

    public function test_une_observation_ecrite_par_la_plateforme_ne_se_recrit_pas(): void
    {
        [$achat, $personnel] = $this->achatOrdinaire([
            'observation_cle' => \App\Support\Observation::ACHAT_SUR_SOLDE,
            'observations' => \App\Support\Observation::francais(\App\Support\Observation::ACHAT_SUR_SOLDE),
        ]);

        Livewire::actingAs($personnel)
            ->test(AchatModifier::class, ['achat' => $achat])
            ->assertSet('observationFigee', true)
            ->set('observations', 'Texte substitué')
            ->call('enregistrer')
            ->assertHasNoErrors();

        // La phrase d'origine est la trace d'un acte de l'application : elle
        // reste, et c'est elle que l'écran continue d'afficher.
        $this->assertSame(
            \App\Support\Observation::francais(\App\Support\Observation::ACHAT_SUR_SOLDE),
            AchatAction::findOrFail($achat->id)->observations,
        );
    }

    public function test_une_observation_saisie_reste_modifiable(): void
    {
        [$achat, $personnel] = $this->achatOrdinaire(['observations' => 'Montant arrondi par erreur']);

        Livewire::actingAs($personnel)
            ->test(AchatModifier::class, ['achat' => $achat])
            ->assertSet('observationFigee', false)
            ->set('observations', 'Montant exact, voir facture FAC-2026-118')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame(
            'Montant exact, voir facture FAC-2026-118',
            AchatAction::findOrFail($achat->id)->observations,
        );
    }

    public function test_la_correction_laisse_une_ligne_au_journal(): void
    {
        [$achat, $personnel] = $this->achatOrdinaire(['mode_paiement' => 'Wave']);

        Livewire::actingAs($personnel)
            ->test(AchatModifier::class, ['achat' => $achat])
            ->set('mode_paiement', 'Chèque')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $trace = DB::table('audit_logs')
            ->where('entite', 'achat')->where('entite_id', $achat->id)->where('action', 'modification')
            ->orderByDesc('id')->first();

        $this->assertNotNull($trace, "La correction n'a rien écrit au journal d'audit.");
        $this->aEffacer['audit_logs'][] = $trace->id;

        $avant = json_decode($trace->donnees_avant, true);
        $apres = json_decode($trace->donnees_apres, true);

        $this->assertSame('Wave', $avant['mode_paiement']);
        $this->assertSame('Chèque', $apres['mode_paiement']);
        // Le numéro situe la ligne pour qui relit le journal ; et seuls les
        // champs qui ont bougé y figurent.
        $this->assertSame($achat->numero_achat, $avant['numero_achat']);
        $this->assertSame(['mode_paiement'], array_keys($apres));
    }

    public function test_une_visite_sans_changement_n_ecrit_rien(): void
    {
        [$achat, $personnel] = $this->achatOrdinaire(['mode_paiement' => 'Wave']);

        $avant = DB::table('audit_logs')->count();

        Livewire::actingAs($personnel)
            ->test(AchatModifier::class, ['achat' => $achat])
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame($avant, DB::table('audit_logs')->count());
    }

    public function test_un_gestionnaire_ne_corrige_pas_hors_de_son_portefeuille(): void
    {
        [$achat] = $this->achatOrdinaire();

        // Un gestionnaire bien réel, avec son propre portefeuille — lequel ne
        // contient pas ce dossier.
        $intrus = $this->utilisateur('correction.intrus@local.test', 'gestionnaire');
        $this->gestionnairePour($intrus);

        $this->assertNotSame(
            $achat->compte->investisseur->gestionnaire_id,
            $intrus->fresh()->gestionnaire->id,
            "Le decor est faux : l'intrus gere justement ce dossier.",
        );

        Livewire::actingAs($intrus->fresh())
            ->test(AchatModifier::class, ['achat' => $achat])
            ->assertForbidden();
    }

    public function test_le_role_lecture_ne_corrige_pas(): void
    {
        [$achat] = $this->achatOrdinaire();

        Livewire::actingAs($this->utilisateur('correction.lecture@local.test', 'lecture'))
            ->test(AchatModifier::class, ['achat' => $achat])
            ->assertForbidden();
    }

    public function test_un_dossier_gele_par_un_deces_reste_ferme(): void
    {
        [$achat, $personnel] = $this->achatOrdinaire();

        $achat->compte->investisseur->update(['statut' => 'decede', 'date_deces' => '2026-07-01']);

        Livewire::actingAs($personnel)
            ->test(AchatModifier::class, ['achat' => $achat->fresh()])
            ->assertForbidden();
    }

    /**
     * Pour un présent, les actions sont au compte du Waqf caritatif, mais
     * l'opération est celle du donateur : c'est son gestionnaire qui corrige.
     */
    public function test_un_present_se_corrige_depuis_le_dossier_du_donateur(): void
    {
        $gestionnaire = $this->utilisateur('correction.donateur@local.test', 'gestionnaire');
        $donateur = $this->investisseur($this->gestionnairePour($gestionnaire)->id);

        // Le compte qui reçoit les actions n'est pas celui du donateur : c'est
        // celui du Waqf caritatif, dont le gestionnaire n'a pas la charge.
        $compteWaqf = $this->compte($this->investisseur(), 'waqf');

        $achat = $this->achat($compteWaqf, [
            'offert_par_investisseur_id' => $donateur->id,
            'type_present' => 'memoire',
            'present_pour' => 'Amadou Dieng',
            'lien_avec_donateur' => 'Pere',
        ]);

        Livewire::actingAs($gestionnaire->fresh())
            ->test(AchatModifier::class, ['achat' => $achat])
            ->set('present_pour', 'Amadou Dieng Sr.')
            ->set('lien_avec_donateur', 'Père')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $relu = AchatAction::findOrFail($achat->id);

        $this->assertSame('Amadou Dieng Sr.', $relu->present_pour);
        $this->assertSame('Père', $relu->lien_avec_donateur);
    }

    public function test_les_mentions_d_un_present_ne_se_posent_pas_sur_un_achat_ordinaire(): void
    {
        [$achat, $personnel] = $this->achatOrdinaire();

        Livewire::actingAs($personnel)
            ->test(AchatModifier::class, ['achat' => $achat])
            ->set('present_pour', 'Quelqu un')
            ->set('lien_avec_donateur', 'Ami')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $relu = AchatAction::findOrFail($achat->id);

        $this->assertNull($relu->present_pour);
        $this->assertNull($relu->lien_avec_donateur);
    }

    public function test_l_ecran_montre_les_faits_figes_et_la_piece_deja_jointe(): void
    {
        [$achat, $personnel] = $this->achatOrdinaire([
            'photo_facture_path' => 'factures-achats/essai-correction.pdf',
        ]);

        Livewire::actingAs($personnel)
            ->test(AchatModifier::class, ['achat' => $achat])
            ->assertSee('Ce qui reste figé')
            // Par le formateur maison : le séparateur des milliers est une
            // espace fine insécable, qu'une chaîne écrite à la main rate.
            ->assertSee(\App\Support\Montant::format(100000), false)
            ->assertSee(\App\Support\Montant::format(4))
            ->assertSee('Pièce actuelle :')
            ->assertSee(\App\Support\Document::lien($achat, 'photo_facture_path'), false);
    }

    public function test_un_mode_de_paiement_inconnu_est_refuse(): void
    {
        [$achat, $personnel] = $this->achatOrdinaire(['mode_paiement' => 'Wave']);

        Livewire::actingAs($personnel)
            ->test(AchatModifier::class, ['achat' => $achat])
            ->set('mode_paiement', 'Bitcoin')
            ->call('enregistrer')
            ->assertHasErrors('mode_paiement');

        $this->assertSame('Wave', AchatAction::findOrFail($achat->id)->mode_paiement);
    }

    /**
     * Le piège de la reprise : 142 des 181 achats de la base de démonstration
     * n'ont aucun mode de paiement. Un simple passage sur l'écran ne doit pas
     * leur en inventer un.
     */
    public function test_un_achat_sans_mode_de_paiement_n_en_recoit_pas_un_au_passage(): void
    {
        [$achat, $personnel] = $this->achatOrdinaire(['mode_paiement' => null]);

        Livewire::actingAs($personnel)
            ->test(AchatModifier::class, ['achat' => $achat])
            ->assertSet('mode_paiement', null)
            ->set('reference_facture', 'FAC-2026-200')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $relu = AchatAction::findOrFail($achat->id);

        $this->assertNull($relu->mode_paiement, "L'écran a inventé un mode de paiement.");
        $this->assertSame('FAC-2026-200', $relu->reference_facture);
    }

    public function test_un_mode_de_paiement_peut_etre_remis_a_inconnu(): void
    {
        [$achat, $personnel] = $this->achatOrdinaire(['mode_paiement' => 'Wave']);

        Livewire::actingAs($personnel)
            ->test(AchatModifier::class, ['achat' => $achat])
            ->set('mode_paiement', '')
            ->call('enregistrer')
            ->assertHasNoErrors();

        // Vide veut dire inconnu : la colonne reçoit null, pas une chaîne vide.
        $this->assertNull(AchatAction::findOrFail($achat->id)->mode_paiement);
    }

    /**
     * L'autre piège : l'historique porte un « virement » en minuscules, hors de
     * la liste de la saisie. Omettre cette valeur du choix la ferait disparaître
     * au premier enregistrement, le navigateur retenant alors la première option.
     */
    public function test_un_mode_de_paiement_herite_est_conserve_et_propose(): void
    {
        [$achat, $personnel] = $this->achatOrdinaire(['mode_paiement' => 'virement']);

        Livewire::actingAs($personnel)
            ->test(AchatModifier::class, ['achat' => $achat])
            ->assertSet('mode_paiement', 'virement')
            ->assertSee('virement')
            ->set('reference_facture', 'FAC-2026-201')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame('virement', AchatAction::findOrFail($achat->id)->mode_paiement);
    }

    public function test_un_mode_herite_ne_s_offre_pas_sur_les_autres_achats(): void
    {
        [$achat, $personnel] = $this->achatOrdinaire(['mode_paiement' => 'Wave']);

        // La tolérance ne vaut que pour la valeur que cet achat porte déjà :
        // elle n'ouvre pas la liste à n'importe quoi.
        Livewire::actingAs($personnel)
            ->test(AchatModifier::class, ['achat' => $achat])
            ->set('mode_paiement', 'virement')
            ->call('enregistrer')
            ->assertHasErrors('mode_paiement');

        $this->assertSame('Wave', AchatAction::findOrFail($achat->id)->mode_paiement);
    }

    // ---- Les pièces du décor ----------------------------------------------

    /** @return array{0: AchatAction, 1: User} */
    private function achatOrdinaire(array $attributs = []): array
    {
        $investisseur = $this->investisseur();
        $compte = $this->compte($investisseur, 'commercial');

        return [
            $this->achat($compte, $attributs),
            $this->utilisateur('correction.achat@local.test', 'administrateur'),
        ];
    }

    /** Un identifiant par dossier d'essai : deux dossiers ne se marchent pas dessus. */
    private int $suivant = 0;

    private function investisseur(?int $gestionnaireId = null): Investisseur
    {
        $identifiant = 'Z97' . str_pad((string) ++$this->suivant, 2, '0', STR_PAD_LEFT);

        $gestionnaireId ??= Gestionnaire::orderBy('id')->firstOrFail()->id;

        $investisseur = Investisseur::create([
            'identifiant_externe' => $identifiant,
            'type_personne' => 'physique',
            'nom' => 'Essai',
            'prenom' => 'Correction',
            'statut' => 'actif',
            'gestionnaire_id' => $gestionnaireId,
        ]);

        $this->aEffacer['investisseurs'][] = $investisseur->id;

        return $investisseur;
    }

    private function compte(Investisseur $investisseur, string $categorie): \App\Models\CompteInvestissement
    {
        $compte = \App\Models\CompteInvestissement::create([
            'investisseur_id' => $investisseur->id,
            'categorie' => $categorie,
            'numero_compte' => 'CORR-' . $investisseur->id . '-' . strtoupper(substr($categorie, 0, 3)),
            'date_ouverture' => '2026-01-05',
        ]);

        $this->aEffacer['comptes_investissement'][] = $compte->id;

        return $compte;
    }

    private function achat(\App\Models\CompteInvestissement $compte, array $attributs = []): AchatAction
    {
        $achat = AchatAction::create(array_merge([
            'compte_id' => $compte->id,
            'numero_achat' => 'ACH-C' . $compte->id,
            'date_achat' => '2026-02-14',
            'type_achat' => 'initial',
            'nombre_actions' => 4,
            'prix_unitaire' => 25000,
            'montant' => 100000,
            'mode_paiement' => 'Wave',
        ], $attributs));

        $this->aEffacer['achats_actions'][] = $achat->id;

        return $achat->load('compte.investisseur', 'offertPar');
    }

    private function utilisateur(string $email, string $role): User
    {
        $utilisateur = User::create([
            'nom' => 'Essai',
            'prenom' => 'Correction',
            'email' => $email,
            'password' => Hash::make('quelquechose'),
            'role' => $role,
            'actif' => true,
            'doit_changer_mot_de_passe' => false,
        ]);

        $this->aEffacer['users'][] = $utilisateur->id;

        return $utilisateur;
    }

    /**
     * Un gestionnaire, au sens de la table : une ligne `gestionnaires` qui
     * pointe vers un compte utilisateur. C'est son id que porte le dossier, et
     * c'est par lui que passe le contrôle de portefeuille.
     */
    private function gestionnairePour(User $utilisateur): Gestionnaire
    {
        $gestionnaire = Gestionnaire::create(['user_id' => $utilisateur->id, 'actif' => true]);

        $this->aEffacer['gestionnaires'][] = $gestionnaire->id;

        return $gestionnaire;
    }
}
