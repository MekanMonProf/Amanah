<?php

namespace Tests\Feature;

use App\Http\Controllers\AttestationController;
use App\Models\AchatAction;
use App\Models\CompteInvestissement;
use App\Models\Gestionnaire;
use App\Models\Investisseur;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Les achats antérieurs sur l'attestation d'achat d'actions.
 *
 * Les actionnaires la demandaient : une souscription se fait en plusieurs
 * fois, et l'attestation d'un versement isolé ne disait rien de l'ensemble.
 *
 * Trois choses peuvent rendre ce récapitulatif faux ou indiscret, et ce sont
 * elles que ces tests surveillent. Le compte du Waqf caritatif porte les
 * présents de tous les donateurs : y lire « les achats du compte » montrerait
 * à chacun les dons des autres. Dix pour cent des comptes ont connu une
 * radiation : additionner les achats y annoncerait des actions qui ne sont
 * plus détenues. Et sur un compte au réinvestissement automatique, le cumul
 * des montants est surtout fait de dividendes que personne n'a versés.
 */
class AttestationHistoriqueTest extends TestCase
{
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
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_les_achats_anterieurs_figurent_sur_l_attestation(): void
    {
        $compte = $this->compte();

        $this->achat($compte, 'ACH-H1', '2026-01-10', 4);
        $this->achat($compte, 'ACH-H2', '2026-02-10', 6);
        $atteste = $this->achat($compte, 'ACH-H3', '2026-03-10', 2);

        $html = $this->rendre($atteste);

        $this->assertStringContainsString('Historique des acquisitions sur ce compte', $html);
        $this->assertStringContainsString('ACH-H1', $html);
        $this->assertStringContainsString('ACH-H2', $html);
        // Le cumul, et non le seul achat attesté.
        $this->assertStringContainsString('Total acquis à cette date', $html);
        $this->assertStringContainsString('>12<', $this->chiffres($html));
    }

    public function test_un_premier_achat_n_affiche_aucun_recapitulatif(): void
    {
        $compte = $this->compte();

        // Un récapitulatif d'une seule ligne répéterait le tableau du dessus.
        $html = $this->rendre($this->achat($compte, 'ACH-H4', '2026-01-10', 4));

        $this->assertStringNotContainsString('Historique des acquisitions', $html);
        $this->assertStringNotContainsString('Total acquis', $html);
    }

    public function test_un_achat_posterieur_n_entre_pas_dans_l_attestation(): void
    {
        $compte = $this->compte();

        $atteste = $this->achat($compte, 'ACH-H5', '2026-01-10', 4);
        $this->achat($compte, 'ACH-H6', '2026-05-10', 50);

        // Une attestation dit ce qui était vrai le jour où elle a été méritée :
        // deux tirages à des mois d'écart doivent se ressembler.
        $html = $this->rendre($atteste);

        $this->assertStringNotContainsString('ACH-H6', $html);
    }

    public function test_un_achat_du_meme_jour_saisi_apres_n_y_entre_pas_non_plus(): void
    {
        $compte = $this->compte();

        $atteste = $this->achat($compte, 'ACH-H7', '2026-01-10', 4);
        $this->achat($compte, 'ACH-H8', '2026-01-10', 9);

        $html = $this->rendre($atteste);

        $this->assertStringContainsString('ACH-H7', $html);
        $this->assertStringNotContainsString('ACH-H8', $html);
    }

    public function test_une_radiation_empeche_de_confondre_acquis_et_detenu(): void
    {
        $compte = $this->compte();

        $this->achat($compte, 'ACH-H9', '2026-01-10', 10);
        $atteste = $this->achat($compte, 'ACH-H10', '2026-02-10', 2);

        $this->radiation($compte, 7, '2026-01-20');

        $html = $this->rendre($atteste);

        // 12 acquises, 5 détenues : l'attestation doit dire les deux, le cumul
        // dans le tableau et le détenu dans la phrase qui suit.
        $this->assertStringContainsString('>12<', $this->chiffres($html));
        $this->assertStringContainsString('radiations ou les dons', $html);
        $this->assertMatchesRegularExpression(
            '/ce compte détenait\s*<strong>5 action\(s\)<\/strong>/u',
            $html,
            "L'attestation ne dit pas combien d'actions restaient détenues.",
        );
    }

    public function test_sans_radiation_ni_don_l_ecart_n_est_pas_mentionne(): void
    {
        $compte = $this->compte();

        $this->achat($compte, 'ACH-H11', '2026-01-10', 10);
        $atteste = $this->achat($compte, 'ACH-H12', '2026-02-10', 2);

        $this->assertStringNotContainsString('ce compte détenait', $this->rendre($atteste));
    }

