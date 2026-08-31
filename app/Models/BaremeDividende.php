<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BaremeDividende extends Model
{
    protected $table = 'baremes_dividendes';

    protected $fillable = ['periode', 'categorie', 'benefice_par_action', 'fixe_par'];

    protected $casts = [
        'periode' => 'date',
        'benefice_par_action' => 'decimal:4',
    ];
}
