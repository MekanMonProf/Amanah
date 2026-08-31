<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Don extends Model
{
    protected $fillable = [
        'compte_source_id', 'compte_destinataire_id', 'type_don', 'type_operation', 'heritier_id',
        'nombre_actions', 'prix_unitaire_action', 'montant',
        'date_don', 'motif', 'piece_justificative_path', 'created_by',
    ];

    protected $casts = [
        'date_don' => 'date',
    ];

    public function compteSource()
    {
        return $this->belongsTo(CompteInvestissement::class, 'compte_source_id');
    }

    public function compteDestinataire()
    {
        return $this->belongsTo(CompteInvestissement::class, 'compte_destinataire_id');
    }
}
