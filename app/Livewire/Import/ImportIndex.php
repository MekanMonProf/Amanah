<?php

namespace App\Livewire\Import;

use App\Models\AuditLog;
use App\Support\ImportateurDonnees;
use App\Support\LecteurTableur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Reprise de l'existant : téléverser un fichier Excel/CSV, contrôler ligne par ligne
 * ce qu'il contient, puis enregistrer seulement après validation à l'écran.
 *
 * L'analyse complète est déposée dans un fichier de travail côté serveur (jeton) et
 * non dans une propriété publique : un fichier de plusieurs milliers de lignes ferait
 * autrement transiter tout le contenu à chaque interaction Livewire.
 */
#[Layout('layouts.app')]
class ImportIndex extends Component
{
    use WithFileUploads;

    /** Dossier des analyses en attente de confirmation, et leur durée de vie. */
    protected const DOSSIER_TRAVAIL = 'imports-en-attente';

    protected const DUREE_ANALYSE_HEURES = 6;

    /** Nombre de lignes affichées dans le tableau de contrôle. */
    protected const LIGNES_AFFICHEES = 200;

    public string $type = 'investisseurs';

    public $fichier = null;

    public ?string $jetonAnalyse = null;
    public ?string $nomFichier = null;
    public ?string $erreurLecture = null;

    public array $resume = [];
    public array $apercu = [];
    public array $colonnesInconnues = [];
    public array $colonnesManquantes = [];
    public bool $apercuTronque = false;

    public bool $confirmeIgnorerErreurs = false;

    public array $rapport = [];
    public array $motsDePasse = [];

    public function changerType(string $type): void
    {
        if (! array_key_exists($type, ImportateurDonnees::TYPES)) {
            return;
        }

        $this->type = $type;
        $this->reinitialiser();
    }

    public function reinitialiser(): void
    {
        $this->oublierAnalyse();

        $this->reset([
            'fichier', 'jetonAnalyse', 'nomFichier', 'erreurLecture', 'resume', 'apercu',
            'colonnesInconnues', 'colonnesManquantes', 'apercuTronque', 'confirmeIgnorerErreurs',
            'rapport', 'motsDePasse',
        ]);

        $this->resetErrorBag();
    }

    /** Le fichier est analysé dès son dépôt : l'utilisateur voit le verdict sans second clic. */
    public function updatedFichier(): void
    {
        $this->analyser();
    }

    public function analyser(): void
    {
        $this->reset(['jetonAnalyse', 'erreurLecture', 'resume', 'apercu', 'colonnesInconnues', 'colonnesManquantes', 'apercuTronque', 'confirmeIgnorerErreurs', 'rapport', 'motsDePasse']);

        $this->validate([
            'fichier' => 'required|file|max:10240',
        ], [
            'fichier.required' => 'Choisissez un fichier à importer.',
            'fichier.max' => 'Le fichier ne doit pas dépasser 10 Mo.',
        ]);

        $this->nomFichier = $this->fichier->getClientOriginalName();

        try {
            $contenu = LecteurTableur::lire(
                $this->fichier->getRealPath(),
                $this->fichier->getClientOriginalExtension()
            );
        } catch (\RuntimeException $exception) {
            $this->erreurLecture = $exception->getMessage();

            return;
        }

        $contenu = ImportateurDonnees::appliquerAlias($this->type, $contenu);

        $schema = ImportateurDonnees::schema($this->type);
        $this->colonnesInconnues = array_values(array_diff($contenu['entetes'], array_keys($schema)));
        $this->colonnesManquantes = array_values(array_diff(ImportateurDonnees::colonnesObligatoires($this->type), $contenu['entetes']));

        if ($this->colonnesManquantes !== []) {
            $this->erreurLecture = sprintf(
                'Colonne(s) obligatoire(s) absente(s) du fichier : %s. Téléchargez le modèle ci-dessus pour retrouver les en-têtes attendus.',
                implode(', ', $this->colonnesManquantes)
            );

            return;
        }

        $analyse = (new ImportateurDonnees())->analyser($this->type, $contenu['lignes']);

        $this->jetonAnalyse = (string) str()->uuid();
        $this->deposerAnalyse($this->jetonAnalyse, [
            'type' => $this->type,
            'nom_fichier' => $this->nomFichier,
            'lignes' => $analyse,
        ]);

        $this->resume = [
            'total' => count($analyse),
            'valides' => count(array_filter($analyse, fn ($l) => $l['statut'] === 'valide')),
            'erreurs' => count(array_filter($analyse, fn ($l) => $l['statut'] === 'erreur')),
            'doublons' => count(array_filter($analyse, fn ($l) => $l['statut'] === 'doublon')),
        ];

        $this->apercu = $this->construireApercu($analyse);
    }

