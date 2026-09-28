<?php

namespace App\Support;

use App\Models\AchatAction;
use App\Models\Don;
use App\Models\EcritureCompteFinancier;
use App\Models\Heritier;
use App\Models\Investisseur;
use App\Models\Radiation;
use Illuminate\Database\Eloquent\Model;

/**
 * Les pièces jointes du dossier, et qui a le droit de les ouvrir.
 *
 * Elles étaient déposées sur le disque `public`, donc servies par le serveur web
 * sans la moindre vérification : une carte d'identité scannée, un acte de décès
 * ou un RIB s'ouvrait avec la seule adresse du fichier. Le nom tiré au hasard
 * rendait la devinette improbable, mais une adresse qui fuit — un historique de
 * navigateur, un lien recopié, un cache — suffisait alors à tout livrer.
 *
 * Elles vivent désormais sur le disque privé, hors de portée du web, et ne
 * s'obtiennent que par une route qui demande qui vous êtes.
 *
 * Le catalogue ci-dessous énumère ce qui est atteignable. Une colonne absente de
 * cette liste ne se sert pas, quand bien même son nom finirait par `_path` :
 * accepter un nom de colonne venu de l'URL laisserait lire n'importe quel champ
 * de la ligne.
 */
class Document
{
    /** type => [modèle, colonnes servables] */
    private const SOURCES = [
        'investisseur' => [
            'modele' => Investisseur::class,
            'colonnes' => [
                'piece_identite_path',
                'convention_engagement_path',
                'piece_acte_deces_path',
                'piece_procuration_path',
                'piece_justificatif_domicile_path',
                'piece_rib_path',
            ],
        ],
        'heritier' => [
            'modele' => Heritier::class,
            'colonnes' => [
                'piece_identite_path',
                'piece_justificative_path',
                'piece_certificat_heredite_path',
            ],
        ],
        'ecriture' => [
            'modele' => EcritureCompteFinancier::class,
            'colonnes' => ['piece_justificative_path'],
        ],
        'achat' => [
            'modele' => AchatAction::class,
            'colonnes' => ['photo_facture_path'],
        ],
        'radiation' => [
            'modele' => Radiation::class,
            'colonnes' => ['piece_justificative_path'],
        ],
        'don' => [
            'modele' => Don::class,
            'colonnes' => ['piece_justificative_path'],
        ],
    ];

    /** Le disque où les pièces sont déposées. Privé : rien n'en sort sans passer par ici. */
    public const DISQUE = 'local';

    /**
     * L'adresse d'ouverture d'une pièce, ou null si le champ est vide.
     *
     * Rend null plutôt qu'un lien mort : une vue qui teste le retour n'affiche
     * pas de lien vers un document absent.
     */
    public static function lien(Model $porteur, string $colonne): ?string
    {
        $type = self::typeDe($porteur);

        if ($type === null || blank($porteur->$colonne)) {
            return null;
        }

        return route('documents.ouvrir', [
            'type' => $type,
            'id' => $porteur->getKey(),
            'colonne' => $colonne,
        ]);
    }

    /** @return array{modele: class-string, colonnes: array<int, string>}|null */
    public static function source(string $type): ?array
    {
        return self::SOURCES[$type] ?? null;
    }

    public static function colonneServable(string $type, string $colonne): bool
    {
        return in_array($colonne, self::SOURCES[$type]['colonnes'] ?? [], true);
    }

    /**
     * L'investisseur dont relève cette pièce, pour décider qui peut l'ouvrir.
     *
     * Un don touche deux comptes : la pièce qui le justifie regarde autant celui
     * qui donne que celui qui reçoit, d'où les deux réponses possibles.
     *
     * @return array<int, Investisseur>
     */
    public static function investisseursConcernes(Model $porteur): array
    {
        $investisseurs = match (true) {
            $porteur instanceof Investisseur => [$porteur],
            $porteur instanceof Heritier => [$porteur->investisseur],
            $porteur instanceof Don => [
                $porteur->compteSource?->investisseur,
                $porteur->compteDestinataire?->investisseur,
            ],
            default => [$porteur->compte?->investisseur],
        };

        return array_values(array_filter($investisseurs));
    }

    private static function typeDe(Model $porteur): ?string
    {
        foreach (self::SOURCES as $type => $source) {
            if ($porteur instanceof $source['modele']) {
                return $type;
            }
        }

        return null;
    }
}
