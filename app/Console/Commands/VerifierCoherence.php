<?php

namespace App\Console\Commands;

use App\Models\AchatAction;
use App\Models\AuditLog;
use App\Models\CompteInvestissement;
use App\Models\Dividende;
use App\Models\Don;
use App\Models\Gestionnaire;
use App\Models\Heritier;
use App\Models\Investisseur;
use App\Models\Radiation;
use App\Models\User;
use App\Support\Completude;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Relit la base et vérifie qu'elle raconte une histoire tenable.
 *
 * Une donnée peut être valide au sens de la base — clés étrangères en place,
 * colonnes du bon type — et rester incohérente pour qui la lit : un dividende
 * calculé sur des actions pas encore achetées, un défunt qui souscrit, une
 * succession réglée dont le compte n'est pas soldé. Ces contradictions ne se
 * voient qu'en recoupant plusieurs tables, et elles sautent aux yeux en
 * démonstration. D'où cette commande, à passer après un nouveau jeu de données.
 */
class VerifierCoherence extends Command
{
    protected $signature = 'amanah:verifier-coherence';

    protected $description = "Recoupe les tables et signale les contradictions visibles à l'écran";

    private int $echecs = 0;

    public function handle(): int
    {
        $this->info('Cohérence des données');
        $this->newLine();

        $this->controle('Chaîne des soldes', fn () => $this->chaineDesSoldes());
        $this->controle('Aucun solde négatif', fn () => $this->soldesNegatifs());
        $this->controle("Aucun nombre d'actions négatif", fn () => $this->actionsNegatives());
        $this->controle('Arithmétique des achats, dividendes et radiations', fn () => $this->arithmetique());
        $this->controle("Aucun événement antérieur à l'inscription", fn () => $this->evenementsPrematures());
        $this->controle('Aucun dividende sur des actions non détenues', fn () => $this->dividendesAnticipes());
        $this->controle('Aucun revenu ni achat postérieur au décès', fn () => $this->apresLeDeces());
        $this->controle('Successions réglées = comptes soldés', fn () => $this->successionsReglees());
        $this->controle('Héritiers complets et rattachés', fn () => $this->heritiers());
        $this->controle('Aucun versement de bénéfice sur un compte waqf', fn () => $this->waqf());
        $this->controle('Téléphone et gestionnaire renseignés', fn () => $this->coordonnees());
        $this->controle('Comptes de connexion rattachés', fn () => $this->connexions());
        $this->controle('Aucun compte ouvert resté sans suite', fn () => $this->comptesVides());
        $this->controle('Le filtre « dossiers incomplets » dit vrai', fn () => $this->completude());

        $this->newLine();
        $this->inventaire();

        $this->newLine();

        if ($this->echecs > 0) {
            $this->error($this->echecs . ' contrôle(s) en échec.');

            return self::FAILURE;
        }

        $this->info('Tout est cohérent.');

        return self::SUCCESS;
    }

    /** @param callable(): array<int, string> $sonde */
    private function controle(string $titre, callable $sonde): void
    {
        $anomalies = $sonde();

        if (! $anomalies) {
            $this->line('  <fg=green>OK</>  ' . $titre);

            return;
        }

        $this->echecs++;
        $this->line('  <fg=red>KO</>  ' . $titre . ' (' . count($anomalies) . ')');

        foreach (array_slice($anomalies, 0, 5) as $ligne) {
            $this->line('        <fg=gray>' . $ligne . '</>');
        }

        if (count($anomalies) > 5) {
            $this->line('        <fg=gray>… et ' . (count($anomalies) - 5) . ' autre(s)</>');
        }
    }

    /** @return array<int, string> */
    private function chaineDesSoldes(): array
    {
        $anomalies = [];

        foreach (CompteInvestissement::all() as $compte) {
            $cumul = 0.0;

            foreach ($compte->ecritures()->orderBy('id')->get() as $ecriture) {
                $cumul += (float) $ecriture->montant;

                if (abs($cumul - (float) $ecriture->solde_apres) > 0.01) {
                    $anomalies[] = "compte {$compte->id}, écriture #{$ecriture->id} : solde_apres {$ecriture->solde_apres} au lieu de " . round($cumul, 2);
                    break;
                }
            }

            if (abs($cumul - $compte->solde()) > 0.01) {
                $anomalies[] = "compte {$compte->id} : solde() " . $compte->solde() . ' contre ' . round($cumul, 2);
            }
        }

        return $anomalies;
    }

