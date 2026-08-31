<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AchatAction extends Model
{
    use HasFactory;

    protected $table = 'achats_actions';

    protected $fillable = [
        'compte_id', 'numero_achat', 'date_achat', 'type_achat', 'nombre_actions',
        'prix_unitaire', 'montant', 'mode_paiement', 'reference_facture',
        'photo_facture_path', 'observations', 'saisi_par',
    ];

    protected $casts = ['date_achat' => 'date'];

    public function compte()
    {
        return $this->belongsTo(CompteInvestissement::class, 'compte_id');
    }
}
