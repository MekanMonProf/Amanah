<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BaremeDividende extends Model
{
    protected $table = 'baremes_dividendes';

    protected $fillable = ['periode', 'categorie', 'benefice_par_action', 'fixe_par'];

    protected $casts = [
        'periode' => 'date',
        // Huit décimales : le taux est un quotient (bénéfice de la période / actions
        // en circulation), il tombe rarement rond — voir la migration du 04/10/2026.
        'benefice_par_action' => 'decimal:8',
    ];
}
