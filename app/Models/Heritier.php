<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Heritier extends Model
{
    protected $fillable = [
        'investisseur_id', 'investisseur_heritier_id', 'nom', 'prenom', 'telephone',
        'lien_parente', 'part_pourcentage', 'piece_identite_path',
        'piece_justificative_path', 'piece_certificat_heredite_path',
    ];

    public function defunt()
    {
        return $this->belongsTo(Investisseur::class, 'investisseur_id');
    }

    public function investisseurHeritier()
    {
        return $this->belongsTo(Investisseur::class, 'investisseur_heritier_id');
    }
}