    /** @return array<int, string> */
    private function soldesNegatifs(): array
    {
        $anomalies = [];

        foreach (CompteInvestissement::all() as $compte) {
            foreach ($compte->ecritures()->orderBy('id')->get() as $ecriture) {
                if ((float) $ecriture->solde_apres < -0.01) {
                    $anomalies[] = "compte {$compte->id}, écriture #{$ecriture->id} le " . substr((string) $ecriture->date_ecriture, 0, 10) . " : {$ecriture->solde_apres}";
                    break;
                }
            }
        }

        return $anomalies;
    }

    /** @return array<int, string> */
    private function actionsNegatives(): array
    {
        $anomalies = [];

        foreach (CompteInvestissement::all() as $compte) {
            if ($compte->nombreActions() < 0) {
                $anomalies[] = "compte {$compte->id} : " . $compte->nombreActions() . ' actions';
            }
        }

        return $anomalies;
    }

    /** @return array<int, string> */
    private function arithmetique(): array
    {
        $anomalies = [];

        foreach (AchatAction::all() as $achat) {
            if (abs((float) $achat->montant - $achat->nombre_actions * (float) $achat->prix_unitaire) > 0.01) {
                $anomalies[] = "achat {$achat->numero_achat} : montant {$achat->montant}";
            }
        }

        foreach (Dividende::all() as $dividende) {
            if (abs((float) $dividende->montant_calcule - $dividende->nombre_actions * (float) $dividende->benefice_par_action) > 0.01) {
                $anomalies[] = "dividende #{$dividende->id} : montant {$dividende->montant_calcule}";
            }
        }

        foreach (Radiation::all() as $radiation) {
            if (abs((float) $radiation->montant_total - $radiation->nombre_actions_radiees * (float) $radiation->prix_unitaire_action) > 0.01) {
                $anomalies[] = "radiation {$radiation->numero_radiation} : montant {$radiation->montant_total}";
            }
        }

        return $anomalies;
    }

    /** @return array<int, string> */
    private function evenementsPrematures(): array
    {
        $anomalies = [];

        foreach (CompteInvestissement::with('investisseur')->get() as $compte) {
            $inscription = $compte->investisseur?->created_at?->toDateString();

            if (! $inscription) {
                continue;
            }

            if ($compte->date_ouverture->toDateString() < $inscription) {
                $anomalies[] = "compte {$compte->id} ouvert le {$compte->date_ouverture->toDateString()}, inscription le {$inscription}";
            }

            foreach ($compte->achats as $achat) {
                if ($achat->date_achat->toDateString() < $inscription) {
                    $anomalies[] = "achat {$achat->numero_achat} le {$achat->date_achat->toDateString()}, inscription le {$inscription}";
                }
            }
        }

        foreach (DB::table('historique_affectations')->get() as $transfert) {
            $investisseur = Investisseur::find($transfert->investisseur_id);

            if ($investisseur && substr((string) $transfert->date_transfert, 0, 10) < $investisseur->created_at->toDateString()) {
                $anomalies[] = "{$investisseur->identifiant_externe} transféré le " . substr((string) $transfert->date_transfert, 0, 10) . ', inscrit le ' . $investisseur->created_at->toDateString();
            }
        }

        return $anomalies;
    }

    /**
     * Le dividende se calcule sur les actions détenues au moment de la distribution,
     * qui a lieu en fin de période. Un dividende portant sur plus d'actions que le
     * compte n'en détenait à cette date-là ne peut donc pas s'expliquer.
     *
     * @return array<int, string>
     */
    private function dividendesAnticipes(): array
    {
        $anomalies = [];

        // La règle de sortie fait partie de ce qui ouvre droit au dividende : un
        // actionnaire sorti en cours de mois garde le mois quand elle le prévoit. Le
        // contrôle lit donc le même paramètre que le calcul, sinon il signalerait
        // comme anomalie ce que la maison a décidé.
        $delaiRadiation = \App\Models\ParametreDividende::actuel()->delai_radiation_jours;

        foreach (Dividende::with('compte')->get() as $dividende) {
            $compte = $dividende->compte;

            if (! $compte) {
                continue;
            }

            $detenues = $compte->actionsRetenuesPourDividende($dividende->periode, $delaiRadiation);

            if ((int) $dividende->nombre_actions > $detenues) {
                $anomalies[] = "compte {$compte->id}, " . $dividende->periode->format('Y-m') . " : dividende sur {$dividende->nombre_actions} actions, {$detenues} détenue(s)";
            }
        }

        return $anomalies;
    }

