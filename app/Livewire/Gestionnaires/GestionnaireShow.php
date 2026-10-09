<?php

namespace App\Livewire\Gestionnaires;

use App\Livewire\Gestionnaires\Concerns\AgitSurUnGestionnaire;
use App\Models\Gestionnaire;
use App\Models\HistoriqueAffectation;
use App\Models\Investisseur;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * La fiche d'un gestionnaire : qui il est, ce qu'il suit, ce qui a bougé.
 *
 * Tout se faisait jusqu'ici dans la liste, au moyen de panneaux qui s'ouvraient
 * entre deux lignes. On pouvait agir sur un gestionnaire, pas le regarder : ni
 * le détail de son portefeuille, ni l'historique des dossiers qu'il a reçus ou
 * cédés — lequel n'était visible nulle part.
 */
#[Layout('layouts.app')]
class GestionnaireShow extends Component
{
    use AgitSurUnGestionnaire;

    public Gestionnaire $gestionnaire;

    /** @var array<string, bool> */
    public array $blocsReplies = [];

    public const CLE_SESSION_BLOCS = 'amanah.blocs_replies_gestionnaire';

    /**
     * Les blocs repliés tant que personne ne les a ouverts.
     *
     * Le défaut doit être déclaré ici et nulle part ailleurs : la vue et le
     * bouton le liraient sinon chacun de son côté, et un bloc affiché replié
     * mais tenu pour ouvert demanderait deux clics pour s'ouvrir — le premier
     * ne faisant que confirmer un état déjà vrai.
     */
    private const REPLIES_PAR_DEFAUT = ['portefeuille' => true, 'transferts' => true];

    public function mount(Gestionnaire $gestionnaire): void
    {
        $this->gestionnaire = $gestionnaire;
        $this->blocsReplies = session(self::CLE_SESSION_BLOCS, []);
    }

    /**
     * Replié, un bloc n'est pas caché : il n'est plus rendu. Un portefeuille de
     * cent quatre-vingts dossiers ne se construit donc pas tant qu'on ne l'a pas
     * demandé.
     */
    public function basculerBloc(string $cle): void
    {
        $this->blocsReplies[$cle] = ! $this->estReplie($cle);

        session()->put(self::CLE_SESSION_BLOCS, $this->blocsReplies);
    }

    /** L'état d'un bloc : celui qu'on lui a donné, sinon son défaut. */
    public function estReplie(string $cle): bool
    {
        return $this->blocsReplies[$cle] ?? (self::REPLIES_PAR_DEFAUT[$cle] ?? false);
    }

    public function render()
    {
        $portefeuille = $this->gestionnaire->investisseurs()
            ->with('comptes')
            ->orderBy('identifiant_externe')
            ->get();

        return view('livewire.gestionnaires.gestionnaire-show', [
            'portefeuille' => $portefeuille,
            'position' => $this->position($portefeuille),
            'transferts' => $this->transferts(),
            // Pour le choix d'un repreneur, si la désactivation bute sur un
            // portefeuille non vide.
            'repreneurs' => Gestionnaire::avecCompte()
                ->with('user')
                ->where('id', '!=', $this->gestionnaire->id)
                ->where('actif', true)
                ->get(),
        ]);
    }

    /**
     * Ce que pèse le portefeuille : combien de dossiers, combien d'actions,
     * combien d'argent en attente sur les comptes.
     *
     * @return array{dossiers:int, actifs:int, actions:int, solde:float}
     */
    private function position($portefeuille): array
    {
        $actions = 0;
        $solde = 0.0;

        foreach ($portefeuille as $investisseur) {
            foreach ($investisseur->comptes as $compte) {
                $actions += $compte->nombreActions();
                $solde += $compte->solde();
            }
        }

        return [
            'dossiers' => $portefeuille->count(),
            'actifs' => $portefeuille->where('statut', 'actif')->count(),
            'actions' => $actions,
            'solde' => $solde,
        ];
    }

    /** Les dossiers reçus et cédés, du plus récent au plus ancien. */
    private function transferts()
    {
        return HistoriqueAffectation::with(['investisseur', 'ancienGestionnaire.user', 'nouveauGestionnaire.user', 'effectuePar'])
            ->where('ancien_gestionnaire_id', $this->gestionnaire->id)
            ->orWhere('nouveau_gestionnaire_id', $this->gestionnaire->id)
            ->orderByDesc('date_transfert')
            ->orderByDesc('id')
            ->get();
    }

    /** Le nombre de dossiers encore actifs, qui interdit la désactivation. */
    public function getDossiersActifsProperty(): int
    {
        return Investisseur::where('gestionnaire_id', $this->gestionnaire->id)
            ->where('statut', 'actif')
            ->count();
    }
}
