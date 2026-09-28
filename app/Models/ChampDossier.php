<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un champ que le dossier investisseur doit contenir pour être dit complet.
 *
 * Voir App\Support\Completude pour le catalogue des champs proposés et la
 * lecture de cette table ; l'écran de paramétrage n'y coche que des lignes.
 */
class ChampDossier extends Model
{
    protected $table = 'champs_dossier';

    protected $fillable = ['contexte', 'champ', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }
}
