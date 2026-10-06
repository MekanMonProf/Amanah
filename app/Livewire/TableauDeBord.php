<?php

namespace App\Livewire;

use App\Models\CompteInvestissement;
use App\Models\Dividende;
use App\Models\EcritureCompteFinancier;
use App\Models\Gestionnaire;
use App\Models\Investisseur;
use App\Models\Radiation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TableauDeBord extends Component
{
    public function mount(): void
    {
        if (Auth::user()->role === 'investisseur') {
            $this->redirectRoute('portail.mon-compte', navigate: true);
        }
    }

    public function render()
    {
        $user = Auth::user();

        if ($user->role === 'gestionnaire') {
            return view('livewire.tableau-de-bord', $this->statsGestionnaire($user))
                ->with('vue', 'gestionnaire');
        }

        return view('livewire.tableau-de-bord', $this->statsGlobales())
            ->with('vue', 'globale');
    }

    /**
     * Statistiques globales pour Direction / Administrateur / Lecture.
     */
    protected function statsGlobales(): array
    {
        $nbInvestisseursActifs = Investisseur::where('statut', 'actif')->count();
        // Le total accompagne l'effectif actif : « 367 » seul se lit comme le
        // nombre d'investisseurs, et les inactifs disparaissent sans que rien
        // ne le signale.
        $nbInvestisseurs = Investisseur::count();
        $nbInvestisseursSansGestionnaire = Investisseur::whereNull('gestionnaire_id')->where('statut', 'actif')->count();
        $nbGestionnairesActifs = Gestionnaire::where('actif', true)->count();
        $nbDossiersIncomplets = Investisseur::where('statut', 'actif')->incomplets()->count();

        $comptesParCategorie = CompteInvestissement::where('statut', 'actif')
            ->selectRaw('categorie, count(*) as nb')
            ->groupBy('categorie')
            ->pluck('nb', 'categorie');

        $actionsAcheteesParCategorie = CompteInvestissement::join('achats_actions', 'achats_actions.compte_id', '=', 'comptes_investissement.id')
            ->selectRaw('comptes_investissement.categorie, SUM(achats_actions.nombre_actions) as total')
            ->groupBy('comptes_investissement.categorie')
            ->pluck('total', 'categorie');

        $actionsRadieesParCategorie = CompteInvestissement::join('radiations', 'radiations.compte_id', '=', 'comptes_investissement.id')
            ->selectRaw('comptes_investissement.categorie, SUM(radiations.nombre_actions_radiees) as total')
            ->groupBy('comptes_investissement.categorie')
            ->pluck('total', 'categorie');

        $actionsNettesParCategorie = collect(['commercial', 'waqf'])->mapWithKeys(fn ($cat) => [
            $cat => (int) ($actionsAcheteesParCategorie[$cat] ?? 0) - (int) ($actionsRadieesParCategorie[$cat] ?? 0),
        ]);

        // Solde cumulé par catégorie — recalculé depuis les écritures de chaque compte actif
        // (jamais stocké, cohérent avec le reste du système).
        $soldeParCategorie = ['commercial' => 0.0, 'waqf' => 0.0];
        CompteInvestissement::where('statut', 'actif')->get(['id', 'categorie'])->each(function ($compte) use (&$soldeParCategorie) {
            $soldeParCategorie[$compte->categorie] = ($soldeParCategorie[$compte->categorie] ?? 0) + $compte->solde();
        });

        $totalDividendesDistribues = Dividende::sum('montant_calcule');
        // Sur la période, pas sur la date d'enregistrement. Les deux coïncidaient tant
        // qu'un mois était distribué dans le mois ; la reprise de l'historique les a
        // séparés d'un coup — vingt mois de dividendes enregistrés le même jour, et la
        // tuile annonçait alors tout l'historique comme perçu ce mois-ci.
        $dividendesCeMois = Dividende::whereMonth('periode', now()->month)
            ->whereYear('periode', now()->year)
            ->sum('montant_calcule');

        $totalRadiationsMontant = Radiation::sum('montant_total');
        $totalRadiationsVersees = EcritureCompteFinancier::where('reference_type', 'radiations')
            ->where('type_ecriture', 'paiement')
            ->sum('montant');
        $radiationsEnAttente = $totalRadiationsMontant - abs($totalRadiationsVersees);

        return [
            'nbInvestisseursActifs' => $nbInvestisseursActifs,
            'nbInvestisseurs' => $nbInvestisseurs,
            'nbInvestisseursSansGestionnaire' => $nbInvestisseursSansGestionnaire,
            'nbDossiersIncomplets' => $nbDossiersIncomplets,
            'nbGestionnairesActifs' => $nbGestionnairesActifs,
            'comptesParCategorie' => $comptesParCategorie,
            'actionsNettesParCategorie' => $actionsNettesParCategorie,
            'soldeParCategorie' => $soldeParCategorie,
            'totalDividendesDistribues' => $totalDividendesDistribues,
            'dividendesCeMois' => $dividendesCeMois,
            'radiationsEnAttente' => $radiationsEnAttente,
            'derniersInvestisseurs' => Investisseur::latest()->take(5)->get(),
        ];
    }

    /**
     * Statistiques du portefeuille personnel pour un Gestionnaire.
     */
    protected function statsGestionnaire($user): array
    {
        $gestionnaire = $user->gestionnaire;

        if (! $gestionnaire) {
            return ['aucunPortefeuille' => true];
        }

        $investisseursIds = Investisseur::where('gestionnaire_id', $gestionnaire->id)->pluck('id');
        $nbInvestisseurs = $investisseursIds->count();

        $comptes = CompteInvestissement::whereIn('investisseur_id', $investisseursIds)->where('statut', 'actif')->get();

        $totalActions = 0;
        $totalSolde = 0.0;
        foreach ($comptes as $compte) {
            $totalActions += $compte->nombreActions();
            $totalSolde += $compte->solde();
        }

        $dernierAchats = \App\Models\AchatAction::whereIn('compte_id', $comptes->pluck('id'))
            ->latest('created_at')
            ->take(5)
            ->get();

        return [
            'nbInvestisseurs' => $nbInvestisseurs,
            'nbComptes' => $comptes->count(),
            'totalActions' => $totalActions,
            'totalSolde' => $totalSolde,
            'dernierAchats' => $dernierAchats,
            'mesInvestisseurs' => Investisseur::where('gestionnaire_id', $gestionnaire->id)->latest()->take(5)->get(),
        ];
    }
}
