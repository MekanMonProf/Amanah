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

    /**
     * L'adresse enregistrée pour ce couple sujet / langue, sans repli.
     *
     * C'est ce que l'écran de paramétrage montre dans ses champs : il doit
     * afficher ce qui est posé, pas ce qui serait servi.
     */
    public function video(string $sujet, string $langue): ?string
    {
        $adresse = (($this->videos ?? [])[$sujet] ?? [])[$langue] ?? null;

        return is_string($adresse) && trim($adresse) !== '' ? trim($adresse) : null;
    }

    /**
     * La vidéo à servir sur la page, et dans quelle langue elle est.
     *
     * Faute d'enregistrement dans la langue du lecteur, on sert celui de la
     * langue source, comme le fait déjà le texte de la page. Une démonstration
     * à l'écran reste en grande partie compréhensible sans la bande son : on
     * voit où l'on clique. La page dit alors la langue, pour que personne ne
     * se croie responsable de ne pas comprendre.
     *
     * @return array{adresse: string, langue: string, dansLaLangue: bool}|null
     */
    public function videoPour(string $sujet, ?string $langue = null): ?array
    {
        $langue ??= app()->getLocale();

        if ($adresse = $this->video($sujet, $langue)) {
            return ['adresse' => $adresse, 'langue' => $langue, 'dansLaLangue' => true];
        }

        $source = \App\Support\Aide::LANGUE_SOURCE;

        if ($langue !== $source && $adresse = $this->video($sujet, $source)) {
            return ['adresse' => $adresse, 'langue' => $source, 'dansLaLangue' => false];
        }

        return null;
    }

    /** Le support est joignable dès qu'un canal est renseigné. */
    public function estJoignable(): bool
    {
        return (bool) ($this->numeroWhatsapp() || $this->valeur('email'));
    }
}
