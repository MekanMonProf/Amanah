<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParametreDividende extends Model
{
    protected $table = 'parametres_dividendes';

    protected $fillable = ['delai_eligibilite_jours', 'delai_radiation_jours'];

    /**
     * Récupère la ligne unique de paramétrage (la crée avec les valeurs par défaut si absente).
     */
    public static function actuel(): self
    {
        return static::first() ?? static::create([
            'delai_eligibilite_jours' => 0,
            'delai_radiation_jours' => 31,
        ]);
    }
}
