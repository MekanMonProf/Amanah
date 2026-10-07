<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParametreSupport extends Model
{
    protected $table = 'parametres_support';

    protected $fillable = ['telephone', 'whatsapp', 'email', 'horaires', 'videos'];

    protected function casts(): array
    {
        return ['videos' => 'array'];
    }

    /** La ligne unique de réglage, créée vide au premier appel. */
    public static function actuel(): self
    {
        return static::first() ?? static::create(['videos' => []]);
    }

    /** Le numéro à qui écrire sur WhatsApp : celui déclaré, à défaut le téléphone. */
    public function numeroWhatsapp(): ?string
    {
        return $this->whatsapp ?: $this->telephone;
    }

    /** L'adresse de la vidéo d'un sujet d'aide, ou null si aucune n'est renseignée. */
    public function video(string $sujet): ?string
    {
        $adresse = ($this->videos ?? [])[$sujet] ?? null;

        return is_string($adresse) && trim($adresse) !== '' ? trim($adresse) : null;
    }

    /** Le support est joignable dès qu'un canal est renseigné. */
    public function estRenseigne(): bool
    {
        return (bool) ($this->numeroWhatsapp() || $this->email);
    }
}
