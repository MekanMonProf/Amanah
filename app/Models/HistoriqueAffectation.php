<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoriqueAffectation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'investisseur_id', 'ancien_gestionnaire_id', 'nouveau_gestionnaire_id',
        'date_transfert', 'motif', 'effectue_par',
    ];

    protected $casts = ['date_transfert' => 'date'];

    public function investisseur()
    {
        return $this->belongsTo(Investisseur::class);
    }
}
