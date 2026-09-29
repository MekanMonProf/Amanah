<?php

namespace Database\Seeders;

use App\Models\ParametreDividende;
use App\Models\PolitiqueInvestissement;
use App\Models\User;
use App\Support\MotDePasseTemporaire;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Ce qu'il faut poser sur une installation neuve pour que l'application marche.
 *
 * À ne pas confondre avec PresentationSeeder, qui fabrique onze mois d'activité
 * fictive et commence par vider les tables métier : celui-ci ne détruit rien et
 * se rejoue sans dommage.
 *
 * Sans lui, les migrations créent `politiques_investissement` vide — et comme
 * acheterActionsAvecSoldeDisponible() s'arrête quand le prix unitaire vaut zéro,
 * les souscriptions échoueraient en silence, sans message ni trace. C'est le
 * genre de manque qu'on ne découvre qu'au premier client.
 */
class ParametresInitiauxSeeder extends Seeder
{
    /** Le prix d'une action, dans les deux catégories. */
    private const PRIX_ACTION = 25000;

    /**
     * Jours avant la fin du mois où un achat ne compte plus pour la période.
     *
     * Une souscription du 30 ne doit pas toucher le dividende du mois : elle n'a
     * rien financé. Le réglage se change ensuite depuis Paramétrage.
     */
    private const DELAI_ELIGIBILITE_JOURS = 2;

    public function run(): void
    {
        $this->politiques();
        $this->parametresDividendes();
        $this->administrateur();
    }

    /**
     * Les deux natures de compte.
     *
     * Le waqf se distingue sur trois points, qui ne relèvent pas du réglage mais
     * du principe : son capital est immobilisé, donc il ne se cède pas et ses
     * bénéfices ne se versent pas. Ils restent au compte et s'y réinvestissent.
     */
    private function politiques(): void
    {
        PolitiqueInvestissement::updateOrCreate(['categorie' => 'commercial'], [
            'eligible_dividendes' => true,
            'reinvestissement_par_defaut' => true,
            'versement_dividendes_possible' => true,
            'versement_capital_radiation_possible' => true,
            'cession_autorisee' => true,
            'radiation_autorisee' => true,
            'prix_unitaire_action' => self::PRIX_ACTION,
        ]);

        PolitiqueInvestissement::updateOrCreate(['categorie' => 'waqf'], [
            'eligible_dividendes' => true,
            'reinvestissement_par_defaut' => true,
            'versement_dividendes_possible' => false,
            'cession_autorisee' => false,
            'versement_capital_radiation_possible' => true,
            'radiation_autorisee' => true,
            'prix_unitaire_action' => self::PRIX_ACTION,
        ]);

        $this->command?->line('  Politiques commercial et waqf posées (action à '
            . number_format(self::PRIX_ACTION, 0, ',', ' ') . ' CFA).');
    }

    private function parametresDividendes(): void
    {
        // firstOrCreate et non updateOrCreate : sur une base déjà réglée, ce
        // semeur ne doit pas ramener le délai à sa valeur d'origine.
        ParametreDividende::firstOrCreate([], [
            'delai_eligibilite_jours' => self::DELAI_ELIGIBILITE_JOURS,
        ]);

        $this->command?->line('  Délai de carence : '
            . ParametreDividende::first()->delai_eligibilite_jours . ' jour(s).');
    }

    /**
     * Un premier administrateur, seulement s'il n'en existe aucun.
     *
     * Le mot de passe est tiré au hasard et affiché une fois : un semeur qui
     * poserait un mot de passe écrit dans le code laisserait la même porte
     * ouverte sur toutes les installations.
     */
    private function administrateur(): void
    {
        if (User::where('role', 'administrateur')->exists()) {
            $this->command?->line('  Un administrateur existe déjà — aucun compte créé.');

            return;
        }

        $email = env('AMANAH_ADMIN_EMAIL');

        if (blank($email)) {
            $this->command?->warn('  Aucun administrateur créé : réglez AMANAH_ADMIN_EMAIL puis relancez.');

            return;
        }

        $motDePasse = MotDePasseTemporaire::generer('ADM');

        User::create([
            'nom' => env('AMANAH_ADMIN_NOM', 'Administrateur'),
            'prenom' => env('AMANAH_ADMIN_PRENOM', ''),
            'email' => $email,
            'password' => Hash::make($motDePasse),
            'role' => 'administrateur',
            'actif' => true,
            'langue' => 'fr',
            'doit_changer_mot_de_passe' => true,
        ]);

        $this->command?->newLine();
        $this->command?->info('  Administrateur créé : ' . $email);
        $this->command?->info('  Mot de passe temporaire : ' . $motDePasse);
        $this->command?->warn('  Notez-le maintenant : il ne sera plus affiché.');
    }
}
