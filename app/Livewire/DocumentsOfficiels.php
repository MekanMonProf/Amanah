<?php

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\DocumentOfficiel;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Les documents que la direction adresse à tous.
 *
 * Un seul écran pour les deux usages : la liste, que tout compte connecté peut
 * lire, et le formulaire de dépôt, que seuls la direction et l'administrateur
 * voient. Séparer les deux aurait obligé le publiant à changer d'écran pour
 * vérifier ce qu'il venait de publier.
 */
#[Layout('layouts.app')]
class DocumentsOfficiels extends Component
{
    use WithFileUploads;

    public bool $afficherFormulaire = false;

    #[Validate('required|string|max:200')]
    public string $titre = '';

    #[Validate('nullable|string|max:2000')]
    public string $description = '';

    #[Validate('nullable|date')]
    public string $dateDocument = '';

    /**
     * Dix méga-octets, et les formats qu'on lit sans logiciel particulier.
     *
     * Le PDF d'abord : c'est la forme d'un document officiel, celle qui
     * s'affiche à l'identique partout. Les images servent aux pièces
     * photographiées, et les formats bureautiques à ce qui circule encore
     * ainsi.
     */
    #[Validate('required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx')]
    public $fichier = null;

    public function ouvrirFormulaire(): void
    {
        $this->interdireSiPasDeposant();

        $this->reset(['titre', 'description', 'dateDocument', 'fichier']);
        $this->resetErrorBag();
        $this->afficherFormulaire = true;
    }

    public function publier(): void
    {
        $this->interdireSiPasDeposant();
        $this->validate();

        // Le nom d'origine est conservé à part : le chemin, lui, est tiré au
        // hasard. Deux circulaires nommées « circulaire.pdf » ne doivent pas
        // s'écraser, et le nom rendu au téléchargement reste celui de l'auteur.
        $chemin = $this->fichier->store(DocumentOfficiel::DOSSIER, DocumentOfficiel::DISQUE);

        $document = DocumentOfficiel::create([
            'titre' => $this->titre,
            'description' => $this->description ?: null,
            'fichier_path' => $chemin,
            'nom_fichier' => $this->fichier->getClientOriginalName(),
            'taille' => $this->fichier->getSize(),
            'publie_par' => Auth::id(),
            'date_document' => $this->dateDocument ?: null,
        ]);

        AuditLog::enregistrer(
            action: 'publication_document',
            entite: 'document_officiel',
            entiteId: $document->id,
            apres: ['titre' => $document->titre, 'fichier' => $document->nom_fichier],
        );

        $this->reset(['titre', 'description', 'dateDocument', 'fichier', 'afficherFormulaire']);
        session()->flash('succes_document', __("Document publié. Il est visible par tous les gestionnaires et investisseurs."));
    }

    public function retirer(int $documentId): void
    {
        $this->interdireSiPasDeposant();

        $document = DocumentOfficiel::findOrFail($documentId);

        AuditLog::enregistrer(
            action: 'retrait_document',
            entite: 'document_officiel',
            entiteId: $document->id,
            avant: ['titre' => $document->titre, 'fichier' => $document->nom_fichier],
        );

        // Le fichier suit la ligne : voir DocumentOfficiel::booted().
        $document->delete();

        session()->flash('succes_document', __("Document retiré. Il n'est plus visible par personne."));
    }

    /**
     * La vue cache déjà ce que l'un ne peut pas faire ; ce garde-fou vise
     * l'appel direct, qui ne passe pas par elle.
     */
    private function interdireSiPasDeposant(): void
    {
        abort_unless(DocumentOfficiel::peutPublier(), 403, __("Seules la direction et l'administration publient des documents officiels."));
    }

    public function render()
    {
        return view('livewire.documents-officiels', [
            'documents' => DocumentOfficiel::with('auteur')->latest()->get(),
            'peutPublier' => DocumentOfficiel::peutPublier(),
        ]);
    }
}
