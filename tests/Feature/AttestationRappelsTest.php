<?php

namespace Tests\Feature;

use App\Http\Controllers\AttestationController;
use App\Models\CompteInvestissement;
use App\Models\EcritureCompteFinancier;
use App\Models\Gestionnaire;
use App\Models\Investisseur;
use App\Models\Radiation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Le même rappel que sur l'attestation d'achat, porté aux deux autres.
 *
 * Une radiation isolée ne disait pas où en était le compte — treize des
 * quarante-six comptes radiés l'ont été en plusieurs fois. Un versement isolé
 * ne disait pas ce qui avait déjà été payé — quarante-deux comptes sur
 * quatre-vingt-dix-neuf en ont reçu plusieurs.
 *
 * Le reçu, lui, n'en porte pas : il s'ouvre par un lien signé sans connexion,
 * que son destinataire peut transférer, et il doit continuer de ne parler que
 * de son opération. C'est le dernier test qui le garde.
 */
class AttestationRappelsTest extends TestCase
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

    // ---- Les radiations ----------------------------------------------------

    public function test_les_radiations_anterieures_figurent_sur_l_attestation(): void
    {
        $compte = $this->compte();
        $this->achat($compte, 30, '2026-01-05');

        $this->radiation($compte, 'RAD-R1', '2026-02-10', 10);
        $attestee = $this->radiation($compte, 'RAD-R2', '2026-03-10', 5);

        $html = $this->rendreRadiation($attestee);

        $this->assertStringContainsString('Radiations opérées sur ce compte', $html);
        $this->assertStringContainsString('RAD-R1', $html);
        $this->assertStringContainsString('Total sorti à cette date', $html);
        // 10 + 5 sorties sur 30 acquises : il doit rester 15.
        $this->assertMatchesRegularExpression(
            '/ce compte détenait encore\s*<strong>15 action\(s\)<\/strong>/u',
            $html,
        );
    }

    public function test_une_premiere_radiation_n_affiche_pas_de_tableau_mais_dit_le_reste(): void
    {
        $compte = $this->compte();
        $this->achat($compte, 30, '2026-01-05');

        $attestee = $this->radiation($compte, 'RAD-R3', '2026-02-10', 4);

        $html = $this->rendreRadiation($attestee);

        $this->assertStringNotContainsString('Radiations opérées', $html);
        // Le reste se dit même sur une sortie isolée : c'est la question posée.
        $this->assertMatchesRegularExpression(
            '/ce compte détenait encore\s*<strong>26 action\(s\)<\/strong>/u',
            $html,
        );
    }

    public function test_un_compte_entierement_sorti_le_dit_autrement(): void
    {
        $compte = $this->compte();
        $this->achat($compte, 8, '2026-01-05');

        $attestee = $this->radiation($compte, 'RAD-R4', '2026-02-10', 8);

        $this->assertStringContainsString(
            'ne détenait plus',
            $this->rendreRadiation($attestee),
        );
    }

    public function test_une_radiation_posterieure_n_entre_pas_dans_l_attestation(): void
    {
        $compte = $this->compte();
        $this->achat($compte, 30, '2026-01-05');

        $attestee = $this->radiation($compte, 'RAD-R5', '2026-02-10', 4);
        $this->radiation($compte, 'RAD-R6', '2026-06-10', 9);

        $this->assertStringNotContainsString('RAD-R6', $this->rendreRadiation($attestee));
    }

    // ---- Les versements ----------------------------------------------------

    public function test_les_versements_anterieurs_figurent_sur_l_attestation(): void
    {
        $compte = $this->compte();

        $this->ecriture($compte, 'paiement', -40000, '2026-02-10');
        $attestee = $this->ecriture($compte, 'paiement', -25000, '2026-03-10');

        $html = $this->rendreVersement($attestee);

        $this->assertStringContainsString('Versements reçus sur ce compte', $html);
        $this->assertStringContainsString('Total versé à cette date', $html);
        $this->assertStringContainsString('65 000 CFA', $html);
    }

    public function test_un_dividende_n_est_pas_compte_comme_un_versement(): void
    {
        $compte = $this->compte();

        // Une entrée au compte n'est pas une sortie : la mêler fausserait le total.
        $this->ecriture($compte, 'dividende', 90000, '2026-02-01');
        $this->ecriture($compte, 'paiement', -40000, '2026-02-10');
        $attestee = $this->ecriture($compte, 'paiement', -25000, '2026-03-10');

        $html = $this->rendreVersement($attestee);

        $this->assertStringContainsString('65 000 CFA', $html);
        $this->assertStringNotContainsString('90 000', $html);
    }

    public function test_un_premier_versement_n_affiche_aucun_tableau(): void
    {
        $compte = $this->compte();

        $attestee = $this->ecriture($compte, 'paiement', -25000, '2026-03-10');

        $this->assertStringNotContainsString('Versements reçus', $this->rendreVersement($attestee));
    }

    /**
     * Le reçu s'ouvre sans connexion, par un lien que l'on transfère. Lui
     * ajouter l'historique élargirait ce qu'une adresse égarée révèle.
     */
    public function test_le_recu_public_ne_porte_aucun_historique(): void
    {
        $compte = $this->compte();

        $this->ecriture($compte, 'paiement', -40000, '2026-02-10');
        $ecriture = $this->ecriture($compte, 'paiement', -25000, '2026-03-10');

        $html = view('pdf.recu-operation', [
            'ecriture' => $ecriture,
            'compte' => $ecriture->compte,
            'investisseur' => $ecriture->compte->investisseur,
            'numero' => 'REC-000001',
            'dateGeneration' => now(),
        ])->render();

        $this->assertStringNotContainsString('Versements reçus', $html);
        $this->assertStringNotContainsString('Total versé', $html);
        $this->assertStringNotContainsString('65 000', $html);
    }

    // ---- Les pièces du décor ----------------------------------------------

    private function controleur(): AttestationController
    {
        return new class extends AttestationController
        {
            public function radiations(Radiation $radiation): \Illuminate\Support\Collection
            {
                return $this->historiqueDesRadiations($radiation);
            }

            public function versements(EcritureCompteFinancier $ecriture): \Illuminate\Support\Collection
            {
                return $this->historiqueDesVersements($ecriture);
            }
        };
    }

    private function rendreRadiation(Radiation $radiation): string
    {
        $radiation->load('compte.investisseur');
        $historique = $this->controleur()->radiations($radiation);

        return view('pdf.attestation-radiation', [
            'radiation' => $radiation,
            'compte' => $radiation->compte,
            'investisseur' => $radiation->compte->investisseur,
            'montantVerse' => 0.0,
            'statutPaiement' => 'Non payé',
            'historique' => $historique,
            'actionsSorties' => (int) $historique->sum('nombre_actions_radiees'),
            'capitalSorti' => (float) $historique->sum('montant_total'),
            'actionsRestantes' => $radiation->compte->nombreActionsAu($radiation->date_radiation->toDateString()),
            'dateGeneration' => now(),
        ])->render();
    }

    private function rendreVersement(EcritureCompteFinancier $ecriture): string
    {
        $ecriture->load('compte.investisseur');
        $historique = $this->controleur()->versements($ecriture);

        return view('pdf.attestation-paiement', [
            'ecriture' => $ecriture,
            'compte' => $ecriture->compte,
            'investisseur' => $ecriture->compte->investisseur,
            'historique' => $historique,
            'totalVerse' => (float) $historique->sum(fn ($ligne) => abs((float) $ligne->montant)),
            'dateGeneration' => now(),
        ])->render();
    }

    private function compte(): CompteInvestissement
    {
        $investisseur = Investisseur::create([
            'identifiant_externe' => 'Z99' . random_int(10, 99),
            'type_personne' => 'physique',
            'nom' => 'Essai',
            'prenom' => 'Rappels',
            'statut' => 'actif',
            'gestionnaire_id' => Gestionnaire::orderBy('id')->firstOrFail()->id,
        ]);

        return CompteInvestissement::create([
            'investisseur_id' => $investisseur->id,
            'categorie' => 'commercial',
            'numero_compte' => 'RAPP-' . $investisseur->id,
            'date_ouverture' => '2025-12-01',
        ]);
    }

    private function achat(CompteInvestissement $compte, int $actions, string $date): void
    {
        $compte->achats()->create([
            'numero_achat' => 'ACH-RAPP' . $compte->id,
            'date_achat' => $date,
            'type_achat' => 'initial',
            'nombre_actions' => $actions,
            'prix_unitaire' => 25000,
            'montant' => 25000 * $actions,
        ]);
    }

    private function radiation(CompteInvestissement $compte, string $numero, string $date, int $actions): Radiation
    {
        return Radiation::create([
            'compte_id' => $compte->id,
            'numero_radiation' => $numero,
            'date_radiation' => $date,
            'nombre_actions_radiees' => $actions,
            'prix_unitaire_action' => 25000,
            'montant_total' => 25000 * $actions,
        ]);
    }

    private function ecriture(CompteInvestissement $compte, string $type, float $montant, string $date): EcritureCompteFinancier
    {
        return EcritureCompteFinancier::create([
            'compte_id' => $compte->id,
            'type_ecriture' => $type,
            'montant' => $montant,
            'solde_apres' => max($montant, 0),
            'date_ecriture' => $date,
        ]);
    }
}
