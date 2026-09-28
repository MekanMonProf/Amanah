<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Le niveau d'accès d'un rôle à un module. Voir App\Support\Modules pour le
 * catalogue, et App\Support\Droits pour la lecture de cette table.
 */
class Permission extends Model
{
    protected $fillable = ['role', 'module', 'niveau'];
}
