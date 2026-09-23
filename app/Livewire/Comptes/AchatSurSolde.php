<?php

namespace App\Livewire\Comptes;

use App\Models\CompteInvestissement;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class AchatSurSolde extends Component
{
    use \App\Support\RestreintAuPortefeuilleGestionnaire;

    public CompteInvestissement $compte;

    public float $prixAction;
    public int $nbActionsPossibles;
    public float $montantUtilise;
    public float $reliquat;

    public ?int $resultatNbAchats = null;

    public function mount(CompteInvestissement $compte): void
    {
        $this->assurerAccesGestionnairePourCompte($compte);
        $this->compte = $compte;
        abort_if($compte->investisseur->estDecede(), 403, __("Ce compte est gelé — l'investisseur est déclaré décédé. Gérez la succession depuis sa fiche."));
        $this->calculerApercu();
    }

    protected function calculerApercu(): void
    {
        $this->prixAction = (float) ($this->compte->politique()?->prix_unitaire_action ?? 25000);
        $solde = $this->compte->solde();

        $this->nbActionsPossibles = $this->prixAction > 0 ? intdiv((int) floor($solde), (int) $this->prixAction) : 0;
        $this->montantUtilise = $this->nbActionsPossibles * $this->prixAction;
        $this->reliquat = $solde - $this->montantUtilise;
    }

    public function confirmerAchat(): void
    {
        $nbAchats = $this->compte->acheterActionsAvecSoldeDisponible(
            typeAchat: 'complement',
            observationCle: \App\Support\Observation::ACHAT_SUR_SOLDE,
            userId: Auth::id(),
        );

        $this->resultatNbAchats = $nbAchats;
        $this->calculerApercu();
    }

    public function render()
    {
        return view('livewire.comptes.achat-sur-solde');
    }
}
