<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandeSupport extends Model
{
    protected $table = 'demandes_support';

    protected $fillable = [
        'user_id', 'role', 'categorie', 'sujet', 'message', 'url_origine',
        'statut', 'reponse', 'traite_par', 'traite_le',
    ];

    protected function casts(): array
    {
        return ['traite_le' => 'datetime'];
    }

    /**
     * Les familles proposées au formulaire.
     *
     * Elles servent à trier la file, pas à router la demande : cinq cases que
     * tout le monde comprend valent mieux qu'une arborescence où l'on hésite.
     */
    public const CATEGORIES = [
        'question' => "Une question sur le fonctionnement",
        'anomalie' => "Quelque chose ne marche pas",
        'correction' => "Une donnée à corriger",
        'acces' => "Un problème d'accès ou de mot de passe",
        'autre' => "Autre chose",
    ];

    public const STATUTS = [
        'nouvelle' => 'Nouvelle',
        'en_cours' => 'En cours',
        'traitee' => 'Traitée',
    ];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    public function estOuverte(): bool
    {
        return $this->statut !== 'traitee';
    }

    public function libelleCategorie(): string
    {
        return self::CATEGORIES[$this->categorie] ?? $this->categorie;
    }

    public function libelleStatut(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    /** Les nouvelles d'abord, les plus anciennes en tête de chaque paquet. */
    public function scopeDansLOrdreDeTraitement($requete)
    {
        return $requete
            ->orderByRaw("FIELD(statut, 'nouvelle', 'en_cours', 'traitee')")
            ->orderBy('created_at');
    }
}
