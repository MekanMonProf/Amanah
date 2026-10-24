<?php

namespace App\Livewire\Achats;

use App\Models\AchatAction;
use App\Models\AuditLog;
use App\Models\Investisseur;
use App\Support\Droits;
use App\Support\Modules;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Corriger ce qu'un achat dit, jamais ce qu'il vaut.
 *
 * Un achat est la contrepartie d'un versement réel : son nombre d'actions, son
 * prix, sa date et sa catégorie sont des faits datés dont dépendent une
 * attestation déjà remise et des dividendes déjà distribués — le calcul rejoue
 * tout l'historique à chaque période. Les corriger en place fausserait les deux
 * sans laisser de trace, et l'application ne le permet pas : un montant se
 * reprend par une radiation, un ajustement ou une correction de barème, qui
 * écrivent une ligne de plus au lieu d'en effacer une.
 *
 * Restent les champs qui décrivent sans compter : le mode de paiement, la
 * référence de la facture, la pièce jointe, l'observation saisie. Une faute de
 * frappe y est une faute de frappe, et aucun calcul n'en dépend. Ce sont les
 * seuls que cet écran ouvre.
 */
#[Layout('layouts.app')]
class AchatModifier extends Component
{
    use WithFileUploads;
    use \App\Support\RestreintAuPortefeuilleGestionnaire;

    public AchatAction $achat;

    /**
     * Le mode de paiement, qui peut rester vide.
     *
     * La saisie l'exige, la correction non : la plupart des achats repris de
     * l'ancienne application n'en portent aucun. Un champ obligatoire écrirait
     * « Wave » sur chacun d'eux au premier enregistrement — un écran de
     * correction qui invente ce qu'il ne sait pas. Vide veut dire inconnu, et
     * le reste vide tant que personne ne le renseigne.
     */
    public ?string $mode_paiement = null;

    public string $reference_facture = '';

    public string $observations = '';

    public $facture_upload = null;

    /** La personne honorée par un présent, et son lien avec le donateur. */
    public string $present_pour = '';

    public string $lien_avec_donateur = '';

    public function mount(AchatAction $achat): void
    {
        $achat->load('compte.investisseur', 'offertPar');

        $this->achat = $achat;

        $this->autoriser();

        $this->mode_paiement = $achat->mode_paiement ?: null;
        $this->reference_facture = $achat->reference_facture ?? '';
        $this->observations = $achat->observations ?? '';
        $this->present_pour = $achat->present_pour ?? '';
        $this->lien_avec_donateur = $achat->lien_avec_donateur ?? '';
    }

    /** Le dossier d'où l'on vient, et celui qui commande l'accès. */
    public function getDossierProperty(): Investisseur
    {
        return $this->achat->dossier();
    }

    /** Les modes de paiement proposés à la saisie, dans leur ordre d'usage. */
    public const MODES = ['Wave', 'Orange Money', 'Espèces', 'Virement bancaire', 'Chèque', 'Autre'];

    /**
     * Les modes offerts au choix sur cet achat.
     *
     * La liste de la saisie, plus — le cas échéant — la valeur que l'achat
     * porte déjà. L'historique repris de l'ancienne application contient des
     * libellés hors liste (« virement » en minuscules) : les omettre ferait
     * d'un simple affichage une réécriture, le navigateur sélectionnant alors
     * la première option et l'enregistrement la prenant pour un choix.
     *
     * @return list<string>
     */
    public function getModesProposesProperty(): array
    {
        $modes = self::MODES;

        if (filled($this->achat->mode_paiement) && ! in_array($this->achat->mode_paiement, $modes, true)) {
            $modes[] = $this->achat->mode_paiement;
        }

        return $modes;
    }

