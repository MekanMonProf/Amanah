<?php

namespace App\Livewire\Parametrage;

use App\Models\AuditLog;
use App\Models\DemandeSupport;
use App\Models\ParametreSociete;
use App\Support\AdresseVideo;
use App\Support\Aide;
use App\Support\Droits;
use App\Support\Modules;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Les vidéos du mode d'emploi, et la file des demandes.
 *
 * Les coordonnées ne sont plus ici : ce sont celles de la maison, et elles ont
 * rejoint l'onglet « La société » avec le reste de son identité. Un numéro de
 * support rangé à part de l'adresse et de la raison sociale finissait par être
 * changé d'un côté seulement.
 */
class AideEtSupport extends Component
{
    /**
     * Les adresses de la langue en cours d'édition. code du sujet => adresse.
     *
     * Trente champs à l'écran — dix sujets fois trois langues — se liraient mal
     * et se rempliraient moins bien. On en montre dix, et on change de langue.
     */
    public array $videos = [];

    public string $langueVideos = \App\Support\Aide::LANGUE_SOURCE;

    public string $filtreStatut = 'ouvertes';

    public ?int $demandeOuverte = null;

    public string $reponse = '';

    public function mount(): void
    {
        Droits::exiger(Modules::MODULE_VERROU, Modules::ECRITURE);

        $this->chargerVideos();
    }

    private function chargerVideos(): void
    {
        $reglages = ParametreSociete::actuel();

        foreach (array_keys(Aide::SUJETS) as $code) {
            $this->videos[$code] = $reglages->video($code, $this->langueVideos) ?? '';
        }
    }

    /**
     * Changer de langue recharge les champs depuis la base.
     *
     * Les modifications non enregistrées sont donc perdues — c'est voulu :
     * les garder en mémoire laisserait croire qu'elles sont posées, et on
     * repartirait en croyant avoir enregistré trois langues sur une.
     */
    public function changerLangue(string $langue): void
    {
        if (! \App\Support\Langue::estValide($langue)) {
            return;
        }

        $this->langueVideos = $langue;
        $this->resetValidation();
        $this->chargerVideos();
    }

    protected function rules(): array
    {
        return ['videos.*' => ['nullable', 'url', 'max:500']];
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

        $reglages = ParametreSociete::actuel();

        // On repart des adresses déjà en base : l'écran ne montre qu'une langue,
        // enregistrer ne doit pas effacer les deux autres.
        $videos = $reglages->videos ?? [];

        foreach ($this->videos as $code => $adresse) {
            // Les trois formes d'adresse YouTube sont acceptées et ramenées à
            // celle qui s'intègre : celle de la barre d'adresse et celle du
            // bouton Partager donneraient un cadre noir, sans que rien ne le dise.
            $normalisee = AdresseVideo::normaliser($adresse);

            $pourLeSujet = $videos[$code] ?? [];

            if ($normalisee === null) {
                unset($pourLeSujet[$this->langueVideos]);
            } else {
                $pourLeSujet[$this->langueVideos] = $normalisee;
            }

            if ($pourLeSujet === []) {
                unset($videos[$code]);
            } else {
                $videos[$code] = $pourLeSujet;
            }
        }

        $reglages->update(['videos' => $videos]);

        // Le champ montre ce qui a été enregistré : la conversion se voit,
        // elle ne se devine pas.
        foreach (array_keys($this->videos) as $code) {
            $this->videos[$code] = ($videos[$code] ?? [])[$this->langueVideos] ?? '';
        }

        AuditLog::enregistrer(
            action: 'modification_videos_aide',
            entite: 'parametre_societe',
            entiteId: $reglages->id,
            apres: ['videos' => count($reglages->videos ?? [])],
        );

        session()->flash('succes_parametrage', __("Vidéos du mode d'emploi enregistrées."));
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
            'reglages' => ParametreSociete::actuel(),
            'demandes' => $demandes,
            'nouvelles' => DemandeSupport::where('statut', 'nouvelle')->count(),
            'sujets' => Aide::SUJETS,
        ]);
    }
}
