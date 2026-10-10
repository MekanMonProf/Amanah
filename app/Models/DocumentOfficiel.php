<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Un document que la direction adresse à tous : circulaire, rapport,
 * procès-verbal.
 *
 * Il n'appartient à personne en particulier — c'est ce qui le distingue des
 * pièces d'un dossier. Tout compte connecté peut l'ouvrir ; seuls la direction
 * et l'administrateur peuvent en déposer ou en retirer.
 */
class DocumentOfficiel extends Model
{
    protected $table = 'documents_officiels';

    protected $fillable = [
        'titre', 'description', 'fichier_path', 'nom_fichier',
        'taille', 'publie_par', 'date_document',
    ];

    protected $casts = [
        'date_document' => 'date',
        'taille' => 'integer',
    ];

    /** Le disque privé, hors de portée du serveur web. */
    public const DISQUE = 'local';

    /** Le dossier où les fichiers se rangent, à l'écart des pièces de dossier. */
    public const DOSSIER = 'documents-officiels';

    /**
     * Les rôles qui publient.
     *
     * La direction est l'auteur de ces documents, l'administrateur celui qui
     * tient la plateforme au quotidien : réserver le dépôt à l'un bloquerait
     * l'autre.
     */
    public const ROLES_DEPOSANTS = ['direction', 'administrateur'];

    public function auteur()
    {
        return $this->belongsTo(User::class, 'publie_par');
    }

    /** Qui peut déposer ou retirer. La lecture, elle, est ouverte à tous. */
    public static function peutPublier(?User $utilisateur = null): bool
    {
        $utilisateur ??= Auth::user();

        return $utilisateur !== null && in_array($utilisateur->role, self::ROLES_DEPOSANTS, true);
    }

    /** Le poids en clair, pour l'annoncer avant le clic. */
    public function tailleLisible(): string
    {
        $octets = (int) $this->taille;

        if ($octets < 1024) {
            return __(':nombre o', ['nombre' => $octets]);
        }

        if ($octets < 1024 * 1024) {
            return __(':nombre Ko', ['nombre' => \App\Support\Montant::format($octets / 1024)]);
        }

        return __(':nombre Mo', ['nombre' => \App\Support\Montant::format($octets / 1048576, 1)]);
    }

    /** L'extension, pour dire de quelle sorte de pièce il s'agit. */
    public function extension(): string
    {
        return strtoupper(pathinfo($this->nom_fichier, PATHINFO_EXTENSION)) ?: '—';
    }

    /**
     * Le fichier part avec la ligne : laisser l'un sans l'autre remplirait le
     * disque de pièces que plus rien ne nomme.
     */
    protected static function booted(): void
    {
        static::deleted(function (self $document) {
            Storage::disk(self::DISQUE)->delete($document->fichier_path);
        });
    }
}
