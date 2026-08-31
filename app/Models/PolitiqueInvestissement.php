<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PolitiqueInvestissement extends Model
{
    protected $table = 'politiques_investissement';

    protected $fillable = [
        'categorie', 'eligible_dividendes', 'reinvestissement_par_defaut',
        'versement_dividendes_possible', 'versement_capital_radiation_possible',
        'cession_autorisee', 'radiation_autorisee', 'prix_unitaire_action',
    ];

    protected $casts = [
        'eligible_dividendes' => 'boolean',
        'reinvestissement_par_defaut' => 'boolean',
        'versement_dividendes_possible' => 'boolean',
        'versement_capital_radiation_possible' => 'boolean',
        'cession_autorisee' => 'boolean',
        'radiation_autorisee' => 'boolean',
        'prix_unitaire_action' => 'decimal:2',
    ];
}