    /**
     * Les règles sont rassemblées ici, et non posées en attributs sur chaque
     * propriété : celle du mode de paiement dépend de ce que l'achat porte
     * déjà, et des règles à deux endroits se liraient mal.
     *
     * Les longueurs sont celles des colonnes, et les mêmes qu'à la saisie.
     */
    protected function rules(): array
    {
        return [
            'mode_paiement' => ['nullable', Rule::in($this->modesProposes)],
            'reference_facture' => ['nullable', 'string', 'max:100'],
            'observations' => ['nullable', 'string', 'max:1000'],
            'facture_upload' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'present_pour' => ['nullable', 'string', 'max:150'],
            'lien_avec_donateur' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * L'observation automatique l'emporte à l'affichage sur le texte saisi —
     * voir App\Support\Observation::rendre(). Ouvrir ce champ donnerait donc un
     * formulaire sans effet visible ; et la phrase elle-même est la trace d'un
     * acte de l'application (réinvestissement, achat sur solde), que personne
     * n'a à récrire. On la montre, en lecture seule, en disant pourquoi.
     */
    public function getObservationFigeeProperty(): bool
    {
        return filled($this->achat->observation_cle);
    }

    public function enregistrer(): void
    {
        $this->autoriser();
        $this->validate();

        $suivis = [
            'mode_paiement', 'reference_facture', 'observations',
            'present_pour', 'lien_avec_donateur', 'photo_facture_path',
        ];

        $avant = $this->achat->only($suivis);

        $modifications = [
            // Un champ laissé vide retourne null, jamais la chaîne vide : la
            // colonne distingue « inconnu » de « renseigné à blanc ».
            'mode_paiement' => $this->mode_paiement ?: null,
            'reference_facture' => trim($this->reference_facture) ?: null,
        ];

        if (! $this->observationFigee) {
            $modifications['observations'] = $this->observations ?: null;
        }

        // Les deux mentions d'un présent n'existent que sur un présent : les
        // écrire ailleurs qualifierait à moitié un achat ordinaire.
        if ($this->achat->estUnPresent()) {
            $modifications['present_pour'] = trim($this->present_pour) ?: null;
            $modifications['lien_avec_donateur'] = trim($this->lien_avec_donateur) ?: null;
        }

        // La nouvelle facture remplace l'ancienne à l'écran, mais l'ancien
        // fichier reste sur le disque : une attestation déjà remise peut en
        // porter la référence.
        if ($this->facture_upload) {
            $modifications['photo_facture_path'] = $this->facture_upload->store('factures-achats', 'local');
        }

        $this->achat->update($modifications);

        $apres = $this->achat->only($suivis);

        // Les seuls champs qui ont bougé. La comparaison est stricte : un champ
        // vidé passe de « Wave » à null, et array_diff_assoc, qui compare des
        // chaînes, confondrait ce null avec une chaîne vide.
        $bouges = array_values(array_filter(
            $suivis,
            fn ($champ) => $avant[$champ] !== $apres[$champ],
        ));

        // Rien à tracer quand rien n'a bougé : le journal d'audit sert à
        // retrouver des changements, pas à compter les visites d'un formulaire.
        if ($bouges !== []) {
            AuditLog::enregistrer(
                action: 'modification',
                entite: 'achat',
                entiteId: $this->achat->id,
                // Le numéro accompagne l'avant : « achat #412 » ne dit rien à
                // qui relit le journal, « ACH-00412 » se retrouve à l'écran.
                avant: ['numero_achat' => $this->achat->numero_achat]
                    + array_intersect_key($avant, array_flip($bouges)),
                apres: array_intersect_key($apres, array_flip($bouges)),
            );
        }

        session()->flash('succes', __("Achat :numero mis à jour.", ['numero' => $this->achat->numero_achat]));

        $this->redirectRoute('investisseurs.show', $this->dossier, navigate: true);
    }

    /**
     * Le droit d'écrire, et le portefeuille. Les deux sont revérifiés à
     * l'enregistrement : une méthode Livewire s'appelle directement, sans
     * repasser par la page qui l'a affichée.
     */
    private function autoriser(): void
    {
        Droits::exiger('investisseurs', Modules::ECRITURE);
        $this->assurerAccesGestionnaire($this->dossier);

        // Un dossier gelé par un décès ne se touche plus, pas même dans ses
        // mentions : c'est la règle que suivent déjà la fiche et les sept autres
        // formulaires. Ce qui reste à écrire sur ce dossier passe par l'écran de
        // succession, qui en garde le déroulé.
        abort_if(
            $this->dossier->estDecede(),
            403,
            __("Ce dossier est gelé — l'investisseur est déclaré décédé. Gérez la succession depuis sa fiche."),
        );
    }

    public function render()
    {
        return view('livewire.achats.achat-modifier');
    }
}