    /** @return array<int, string> */
    private function apresLeDeces(): array
    {
        $anomalies = [];

        foreach (Investisseur::where('statut', 'decede')->get() as $investisseur) {
            $deces = $investisseur->date_deces?->toDateString();

            if (! $deces) {
                $anomalies[] = "{$investisseur->identifiant_externe} : déclaré décédé sans date";

                continue;
            }

            foreach ($investisseur->comptes as $compte) {
                foreach ($compte->achats as $achat) {
                    if ($achat->date_achat->toDateString() > $deces) {
                        $anomalies[] = "{$investisseur->identifiant_externe} : achat {$achat->numero_achat} le {$achat->date_achat->toDateString()}, décès le {$deces}";
                    }
                }

                foreach ($compte->dividendes as $dividende) {
                    if ($dividende->periode->toDateString() > $deces) {
                        $anomalies[] = "{$investisseur->identifiant_externe} : dividende " . $dividende->periode->format('Y-m') . ' postérieur au décès';
                    }
                }
            }
        }

        return $anomalies;
    }

    /** @return array<int, string> */
    private function successionsReglees(): array
    {
        $anomalies = [];

        $regles = Investisseur::where('statut', 'decede')->where('succession_reglee', true)->get();

        foreach ($regles as $investisseur) {
            foreach ($investisseur->comptes as $compte) {
                if ($compte->solde() > 0.01 || $compte->nombreActions() > 0) {
                    $anomalies[] = "{$investisseur->identifiant_externe} : succession réglée, mais solde " . $compte->solde() . ' et ' . $compte->nombreActions() . ' action(s)';
                }
            }
        }

        return $anomalies;
    }

    /** @return array<int, string> */
    private function heritiers(): array
    {
        $anomalies = [];

        foreach (Heritier::all() as $heritier) {
            $manquants = array_keys(array_filter([
                'investisseur' => ! $heritier->investisseur_id,
                'nom' => ! $heritier->nom,
                'prénom' => ! $heritier->prenom,
                'téléphone' => ! $heritier->telephone,
            ]));

            if ($manquants) {
                $anomalies[] = "héritier #{$heritier->id} : " . implode(', ', $manquants) . ' manquant(s)';
            }
        }

        return $anomalies;
    }

    /**
     * La politique waqf interdit le versement des bénéfices : le capital y est
     * immobilisé. Seul le capital d'une radiation peut en sortir, et celui-là
     * porte une référence.
     *
     * @return array<int, string>
     */
    private function waqf(): array
    {
        $anomalies = [];

        foreach (CompteInvestissement::where('categorie', 'waqf')->get() as $compte) {
            foreach ($compte->ecritures()->where('type_ecriture', 'paiement')->whereNull('reference_type')->get() as $ecriture) {
                $anomalies[] = "compte waqf {$compte->id} : versement #{$ecriture->id} de {$ecriture->montant}";
            }
        }

        return $anomalies;
    }

    /** @return array<int, string> */
    private function coordonnees(): array
    {
        $anomalies = [];

        foreach (Investisseur::all() as $investisseur) {
            // Le compte institutionnel du Waqf caritatif n'a ni correspondant ni
            // gestionnaire : il reçoit les présents, il ne se démarche pas.
            if ($investisseur->estWaqfCaritatif()) {
                continue;
            }

            if (! $investisseur->telephone) {
                $anomalies[] = "{$investisseur->identifiant_externe} : sans téléphone";
            }

            if (! $investisseur->gestionnaire_id) {
                $anomalies[] = "{$investisseur->identifiant_externe} : sans gestionnaire";
            }
        }

        return $anomalies;
    }

    /** @return array<int, string> */
    private function connexions(): array
    {
        $anomalies = [];

        foreach (Gestionnaire::all() as $gestionnaire) {
            if (! $gestionnaire->user) {
                $anomalies[] = "gestionnaire #{$gestionnaire->id} sans compte de connexion";
            }
        }

        foreach (User::where('role', 'investisseur')->get() as $user) {
            if (! Investisseur::where('user_id', $user->id)->exists()) {
                $anomalies[] = "utilisateur #{$user->id} de rôle investisseur, sans fiche";
            }

            if (blank($user->email) && blank($user->telephone)) {
                $anomalies[] = "utilisateur #{$user->id} : ni email ni téléphone pour se connecter";
            }
        }

        return $anomalies;
    }

    /**
     * Un compte ouvert que rien n'a jamais alimente.
     *
     * Le controle portait d'abord sur l'absence d'achat, et signalait a tort les
     * comptes dont toute la valeur vient d'un don : un beneficiaire peut n'avoir
     * jamais achete une seule action et detenir pourtant des parts et un solde.
     * C'est le vide reel qu'on cherche — ni achat, ni don recu, ni ecriture —
     * parce que lui seul trahit une ouverture restee sans suite.
     *
     * @return array<int, string>
     */
    private function comptesVides(): array
    {
        $anomalies = [];

        foreach (CompteInvestissement::all() as $compte) {
            $alimente = $compte->achats()->exists()
                || $compte->donsRecus()->exists()
                || $compte->ecritures()->exists();

            if (! $alimente) {
                $anomalies[] = "compte {$compte->id} ({$compte->numero_compte}) ouvert sans achat, sans don ni ecriture";
            }
        }

        return $anomalies;
    }

