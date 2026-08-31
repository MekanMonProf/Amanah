<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcritureCompteFinancier extends Model
{
    protected $table = 'ecritures_compte_financier';

    public $timestamps = false; // uniquement created_at, pas de modification possible

    protected $fillable = [
        'compte_id', 'type_ecriture', 'montant', 'solde_apres',
        'reference_type', 'reference_id', 'date_ecriture', 'observations',
        'piece_justificative_path', 'created_by',
    ];

    protected $casts = ['date_ecriture' => 'date'];

    public function compte()
    {
        return $this->belongsTo(CompteInvestissement::class, 'compte_id');
    }

    // Volontairement : aucune méthode update() n'est exposée pour le solde —
    // toute correction doit passer par une nouvelle écriture de type "ajustement".
}
