<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ce que la maison dit d'elle-même, en un seul endroit.
 *
 * Chaque accesseur retombe sur la valeur d'avant — `config/societe.php`,
 * `config/app.php`, ou le texte qui était écrit en dur dans le gabarit — quand
 * la colonne est vide. L'écran de paramétrage peut donc rester vierge sans que
 * rien ne change : on remplit une ligne le jour où elle doit changer, pas le
 * jour de la mise à jour.
 */
class ParametreSociete extends Model
{
    protected $table = 'parametres_societe';

    protected $fillable = [
        'nom', 'raison_sociale', 'activite', 'oeuvre',
        'rccm', 'ninea',
        'adresse', 'ville', 'pays',
        'telephone', 'whatsapp', 'email', 'site_web', 'horaires',
        'videos',
    ];

    protected function casts(): array
    {
        return ['videos' => 'array'];
    }

    /**
     * Ce qui s'appliquait avant que cet écran n'existe.
     *
     * Les deux premières valeurs étaient écrites dans l'en-tête des PDF, la
     * troisième est la constante du modèle Investisseur : les reprendre ici
     * garantit qu'un champ laissé vide rend exactement la page d'hier.
     */
    private const REPLIS = [
        'raison_sociale' => 'AND DOX S.A.',
        'activite' => 'Plateforme de Gestion des Investissements',
        'oeuvre' => Investisseur::NOM_WAQF_CARITATIF,
    ];

    /** La ligne unique, créée vide au premier appel. */
    public static function actuel(): self
    {
        return static::first() ?? static::create(['videos' => []]);
    }

    /** La valeur réglée, ou celle qui s'appliquait avant. Jamais vide pour ces trois-là. */
    public function valeur(string $champ): ?string
    {
        $pose = trim((string) ($this->{$champ} ?? ''));

        if ($pose !== '') {
            return $pose;
        }

        return match ($champ) {
            'nom' => config('app.name', 'AMANAH'),
            'telephone', 'whatsapp' => \App\Support\Telephone::normaliser(config('societe.whatsapp_support')),
            default => self::REPLIS[$champ] ?? null,
        };
    }

    public function nom(): string
    {
        return $this->valeur('nom') ?? 'AMANAH';
    }

    public function raisonSociale(): ?string
    {
        return $this->valeur('raison_sociale');
    }

    public function activite(): ?string
    {
        return $this->valeur('activite');
    }

    /** Le numéro à qui écrire sur WhatsApp : celui déclaré, à défaut le téléphone. */
    public function numeroWhatsapp(): ?string
    {
        return $this->valeur('whatsapp') ?: $this->valeur('telephone');
    }

    /** L'adresse postale complète, les parties vides retirées. */
    public function adresseComplete(): ?string
    {
        $parties = collect([$this->adresse, $this->ville, $this->pays])
            ->map(fn ($p) => trim((string) $p))
            ->filter();

        return $parties->isEmpty() ? null : $parties->implode(', ');
    }

    /** L'adresse de la vidéo d'un sujet d'aide, ou null si aucune n'est renseignée. */
    public function video(string $sujet): ?string
    {
        $adresse = ($this->videos ?? [])[$sujet] ?? null;

        return is_string($adresse) && trim($adresse) !== '' ? trim($adresse) : null;
    }

    /** Le support est joignable dès qu'un canal est renseigné. */
    public function estJoignable(): bool
    {
        return (bool) ($this->numeroWhatsapp() || $this->valeur('email'));
    }
}
