<?php

namespace App\Support\Piece;

use Carbon\Carbon;

/**
 * Lecture de la bande MRZ d'une pièce d'identité.
 *
 * La MRZ — les deux ou trois lignes en caractères à chasse fixe au bas d'un
 * passeport ou d'une carte biométrique CEDEAO — est le seul endroit d'une pièce
 * conçu pour être lu par une machine. Surtout, chaque champ y porte une clé de
 * contrôle : on peut donc vérifier ce qu'on a lu au lieu de l'espérer.
 *
 * C'est décisif ici. Sur un dossier d'identification, une date de naissance mal
 * déchiffrée est pire qu'une case vide : elle a l'air d'une donnée. Tout champ
 * dont la clé ne tombe pas juste est donc écarté, pas proposé.
 *
 * Deux formats sont reconnus, les seuls courants au Sénégal :
 *   TD3 — passeport, 2 lignes de 44 caractères
 *   TD1 — carte d'identité biométrique, 3 lignes de 30
 */
class Mrz
{
    /**
     * Silhouette de la ligne de données : les positions où la norme impose un
     * chiffre, une lettre ou un sexe. Un texte quelconque n'y ressemble pas, et
     * chaque champ reste ensuite vérifié par sa propre clé.
     */
    private const FORME_TD3 = '/^[A-Z0-9<]{9}\d[A-Z<]{3}\d{6}\d[MFX<]\d{6}\d[A-Z0-9<]{13,19}$/';
    // 6 date + 1 cle + 1 sexe + 6 date + 1 cle + 3 nationalite + 11 facultatif
    // + 1 cle composite = 30. C est ce dernier caractere que j avais oublie.
    private const FORME_TD1 = '/^\d{6}\d[MFX<]\d{6}\d[A-Z<]{3}[A-Z0-9<]{9,15}$/';

    /** Poids cycliques de l'algorithme de contrôle MRZ (norme OACI 9303). */
    private const POIDS = [7, 3, 1];

    /**
     * Code pays ISO 3166-1 alpha-3 => nationalité en français.
     *
     * Volontairement limité à ce qu'on rencontre réellement sur la plateforme.
     * Un code absent ne propose rien : mieux vaut laisser le gestionnaire saisir
     * que suggérer une nationalité fausse sur un dossier d'identification.
     */
    private const NATIONALITES = [
        'SEN' => 'Sénégalaise',   'MLI' => 'Malienne',      'GIN' => 'Guinéenne',
        'MRT' => 'Mauritanienne', 'CIV' => 'Ivoirienne',    'GMB' => 'Gambienne',
        'BFA' => 'Burkinabè',     'NER' => 'Nigérienne',    'BEN' => 'Béninoise',
        'TGO' => 'Togolaise',     'GHA' => 'Ghanéenne',     'NGA' => 'Nigériane',
        'CMR' => 'Camerounaise',  'GAB' => 'Gabonaise',     'CPV' => 'Cap-verdienne',
        'GNB' => 'Bissau-guinéenne', 'MAR' => 'Marocaine',  'DZA' => 'Algérienne',
        'TUN' => 'Tunisienne',    'FRA' => 'Française',     'ITA' => 'Italienne',
        'ESP' => 'Espagnole',     'PRT' => 'Portugaise',    'BEL' => 'Belge',
        'DEU' => 'Allemande',     'GBR' => 'Britannique',   'USA' => 'Américaine',
        'CAN' => 'Canadienne',    'CHE' => 'Suisse',        'NLD' => 'Néerlandaise',
    ];

    /**
     * Analyse des lignes déjà isolées.
     *
     * @param  list<string>  $lignes
     * @return array{champs: array<string, string>, ecartes: array<string, string>, format: ?string, lignes: list<string>, debutBande: ?int}
     */
    public static function lire(array $lignes): array
    {
        $lignes = self::nettoyer($lignes);

        if ($td3 = self::extraireTd3($lignes)) {
            return $td3;
        }

        if ($td1 = self::extraireTd1($lignes)) {
            return $td1;
        }

        return ['champs' => [], 'ecartes' => [], 'format' => null, 'lignes' => $lignes, 'debutBande' => null];
    }

