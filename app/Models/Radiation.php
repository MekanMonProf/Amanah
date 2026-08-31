<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Radiation extends Model
{
    protected $fillable = [
        'compte_id', 'numero_radiation', 'date_radiation', 'nombre_actions_radiees',
        'prix_unitaire_action', 'montant_total', 'mode_paiement', 'reference_facture',
        'mois_previsionnel_paiement', 'piece_justificative_path', 'observations',
    ];

    protected $casts = [
        'date_radiation' => 'date',
        'mois_previsionnel_paiement' => 'date',
    ];

    public function compte()
    {
        return $this->belongsTo(CompteInvestissement::class, 'compte_id');
    }
}
