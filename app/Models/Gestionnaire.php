<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gestionnaire extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function investisseurs()
    {
        return $this->hasMany(Investisseur::class);
    }

    /**
     * Un gestionnaire dont le compte de connexion a disparu.
     *
     * La fiche gestionnaire et le compte utilisateur sont deux lignes distinctes :
     * supprimer le second laisse la premiere derriere lui. Cela n a rien de normal,
     * mais l application doit continuer de s afficher plutot que de tomber en
     * erreur — un investisseur reste rattache a cette fiche, et on doit pouvoir
     * le reassigner.
     */
    public function estOrphelin(): bool
    {
        return $this->user === null;
    }

    /**
     * Nom a afficher. Jamais nul : une fiche orpheline se signale d elle-meme
     * plutot que de laisser une case vide, qui se lirait comme une donnee
     * manquante alors que c est le compte entier qui manque.
     */
    public function nomComplet(): string
    {
        if ($this->estOrphelin()) {
            return __('Compte supprimé (fiche #:id)', ['id' => $this->id]);
        }

        return trim($this->user->nom . ' ' . $this->user->prenom);
    }

    /**
     * Fiches encore utilisables : celles qui ont un compte de connexion. Une
     * fiche orpheline ne doit pas etre proposee dans une liste de choix — on ne
     * peut ni la contacter, ni s y connecter.
     */
    public function scopeAvecCompte($requete)
    {
        return $requete->whereHas('user');
    }
}