    /**
     * Toutes les lignes à problème sont montrées (c'est ce qu'on doit corriger), complétées
     * par un échantillon de lignes correctes pour vérifier que les colonnes tombent en face.
     */
    protected function construireApercu(array $analyse): array
    {
        $problemes = array_values(array_filter($analyse, fn ($l) => $l['statut'] !== 'valide'));
        $valides = array_values(array_filter($analyse, fn ($l) => $l['statut'] === 'valide'));

        $selection = array_merge(
            array_slice($problemes, 0, static::LIGNES_AFFICHEES),
            array_slice($valides, 0, max(0, static::LIGNES_AFFICHEES - count($problemes)))
        );

        $this->apercuTronque = count($selection) < count($analyse);

        usort($selection, fn ($a, $b) => $a['numero'] <=> $b['numero']);

        $colonnes = ImportateurDonnees::colonnesApercu($this->type);

        return array_map(fn ($ligne) => [
            'numero' => $ligne['numero'],
            'statut' => $ligne['statut'],
            'messages' => $ligne['messages'],
            'cellules' => array_map(fn ($colonne) => (string) ($ligne['donnees'][$colonne] ?? ''), $colonnes),
        ], $selection);
    }

    public function importer(): void
    {
        if (! $this->jetonAnalyse) {
            return;
        }

        $analyse = $this->relireAnalyse($this->jetonAnalyse);

        if (! $analyse || $analyse['type'] !== $this->type) {
            $this->erreurLecture = 'Le contrôle du fichier a expiré. Redéposez le fichier pour relancer l\'analyse.';
            $this->reset(['jetonAnalyse', 'resume', 'apercu']);

            return;
        }

        if (($this->resume['erreurs'] ?? 0) > 0 && ! $this->confirmeIgnorerErreurs) {
            $this->addError('confirmeIgnorerErreurs', 'Cochez la case pour confirmer que les lignes en erreur seront ignorées.');

            return;
        }

        if (($this->resume['valides'] ?? 0) === 0) {
            $this->addError('confirmeIgnorerErreurs', 'Aucune ligne valide à importer.');

            return;
        }

        $resultat = DB::transaction(fn () => (new ImportateurDonnees())->importer($this->type, $analyse['lignes']));

        AuditLog::enregistrer(
            action: 'import',
            entite: $this->entiteAuditee(),
            apres: [
                'fichier' => $analyse['nom_fichier'],
                'lignes_lues' => $this->resume['total'],
                'lignes_importees' => $resultat['importees'],
                'lignes_en_erreur' => $this->resume['erreurs'],
                'doublons_ignores' => $this->resume['doublons'],
                'exemples' => array_slice($resultat['identifiants'], 0, 20),
            ],
        );

        $this->oublierAnalyse();

        $this->rapport = [
            'type' => $this->type,
            'fichier' => $analyse['nom_fichier'],
            'importees' => $resultat['importees'],
            'ignorees' => $resultat['ignorees'],
            'erreurs' => $this->resume['erreurs'],
            'doublons' => $this->resume['doublons'],
        ];

        $this->motsDePasse = $resultat['mots_de_passe'];

        $this->reset(['fichier', 'jetonAnalyse', 'resume', 'apercu', 'confirmeIgnorerErreurs']);
    }

    protected function entiteAuditee(): string
    {
        return match ($this->type) {
            'gestionnaires' => 'gestionnaire',
            'investisseurs' => 'investisseur',
            'achats' => 'achat',
            'ecritures' => 'ecriture',
        };
    }

    /**
     * L'analyse dort dans un fichier de travail plutôt qu'en cache : le cache est stocké
     * en base ici, et un gros fichier dépasserait vite la taille maximale d'une requête MySQL.
     */
    protected function cheminAnalyse(string $jeton): string
    {
        return static::DOSSIER_TRAVAIL . '/' . auth()->id() . '-' . $jeton . '.json';
    }

    protected function deposerAnalyse(string $jeton, array $analyse): void
    {
        $this->purgerAnalysesPerimees();

        Storage::disk('local')->put($this->cheminAnalyse($jeton), json_encode($analyse, JSON_UNESCAPED_UNICODE));
    }

    protected function relireAnalyse(string $jeton): ?array
    {
        $chemin = $this->cheminAnalyse($jeton);

        if (! Storage::disk('local')->exists($chemin)) {
            return null;
        }

        return json_decode(Storage::disk('local')->get($chemin), true);
    }

    protected function oublierAnalyse(): void
    {
        if ($this->jetonAnalyse) {
            Storage::disk('local')->delete($this->cheminAnalyse($this->jetonAnalyse));
        }
    }

    /** Une analyse abandonnée (onglet fermé sans valider) ne doit pas rester indéfiniment. */
    protected function purgerAnalysesPerimees(): void
    {
        $disque = Storage::disk('local');
        $limite = now()->subHours(static::DUREE_ANALYSE_HEURES)->getTimestamp();

        foreach ($disque->files(static::DOSSIER_TRAVAIL) as $fichier) {
            if ($disque->lastModified($fichier) < $limite) {
                $disque->delete($fichier);
            }
        }
    }

    public function render()
    {
        return view('livewire.import.import-index', [
            'types' => ImportateurDonnees::TYPES,
            'schema' => ImportateurDonnees::schema($this->type),
            'colonnesApercu' => ImportateurDonnees::colonnesApercu($this->type),
        ]);
    }
}
