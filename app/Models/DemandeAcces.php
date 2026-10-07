<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandeAcces extends Model
{
    protected $table = 'demandes_acces';

    protected $fillable = ['investisseur_id', 'telephone', 'statut', 'traite_par', 'traite_le'];

    protected function casts(): array
    {
        return ['traite_le' => 'datetime'];
    }

    public function investisseur(): BelongsTo
    {
        return $this->belongsTo(Investisseur::class);
    }

    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    public function scopeEnAttente(Builder $requete): Builder
    {
        return $requete->where('statut', 'nouvelle');
    }

    /**
     * Les demandes qu'un gestionnaire a à traiter.
     *
     * Un gestionnaire ne voit que son portefeuille, comme partout ailleurs ;
     * la direction et l'administration voient tout.
     */
    public function scopePourLUtilisateur(Builder $requete, ?User $utilisateur): Builder
    {
        if ($utilisateur?->role !== 'gestionnaire') {
            return $requete;
        }

        return $requete->whereHas(
            'investisseur',
            fn (Builder $q) => $q->where('gestionnaire_id', $utilisateur->gestionnaire?->id),
        );
    }

    /**
     * Enregistre la demande, sauf si le dossier en a déjà une en attente.
     *
     * Quelqu'un qui insiste ne doit pas remplir la file de doublons : la
     * deuxième demande ne dit rien de plus que la première, elle dit seulement
     * qu'on attend toujours.
     */
    public static function deposer(Investisseur $investisseur, string $telephone): self
    {
        $enCours = static::enAttente()->where('investisseur_id', $investisseur->id)->first();

        if ($enCours) {
            $enCours->touch();

            return $enCours;
        }

        return static::create([
            'investisseur_id' => $investisseur->id,
            'telephone' => $telephone,
            'statut' => 'nouvelle',
        ]);
    }

    /** Le gestionnaire a réinitialisé l'accès : la demande n'a plus lieu d'être. */
    public static function clore(Investisseur $investisseur, ?int $parUtilisateur): void
    {
        static::enAttente()
            ->where('investisseur_id', $investisseur->id)
            ->update([
                'statut' => 'traitee',
                'traite_par' => $parUtilisateur,
                'traite_le' => now(),
            ]);
    }
}
