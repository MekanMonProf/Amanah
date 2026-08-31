<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dividende extends Model
{
    protected $fillable = [
        'compte_id', 'periode', 'nombre_actions', 'benefice_par_action',
        'montant_calcule', 'statut',
    ];

    protected $casts = ['periode' => 'date'];

    public function compte()
    {
        return $this->belongsTo(CompteInvestissement::class, 'compte_id');
    }
}
