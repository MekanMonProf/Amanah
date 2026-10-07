<?php

namespace App\Support;

/**
 * Ramène une adresse de vidéo à la forme qui s'intègre dans une page.
 *
 * YouTube donne trois adresses pour la même vidéo, selon l'endroit où on la
 * copie : celle de la barre d'adresse (`watch?v=…`), celle du bouton Partager
 * (`youtu.be/…`) et celle du menu Intégrer (`embed/…`). Seule la dernière
 * s'affiche dans une iframe ; les deux autres donnent un cadre noir. Rien ne
 * le laisse deviner, et c'est précisément la première qu'on a sous la main.
 *
 * On les accepte donc toutes les trois et on les convertit, plutôt que de
 * rejeter deux adresses sur trois en demandant à l'utilisateur d'aller
 * chercher la bonne.
 *
 * Une adresse qui n'est pas de YouTube ressort inchangée : l'application ne
 * prétend pas connaître tous les hébergeurs, et un lien d'intégration Vimeo ou
 * d'un serveur interne doit pouvoir passer tel quel.
 */
class AdresseVideo
{
    /**
     * Les hôtes de YouTube, et ce qu'ils signifient.
     *
     * `youtube-nocookie.com` est conservé quand il est donné : c'est le même
     * lecteur sans le pistage, et le choix appartient à qui a collé l'adresse.
     */
    private const HOTES = [
        'youtube.com' => 'www.youtube.com',
        'www.youtube.com' => 'www.youtube.com',
        'm.youtube.com' => 'www.youtube.com',
        'music.youtube.com' => 'www.youtube.com',
        'youtu.be' => 'www.youtube.com',
        'youtube-nocookie.com' => 'www.youtube-nocookie.com',
        'www.youtube-nocookie.com' => 'www.youtube-nocookie.com',
    ];

    /** Les segments de chemin qui précèdent l'identifiant, selon la forme. */
    private const SEGMENTS = ['embed', 'shorts', 'live', 'v'];

    public static function normaliser(?string $adresse): ?string
    {
        $adresse = trim((string) $adresse);

        if ($adresse === '') {
            return null;
        }

        $parties = parse_url($adresse);
        $hote = strtolower($parties['host'] ?? '');

        if (! isset(self::HOTES[$hote])) {
            return $adresse;
        }

        $identifiant = self::identifiant($hote, $parties);

        if ($identifiant === null) {
            return $adresse;
        }

        $url = 'https://' . self::HOTES[$hote] . '/embed/' . $identifiant;

        // Un instant de départ se perd en changeant de forme : `t=90s` dans
        // l'adresse de partage devient `start=90` dans celle d'intégration.
        $depart = self::secondes($parties['query'] ?? '');

        return $depart > 0 ? $url . '?start=' . $depart : $url;
    }

    /** L'identifiant de la vidéo, ou null si l'adresse n'en porte pas. */
    private static function identifiant(string $hote, array $parties): ?string
    {
        $chemin = trim($parties['path'] ?? '', '/');
        $segments = $chemin === '' ? [] : explode('/', $chemin);

        // youtu.be/ID : l'identifiant est le chemin entier.
        if ($hote === 'youtu.be') {
            return self::valide($segments[0] ?? '');
        }

        // youtube.com/embed/ID, /shorts/ID, /live/ID
        if (count($segments) >= 2 && in_array($segments[0], self::SEGMENTS, true)) {
            return self::valide($segments[1]);
        }

        // youtube.com/watch?v=ID
        parse_str($parties['query'] ?? '', $requete);

        return self::valide($requete['v'] ?? '');
    }

    /**
     * Un identifiant YouTube fait onze caractères parmi lettres, chiffres,
     * tiret et souligné. On reste un peu large plutôt que de refuser une
     * vidéo valide sur une longueur qui aurait changé.
     */
    private static function valide(string $candidat): ?string
    {
        return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $candidat) === 1 ? $candidat : null;
    }

    /** Les secondes portées par `t` ou `start` : « 90 », « 90s », « 1m30s ». */
    private static function secondes(string $requete): int
    {
        parse_str($requete, $parametres);

        $brut = (string) ($parametres['start'] ?? $parametres['t'] ?? '');

        if ($brut === '') {
            return 0;
        }

        if (ctype_digit($brut)) {
            return (int) $brut;
        }

        if (preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/', $brut, $m) !== 1) {
            return 0;
        }

        return (int) ($m[1] ?? 0) * 3600 + (int) ($m[2] ?? 0) * 60 + (int) ($m[3] ?? 0);
    }
}