    /**
     * Ne garde que les lignes qui ressemblent à de la MRZ : majuscules, chiffres
     * et chevrons, à la bonne longueur. L'OCR rend aussi le reste de la carte —
     * nom en gros, mentions administratives — qu'il faut écarter avant d'analyser.
     *
     * @param  list<string>  $lignes
     * @return list<string>
     */
    public static function nettoyer(array $lignes): array
    {
        $retenues = [];

        foreach ($lignes as $ligne) {
            // L'OCR confond volontiers « « » et « K » avec le chevron, et l'espace
            // s'insère un peu partout : on ramène tout à l'alphabet MRZ.
            $ligne = strtoupper(trim($ligne));
            $ligne = str_replace([' ', '«', '‹', '"'], '<', $ligne);
            $ligne = preg_replace('/[^A-Z0-9<]/', '', $ligne) ?? '';

            // Longueur approchante plutot qu exacte : un caractere de trop sur la
            // ligne du nom ne doit pas faire perdre la piece entiere. C est la
            // cle de controle, plus loin, qui fait foi.
            //
            // Les autres lignes sont conservees vides plutot que supprimees : le
            // rang de chacune doit rester celui de l image, sinon on ne peut plus
            // designer « la ligne juste au-dessus de la bande ».
            $retenues[] = (strlen($ligne) >= 28 && strlen($ligne) <= 48 && substr_count($ligne, '<') >= 2)
                ? $ligne
                : '';
        }

        return $retenues;
    }

    /** Clé de contrôle OACI 9303 d'une chaîne. */
    public static function cle(string $valeur): int
    {
        $somme = 0;

        foreach (str_split($valeur) as $rang => $caractere) {
            $somme += self::valeur($caractere) * self::POIDS[$rang % 3];
        }

        return $somme % 10;
    }

    private static function valeur(string $caractere): int
    {
        if ($caractere === '<') {
            return 0;
        }

        return ctype_digit($caractere)
            ? (int) $caractere
            : ord($caractere) - ord('A') + 10;
    }

    /** Le champ n'est retenu que si sa clé tombe juste. */
    private static function verifie(string $valeur, string $cleLue): bool
    {
        return ctype_digit($cleLue) && self::cle($valeur) === (int) $cleLue;
    }

    /**
     * Passeport : 2 lignes de 44.
     *
     * @return array{champs: array<string, string>, ecartes: array<string, string>, format: string}|null
     */
    private static function extraireTd3(array $lignes): ?array
    {
        $rang = self::ligneDeDonnees($lignes, self::FORME_TD3);

        if ($rang === null) {
            return null;
        }

        $bas = $lignes[$rang];
        // La ligne du nom precede la ligne de donnees. Elle ne porte aucune cle :
        // on ne s en sert que si elle est intacte, voir plus bas.
        $haut = $lignes[$rang - 1] ?? '';

        $champs = [];
        $ecartes = [];

        self::poser($champs, $ecartes, 'numero_identification',
            self::sansChevrons(substr($bas, 0, 9)),
            self::verifie(substr($bas, 0, 9), $bas[9]));

        self::poser($champs, $ecartes, 'nationalite',
            self::nationalite(substr($bas, 10, 3)),
            true);

        self::poser($champs, $ecartes, 'date_naissance',
            self::date(substr($bas, 13, 6), passe: true),
            self::verifie(substr($bas, 13, 6), $bas[19]));

        self::poser($champs, $ecartes, 'date_expiration_piece',
            self::date(substr($bas, 21, 6), passe: false),
            self::verifie(substr($bas, 21, 6), $bas[27]));

        $champs['type_identification'] = 'Passeport';

        self::poserNoms($ecartes, $haut, 44, 5);

        return ['champs' => $champs, 'ecartes' => $ecartes, 'format' => 'TD3',
            'lignes' => $lignes, 'debutBande' => max(0, $rang - 1)];
    }

    /**
     * Carte d'identité biométrique : 3 lignes de 30.
     *
     * @return array{champs: array<string, string>, ecartes: array<string, string>, format: string}|null
     */
    private static function extraireTd1(array $lignes): ?array
    {
        $rang = self::ligneDeDonnees($lignes, self::FORME_TD1);

        if ($rang === null || ! isset($lignes[$rang - 1])) {
            return null;
        }

        $un = $lignes[$rang - 1];
        $deux = $lignes[$rang];
        $trois = $lignes[$rang + 1] ?? '';

        $champs = [];
        $ecartes = [];

        // La premiere ligne peut manquer ou etre illisible : seule la ligne de
        // donnees est garantie, c est elle qui a identifie le format. Quinze
        // caracteres suffisent — le numero et sa cle —, la queue peut deborder.
        if (strlen($un) >= 15) {
            self::poser($champs, $ecartes, 'numero_identification',
                self::sansChevrons(substr($un, 5, 9)),
                self::verifie(substr($un, 5, 9), $un[14]));
        }

        self::poser($champs, $ecartes, 'date_naissance',
            self::date(substr($deux, 0, 6), passe: true),
            self::verifie(substr($deux, 0, 6), $deux[6]));

        self::poser($champs, $ecartes, 'date_expiration_piece',
            self::date(substr($deux, 8, 6), passe: false),
            self::verifie(substr($deux, 8, 6), $deux[14]));

        self::poser($champs, $ecartes, 'nationalite',
            self::nationalite(substr($deux, 15, 3)),
            true);

        $champs['type_identification'] = "Carte nationale d'identité";

        self::poserNoms($ecartes, $trois, 30, 0);

        return ['champs' => $champs, 'ecartes' => $ecartes, 'format' => 'TD1',
            'lignes' => $lignes, 'debutBande' => max(0, $rang - 1)];
    }

