<?php

namespace App\Livewire\Parametrage;

use App\Models\AuditLog;
use App\Models\DemandeSupport;
use App\Models\ParametreSupport;
use App\Support\Aide;
use App\Support\Droits;
use App\Support\Modules;
use App\Support\Telephone;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Les coordonnées du support, les vidéos de l'aide, et la file des demandes.
 *
 * Les trois tiennent dans un onglet parce qu'ils se règlent ensemble et
 * rarement : on indique où écrire, on colle les adresses des vidéos à mesure
 * qu'on les enregistre, et on relève la file. Un écran par sujet aurait fait
 * trois entrées de menu pour trois visites par an.
 */
class AideEtSupport extends Component
{
    public string $telephone = '';

    public string $whatsapp = '';

    public string $email = '';

    public string $horaires = '';

    /** @var array<string, string> code du sujet => adresse de la vidéo */
    public array $videos = [];

    public string $filtreStatut = 'ouvertes';

    public ?int $demandeOuverte = null;

    public string $reponse = '';

    public function mount(): void
    {
        Droits::exiger(Modules::MODULE_VERROU, Modules::ECRITURE);

        $reglages = ParametreSupport::actuel();

        $this->telephone = $reglages->telephone ?? '';
        $this->whatsapp = $reglages->whatsapp ?? '';
        $this->email = $reglages->email ?? '';
        $this->horaires = $reglages->horaires ?? '';

        foreach (array_keys(Aide::SUJETS) as $code) {
            $this->videos[$code] = $reglages->video($code) ?? '';
        }
    }

    protected function rules(): array
    {
        return [
            'email' => ['nullable', 'email'],
            'horaires' => ['nullable', 'string', 'max:255'],
            'videos.*' => ['nullable', 'url', 'max:500'],
        ];
    }

    protected function messages(): array
    {
        return [
            'videos.*.url' => __("L'adresse d'une vidéo doit être un lien complet, commençant par https://."),
        ];
    }

    public function enregistrer(): void
    {
        Droits::exiger(Modules::MODULE_VERROU, Modules::ECRITURE);

        $this->validate();

        // Les numéros sont rangés au format international, comme partout
        // ailleurs : un numéro tapé « 77 123 45 67 » doit ouvrir WhatsApp.
        $telephone = $this->telephone !== '' ? Telephone::normaliser($this->telephone) : null;
        $whatsapp = $this->whatsapp !== '' ? Telephone::normaliser($this->whatsapp) : null;

        if ($this->telephone !== '' && $telephone === null) {
            $this->addError('telephone', __("Ce numéro n'est pas reconnaissable."));

            return;
        }

        if ($this->whatsapp !== '' && $whatsapp === null) {
            $this->addError('whatsapp', __("Ce numéro n'est pas reconnaissable."));

            return;
        }

        $reglages = ParametreSupport::actuel();

        $reglages->update([
            'telephone' => $telephone,
            'whatsapp' => $whatsapp,
            'email' => $this->email !== '' ? $this->email : null,
            'horaires' => $this->horaires !== '' ? $this->horaires : null,
            'videos' => array_filter(array_map('trim', $this->videos), fn (string $v) => $v !== ''),
        ]);

        $this->telephone = $telephone ?? '';
        $this->whatsapp = $whatsapp ?? '';

        AuditLog::enregistrer(
            action: 'modification_parametres_support',
            entite: 'parametre_support',
            entiteId: $reglages->id,
            apres: [
                'canaux' => array_keys(array_filter([
                    'telephone' => $telephone,
                    'whatsapp' => $whatsapp,
                    'email' => $reglages->email,
                ])),
                'videos' => count($reglages->videos ?? []),
            ],
        );

        session()->flash('succes_parametrage', __("Coordonnées du support et vidéos enregistrées."));
    }

    public function ouvrir(int $id): void
    {
        $this->demandeOuverte = $this->demandeOuverte === $id ? null : $id;
        $this->reponse = $this->demandeOuverte
            ? (DemandeSupport::find($id)?->reponse ?? '')
            : '';
    }

    public function changerStatut(int $id, string $statut): void
    {
        Droits::exiger(Modules::MODULE_VERROU, Modules::ECRITURE);

        if (! array_key_exists($statut, DemandeSupport::STATUTS)) {
            return;
        }

        $demande = DemandeSupport::findOrFail($id);
        $avant = $demande->statut;

        $demande->update([
            'statut' => $statut,
            'reponse' => trim($this->reponse) !== '' ? trim($this->reponse) : $demande->reponse,
            'traite_par' => Auth::id(),
            'traite_le' => now(),
        ]);

        AuditLog::enregistrer(
            action: 'traitement_demande_support',
            entite: 'demande_support',
            entiteId: $demande->id,
            avant: ['statut' => $avant],
            apres: ['statut' => $statut],
        );

        session()->flash('succes_parametrage', __("Demande n° :numero : :statut.", [
            'numero' => $demande->id,
            'statut' => __($demande->libelleStatut()),
        ]));
    }

    public function render()
    {
        $demandes = DemandeSupport::with(['auteur', 'traitePar'])
            ->when($this->filtreStatut === 'ouvertes', fn ($q) => $q->where('statut', '!=', 'traitee'))
            ->when(
                array_key_exists($this->filtreStatut, DemandeSupport::STATUTS),
                fn ($q) => $q->where('statut', $this->filtreStatut),
            )
            ->dansLOrdreDeTraitement()
            ->take(50)
            ->get();

        return view('livewire.parametrage.aide-et-support', [
            'demandes' => $demandes,
            'nouvelles' => DemandeSupport::where('statut', 'nouvelle')->count(),
            'sujets' => Aide::SUJETS,
        ]);
    }
}