    public function test_la_part_reinvestie_est_dite_a_part(): void
    {
        $compte = $this->compte();

        $this->achat($compte, 'ACH-H13', '2026-01-10', 10);
        $this->achat($compte, 'ACH-H14', '2026-01-31', 3, typeAchat: 'benefice');
        $atteste = $this->achat($compte, 'ACH-H15', '2026-02-10', 2);

        $html = $this->rendre($atteste);

        $this->assertStringContainsString('proviennent du réinvestissement de dividendes', $html);
        $this->assertStringContainsString('ne correspondent à aucun versement', $html);
    }

    public function test_sans_reinvestissement_la_precision_ne_s_affiche_pas(): void
    {
        $compte = $this->compte();

        $this->achat($compte, 'ACH-H16', '2026-01-10', 10);
        $atteste = $this->achat($compte, 'ACH-H17', '2026-02-10', 2);

        $this->assertStringNotContainsString('réinvestissement de dividendes', $this->rendre($atteste));
    }

    /**
     * Le point le plus sensible : le compte du Waqf caritatif porte les
     * présents de tous les donateurs. Un donateur ne doit y voir que les siens.
     */
    public function test_un_donateur_ne_voit_que_ses_propres_presents(): void
    {
        $waqf = $this->compte($this->investisseur('Z9810', 'Institution'));

        $donateur = $this->investisseur('Z9811', 'Premier');
        $autre = $this->investisseur('Z9812', 'Second');

        $this->achat($waqf, 'ACH-DON1', '2026-01-10', 3, donateur: $donateur);
        $this->achat($waqf, 'ACH-AUTRE', '2026-01-15', 8, donateur: $autre);
        $atteste = $this->achat($waqf, 'ACH-DON2', '2026-02-10', 5, donateur: $donateur);

        $html = $this->rendre($atteste);

        $this->assertStringContainsString('Vos présents à ce jour', $html);
        $this->assertStringContainsString('ACH-DON1', $html);
        $this->assertStringNotContainsString(
            'ACH-AUTRE',
            $html,
            "Le présent d'un autre donateur apparaît sur cette attestation.",
        );
    }

    public function test_le_volume_du_compte_du_waqf_n_est_pas_montre_au_donateur(): void
    {
        $waqf = $this->compte($this->investisseur('Z9813', 'Institution'));
        $donateur = $this->investisseur('Z9814', 'Donateur');

        $this->achat($waqf, 'ACH-DON3', '2026-01-10', 3, donateur: $donateur);
        $atteste = $this->achat($waqf, 'ACH-DON4', '2026-02-10', 5, donateur: $donateur);

        // Le compte institutionnel détient aussi les dons de tous les autres :
        // son volume ne regarde pas celui-ci.
        $this->radiation($waqf, 2, '2026-01-20');

        $this->assertStringNotContainsString('ce compte détenait', $this->rendre($atteste));
    }

    /**
     * Un mois non arrêté est un réinvestissement qui n'a pas encore eu lieu.
     * L'attestation reste exacte, mais incomplète : il faut le dire, sans quoi
     * le porteur cherche des actions qui n'existent pas encore.
     */
    public function test_un_mois_sans_bareme_est_signale(): void
    {
        $compte = $this->compte();

        // La base de répétition s'arrête à août 2026 : septembre n'est pas
        // arrêté, et il précède l'achat attesté.
        $this->achat($compte, 'ACH-H18', '2026-09-05', 4);
        $atteste = $this->achat($compte, 'ACH-H19', '2026-10-10', 2);

        $html = $this->rendre($atteste);

        $this->assertStringContainsString("n'est pas encore arrêté", $html);
        $this->assertStringContainsString('septembre 2026', $html);
        $this->assertStringContainsString('à la date de leur enregistrement', $html);
    }

    public function test_plusieurs_mois_en_attente_sont_tous_nommes(): void
    {
        $compte = $this->compte();

        $this->achat($compte, 'ACH-H20', '2026-09-05', 4);
        $atteste = $this->achat($compte, 'ACH-H21', '2026-11-10', 2);

        $html = $this->rendre($atteste);

        $this->assertStringContainsString('ne sont pas encore arrêtés', $html);
        $this->assertStringContainsString('septembre 2026', $html);
        $this->assertStringContainsString('octobre 2026', $html);
    }

    public function test_des_mois_tous_arretes_ne_declenchent_aucune_alerte(): void
    {
        $compte = $this->compte();

        // Janvier et février 2026 ont leur barème dans la base de répétition.
        $this->achat($compte, 'ACH-H22', '2026-01-10', 4);
        $atteste = $this->achat($compte, 'ACH-H23', '2026-03-10', 2);

        $this->assertStringNotContainsString('pas encore arrêté', $this->rendre($atteste));
    }