    /**
     * Le filtre de la liste et la fiche doivent dire la même chose : un dossier
     * signalé incomplet doit avoir un champ manquant, et réciproquement.
     *
     * @return array<int, string>
     */
    private function completude(): array
    {
        $anomalies = [];

        $signales = Completude::filtrerIncomplets(Investisseur::query())
            ->pluck('identifiant_externe')->all();

        foreach (Investisseur::all() as $investisseur) {
            $manquants = Completude::manquants($investisseur);
            $listeLeDit = in_array($investisseur->identifiant_externe, $signales, true);

            if ($listeLeDit === (bool) $manquants) {
                continue;
            }

            $anomalies[] = $listeLeDit
                ? "{$investisseur->identifiant_externe} : signalé incomplet, mais la fiche est complète"
                : "{$investisseur->identifiant_externe} : donné complet, mais il manque " . implode(', ', $manquants);
        }

        return $anomalies;
    }

    /**
     * L'inventaire n'est pas un contrôle : il dit ce que la base a à montrer.
     * Un zéro sur une ligne signale une fonctionnalité qu'aucune donnée n'illustre.
     */
    private function inventaire(): void
    {
        $this->info('Ce que la base a à montrer');

        $mois = [];

        foreach (DB::table('ecritures_compte_financier')->pluck('date_ecriture') as $date) {
            $mois[substr((string) $date, 0, 7)] = true;
        }

        if ($mois) {
            $curseur = Carbon::parse(min(array_keys($mois)) . '-01');
            $vides = [];

            while ($curseur->format('Y-m') <= now()->format('Y-m')) {
                if (! isset($mois[$curseur->format('Y-m')])) {
                    $vides[] = $curseur->format('Y-m');
                }

                $curseur->addMonth();
            }

            $this->line($vides
                ? '  <fg=yellow>mois sans aucune écriture : ' . implode(', ', $vides) . '</>'
                : '  <fg=gray>' . count($mois) . ' mois consécutifs couverts</>');
        }

        $lignes = [
            'Investisseurs' => Investisseur::count(),
            '— personnes morales' => Investisseur::where('type_personne', 'morale')->count(),
            '— hors Sénégal' => Investisseur::where('pays', '!=', 'Sénégal')->count(),
            '— dossiers incomplets' => Completude::filtrerIncomplets(Investisseur::query())->count(),
            '— décédés' => Investisseur::where('statut', 'decede')->count(),
            '— inactifs' => Investisseur::where('statut', 'inactif')->count(),
            'Comptes waqf' => CompteInvestissement::where('categorie', 'waqf')->count(),
            'Présents au waqf' => AchatAction::whereNotNull('type_present')->count(),
            'Achats sur solde' => AchatAction::where('type_achat', 'complement')->count(),
            'Réinvestissements' => AchatAction::where('type_achat', 'benefice')->count(),
            'Radiations volontaires' => Radiation::where('numero_radiation', 'not like', Radiation::PREFIXE_SUCCESSION . '%')->count(),
            'Liquidations de succession' => Radiation::where('numero_radiation', 'like', Radiation::PREFIXE_SUCCESSION . '%')->count(),
            'Dons' => Don::count(),
            'Corrections de barème' => AuditLog::where('action', 'correction_bareme')->count(),
            'Transferts de gestionnaire' => DB::table('historique_affectations')->count(),
            'Versements aux investisseurs' => DB::table('ecritures_compte_financier')->where('type_ecriture', 'paiement')->whereNull('reference_type')->count(),
            'Paiements de radiation' => DB::table('ecritures_compte_financier')->where('type_ecriture', 'paiement')->where('reference_type', 'radiations')->count(),
            "Entrées au journal d'audit" => AuditLog::count(),
        ];

        foreach ($lignes as $quoi => $combien) {
            $couleur = $combien === 0 ? 'yellow' : 'gray';

            // str_pad compte les octets : « décédés » en occuperait trois de plus
            // que sa largeur à l'écran, et la colonne se décalerait.
            $remplissage = str_repeat(' ', max(1, 30 - mb_strlen($quoi)));

            $this->line('  <fg=' . $couleur . '>' . $quoi . $remplissage . $combien . '</>');
        }
    }
}