    /**
     * Range la valeur si sa clé de contrôle tombe juste. Sinon elle rejoint les
     * valeurs non confirmées : lue, mais pas au point d'être proposée seule.
     */
    private static function poser(array &$champs, array &$ecartes, string $nom, ?string $valeur, bool $verifiee): void
    {
        if ($valeur === null || $valeur === '') {
            return;
        }

        if ($verifiee) {
            $champs[$nom] = $valeur;

            return;
        }

        // On garde la valeur lue : elle n est pas confirmee, mais la montrer
        // permet au gestionnaire de la comparer a la piece plutot que de rester
        // devant un champ vide sans savoir si la lecture a eu lieu.
        $ecartes[$nom] = $valeur;
    }

    /**
     * « DIOP<<MOUSSA<AMADOU » => nom « DIOP », prénom « Moussa Amadou ».
     *
     * La ligne des noms est la seule de la MRZ à ne porter aucune clé de
     * contrôle : rien ne permet de vérifier ce qu'on y a lu, et une lettre
     * parasite y passe inaperçue même quand la longueur tombe juste. Les noms
     * rejoignent donc les valeurs non confirmées — montrés pour être comparés à
     * la pièce, jamais donnés pour vérifiés.
     */
    private static function poserNoms(array &$ecartes, string $ligne, int $longueurAttendue, int $debut): void
    {
        if (abs(strlen($ligne) - $longueurAttendue) > 2) {
            return;
        }

        $zone = substr($ligne, $debut);

        [$nom, $prenoms] = array_pad(explode('<<', trim($zone, '<'), 2), 2, '');

        // Dans une MRZ, le double chevron separe les champs : ce qui suit les
        // prenoms est du remplissage, meme quand l OCR l a rendu en lettres.
        $prenoms = explode('<<', $prenoms)[0];

        $nom = self::sansChevrons($nom);
        $prenoms = self::sansChevrons($prenoms);

        if ($nom !== '') {
            $ecartes['nom'] = $nom;
        }

        if ($prenoms !== '') {
            $ecartes['prenom'] = $prenoms;
        }
    }

    private static function sansChevrons(string $valeur): string
    {
        $texte = trim(preg_replace('/<+/', ' ', $valeur) ?? '');

        // L OCR rend parfois une longue suite de chevrons comme une suite de la
        // meme lettre — « AISSATOUCKKKKKKKKKK ». Trois caracteres identiques de
        // suite ne sont pas un nom : on coupe la queue plutot que d afficher ça.
        return trim(preg_replace('/(.)\1{2,}.*$/u', '', $texte) ?? $texte);
    }

    private static function nationalite(string $code): ?string
    {
        return self::NATIONALITES[strtoupper(trim($code, '<'))] ?? null;
    }

    /**
     * AAMMJJ vers une date ISO.
     *
     * La MRZ ne donne que deux chiffres d'année : une date de naissance est
     * forcément passée, une date d'expiration se lit dans le siècle courant.
     */
    private static function date(string $brut, bool $passe): ?string
    {
        if (! preg_match('/^\d{6}$/', $brut)) {
            return null;
        }

        $an = (int) substr($brut, 0, 2);
        $mois = (int) substr($brut, 2, 2);
        $jour = (int) substr($brut, 4, 2);

        if ($mois < 1 || $mois > 12 || $jour < 1 || $jour > 31) {
            return null;
        }

        $siecleCourant = (int) substr((string) now()->year, 0, 2) * 100;
        $annee = $siecleCourant + $an;

        if ($passe && $annee > now()->year) {
            $annee -= 100;
        }

        try {
            return Carbon::createFromDate($annee, $mois, $jour)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Rang de la ligne porteuse des données, celle dont on peut vérifier la
     * lecture. C'est elle qui identifie le format — pas la ligne des noms, qui
     * n'a pas de clé et dont une seule lettre mal lue suffirait à faire perdre
     * la pièce entière.
     */
    private static function ligneDeDonnees(array $lignes, string $forme): ?int
    {
        foreach ($lignes as $rang => $ligne) {
            if ($ligne !== '' && preg_match($forme, $ligne)) {
                return $rang;
            }
        }

        return null;
    }
}