    public function test_le_mois_courant_n_est_jamais_annonce_comme_un_manque(): void
    {
        $compte = $this->compte();

        // Deux achats du même mois : le mois de l'achat attesté n'est jamais
        // arrêté au moment où il se produit, l'annoncer serait une fausse alerte.
        $this->achat($compte, 'ACH-H24', '2026-08-02', 4);
        $atteste = $this->achat($compte, 'ACH-H25', '2026-08-20', 2);

        $this->assertStringNotContainsString('pas encore arrêté', $this->rendre($atteste));
    }

    public function test_un_present_ne_parle_pas_de_dividendes(): void
    {
        $waqf = $this->compte($this->investisseur('Z9815', 'Institution'));
        $donateur = $this->investisseur('Z9816', 'Donateur');

        $this->achat($waqf, 'ACH-DON5', '2026-09-05', 3, donateur: $donateur);
        $atteste = $this->achat($waqf, 'ACH-DON6', '2026-10-10', 5, donateur: $donateur);

        // Un présent n'ouvre aucun droit à dividende : la question ne se pose pas.
        $this->assertStringNotContainsString('pas encore arrêté', $this->rendre($atteste));
    }

    // ---- Les pièces du décor ----------------------------------------------

    /** Le HTML que dompdf met en page : on y lit ce que le PDF dira. */
    private function rendre(AchatAction $achat): string
    {
        $achat->load('compte.investisseur', 'offertPar');

        $controleur = new class extends AttestationController
        {
            public function historique(AchatAction $achat): \Illuminate\Support\Collection
            {
                return $this->historiqueDesAchats($achat);
            }

            public function periodes(AchatAction $achat): array
            {
                return $this->periodesSansBareme($achat);
            }
        };

        $historique = $controleur->historique($achat);

        return view('pdf.attestation-achat', [
            'achat' => $achat,
            'compte' => $achat->compte,
            'investisseur' => $achat->compte->investisseur,
            'historique' => $historique,
            'actionsAcquises' => (int) $historique->sum('nombre_actions'),
            'montantCumule' => (float) $historique->sum('montant'),
            'reinvesti' => $historique->filter(fn (AchatAction $ligne) => $ligne->estUnReinvestissement()),
            'actionsDetenues' => $achat->estUnPresent()
                ? null
                : $achat->compte->nombreActionsAu($achat->date_achat->toDateString()),
            'periodesEnAttente' => $controleur->periodes($achat),
            'dateGeneration' => now(),
        ])->render();
    }

    /** Les cellules de chiffres seules, pour qu'un « 5 » d'une date ne compte pas. */
    private function chiffres(string $html): string
    {
        preg_match_all('/<td class="chiffre">([^<]*)<\/td>/u', $html, $trouves);

        return '>' . implode('<>', array_map('trim', $trouves[1])) . '<';
    }

    private function investisseur(string $identifiant, string $prenom): Investisseur
    {
        return Investisseur::create([
            'identifiant_externe' => $identifiant,
            'type_personne' => 'physique',
            'nom' => 'Essai',
            'prenom' => $prenom,
            'statut' => 'actif',
            'gestionnaire_id' => Gestionnaire::orderBy('id')->firstOrFail()->id,
        ]);
    }

    private function compte(?Investisseur $investisseur = null): CompteInvestissement
    {
        $investisseur ??= $this->investisseur('Z98' . random_int(20, 99), 'Titulaire');

        return CompteInvestissement::create([
            'investisseur_id' => $investisseur->id,
            'categorie' => 'commercial',
            'numero_compte' => 'HIST-' . $investisseur->id,
            'date_ouverture' => '2025-12-01',
        ]);
    }

    private function achat(
        CompteInvestissement $compte,
        string $numero,
        string $date,
        int $actions,
        string $typeAchat = 'rajout',
        ?Investisseur $donateur = null,
    ): AchatAction {
        return AchatAction::create([
            'compte_id' => $compte->id,
            'numero_achat' => $numero,
            'date_achat' => $date,
            'type_achat' => $typeAchat,
            'nombre_actions' => $actions,
            'prix_unitaire' => 25000,
            'montant' => 25000 * $actions,
            'offert_par_investisseur_id' => $donateur?->id,
            'type_present' => $donateur ? 'memoire' : null,
            'present_pour' => $donateur ? 'Personne honorée' : null,
        ]);
    }

    private function radiation(CompteInvestissement $compte, int $actions, string $date): void
    {
        DB::table('radiations')->insert([
            'compte_id' => $compte->id,
            'numero_radiation' => 'RAD-H' . $compte->id,
            'date_radiation' => $date,
            'nombre_actions_radiees' => $actions,
            'prix_unitaire_action' => 25000,
            'montant_total' => 25000 * $actions,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
