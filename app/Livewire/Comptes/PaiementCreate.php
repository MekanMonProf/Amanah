<?php

namespace App\Livewire\Comptes;

use App\Models\CompteInvestissement;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class PaiementCreate extends Component
{
    use WithFileUploads;
    use \App\Support\RestreintAuPortefeuilleGestionnaire;

    public CompteInvestissement $compte;

    #[Validate('required|numeric|min:1')]
    public ?float $montant = null;

    #[Validate('required|date')]
    public string $date_paiement;

    #[Validate('required|in:Wave,Orange Money,Espèces,Virement bancaire,Chèque,Autre')]
    public string $mode_paiement = 'Wave';

    #[Validate('nullable|string|max:100')]
    public string $reference = '';

    #[Validate('nullable|file|mimes:jpg,jpeg,png,pdf|max:5120')]
    public $preuve_upload = null;

    public bool $versementAutorise = true;

    /**
     * Provenance du versement, déterminée par le lien d'origine (?source=radiation).
     */
    public string $source = 'dividende';

    /**
     * Si le paiement vient d'une radiation, on garde son id pour relier l'écriture —
     * c'est ce qui permet d'afficher le statut "payé / non payé" sur la radiation.
     */
    public ?int $radiationId = null;

    /**
     * L'écriture qui vient d'être créée, tant que l'écran propose son reçu.
     *
     * L'écran ne repart plus tout seul vers la fiche : le moment où l'on
     * vient d'enregistrer est celui où l'on tient encore l'investisseur, et
     * donc celui où la question de lui envoyer son reçu se pose.
     */
    public ?int $ecritureDuRecu = null;

    public function mount(CompteInvestissement $compte): void
    {
        $this->assurerAccesGestionnairePourCompte($compte);
        $this->compte = $compte;
        abort_if($compte->investisseur->estDecede(), 403, __("Ce compte est gelé — l'investisseur est déclaré décédé. Gérez la succession depuis sa fiche."));
        $this->date_paiement = now()->toDateString();

        $this->source = request()->query('source') === 'radiation' ? 'radiation' : 'dividende';
        $this->radiationId = request()->query('radiation_id') ? (int) request()->query('radiation_id') : null;

        $politique = $compte->politique();
        $this->versementAutorise = $this->source === 'radiation'
            ? (bool) ($politique?->versement_capital_radiation_possible ?? true)
            : (bool) ($politique?->versement_dividendes_possible ?? true);

        if (request()->has('montant')) {
            $this->montant = (float) request()->query('montant');
        }
        if (request()->has('reference')) {
            $this->reference = (string) request()->query('reference');
        }
    }

    public function enregistrer(): void
    {
        // Relu en base, et non repris de la propriete : $versementAutorise et
        // $source sont deux proprietes Livewire, donc falsifiables. Sans cette
        // relecture, il suffisait d'annoncer « source = radiation » pour sortir
        // l'argent d'un compte dont la categorie l'interdit.
        $politique = $this->compte->politique();
        $this->versementAutorise = $this->source === 'radiation'
            ? (bool) ($politique?->versement_capital_radiation_possible ?? true)
            : (bool) ($politique?->versement_dividendes_possible ?? true);

        if (! $this->versementAutorise) {
            $this->addError('montant', __("Ce versement n'est pas autorisé pour cette catégorie de compte (voir la politique d'investissement)."));
            return;
        }

        $this->validate();

        if ($this->montant > $this->compte->solde()) {
            $this->addError('montant', __("Le montant dépasse le solde disponible sur ce compte (:solde).", ['solde' => \App\Support\Montant::avecDevise($this->compte->solde())]));
            return;
        }

        $cheminPreuve = null;
        if ($this->preuve_upload) {
            $cheminPreuve = $this->preuve_upload->store('preuves-paiement', 'local');
        }

        $cleVersement = $this->source === 'radiation'
            ? \App\Support\Observation::selonReference(\App\Support\Observation::VERSEMENT_CAPITAL_RADIE, \App\Support\Observation::VERSEMENT_CAPITAL_RADIE_REF, $this->reference)
            : \App\Support\Observation::selonReference(\App\Support\Observation::VERSEMENT_INVESTISSEUR, \App\Support\Observation::VERSEMENT_INVESTISSEUR_REF, $this->reference);

        $ecriture = $this->compte->ajouterEcriture(
            type: 'paiement',
            montant: -$this->montant,
            dateEcriture: $this->date_paiement,
            referenceType: $this->radiationId ? 'radiations' : null,
            referenceId: $this->radiationId,
            observationCle: $cleVersement,
            observationParametres: ['mode' => $this->mode_paiement, 'reference' => $this->reference],
            userId: Auth::id(),
            pieceJustificativePath: $cheminPreuve,
        );

        session()->flash('succes', __("Paiement de :montant enregistré.", ['montant' => \App\Support\Montant::avecDevise($this->montant)]));

        \App\Models\AuditLog::enregistrer(
            action: 'paiement',
            entite: 'compte_investissement',
            entiteId: $this->compte->id,
            apres: [
                'compte' => $this->compte->numero_compte, 'montant' => $this->montant,
                'mode_paiement' => $this->mode_paiement, 'source' => $this->source,
            ],
        );

        $this->ecritureDuRecu = $ecriture->id;
    }

    public function render()
    {
        return view('livewire.comptes.paiement-create');
    }
}
