<?php

namespace App\Support;

use App\Models\Investisseur;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ce qui manque à un dossier investisseur pour être considéré comme complet.
 *
 * Un dossier se crée en quelques secondes — un nom, un numéro — pour que la
 * souscription n'attende pas les papiers. Le reste se saisit ensuite. Sans
 * rappel, ce « ensuite » n'arrive jamais : cette classe rend l'écart visible,
 * à l'écran comme dans une requête.
 *
 * Elle ne bloque rien. Un dossier incomplet reste utilisable, il est seulement
 * signalé — arrêter la collecte pour une pièce manquante coûterait plus que le
 * risque qu'on cherche à réduire.
 */
class Completude
{
    /**
     * Colonne => libellé affiché, pour une personne physique.
     *
     * L'ordre est celui du formulaire, pour que la liste des manques se lise
     * comme un parcours à faire et non comme un inventaire en vrac.
     */
    private const PHYSIQUE = [
        'type_identification' => "Type de pièce d'identité",
        'numero_identification' => "Numéro de pièce d'identité",
        'piece_identite_path' => "Pièce d'identité scannée",
        'date_naissance' => 'Date de naissance',
        'lieu_naissance' => 'Lieu de naissance',
        'nationalite' => 'Nationalité',
        'adresse' => 'Adresse',
        'pays' => 'Pays de résidence',
        'convention_engagement_path' => "Convention d'engagement signée",
    ];

    /**
     * Pour une personne morale, le registre du commerce et l'identifiant fiscal
     * tiennent lieu de pièce d'identité : une société n'a ni date de naissance,
     * ni nationalité au sens de l'état civil.
     */
    private const MORALE = [
        'rccm' => 'RCCM',
        'ninea' => 'NINEA',
        'adresse' => 'Adresse',
        'pays' => 'Pays de résidence',
        'convention_engagement_path' => "Convention d'engagement signée",
    ];

    /** @return array<string, string> colonne => libellé attendu pour ce dossier */
    public static function champsAttendus(Investisseur $investisseur): array
    {
        return $investisseur->type_personne === 'morale' ? self::MORALE : self::PHYSIQUE;
    }

    /**
     * Libellés de ce qui manque, dans l'ordre du formulaire. Vide = dossier complet.
     *
     * @return list<string>
     */
    public static function manquants(Investisseur $investisseur): array
    {
        $manquants = [];

        foreach (self::champsAttendus($investisseur) as $colonne => $libelle) {
            if (blank($investisseur->$colonne)) {
                $manquants[] = $libelle;
            }
        }

        return $manquants;
    }

    public static function estComplet(Investisseur $investisseur): bool
    {
        return self::manquants($investisseur) === [];
    }

    /**
     * Restreint une requête aux dossiers incomplets.
     *
     * Le jeu de colonnes dépend du type de personne : la condition est donc
     * écrite en deux branches plutôt qu'en une seule liste, sinon une société
     * serait déclarée incomplète faute de date de naissance.
     */
    public static function filtrerIncomplets(Builder $requete): Builder
    {
        return $requete->where(function (Builder $q) {
            $q->where(function (Builder $morale) {
                $morale->where('type_personne', 'morale');
                self::auMoinsUnVide($morale, array_keys(self::MORALE));
            })->orWhere(function (Builder $physique) {
                // Le test de type doit etre groupe : sans ces parentheses, le OR
                // deborderait sur la condition de completude et tout dossier non
                // moral serait declare incomplet, meme rempli.
                $physique->where(function (Builder $q) {
                    $q->where('type_personne', '!=', 'morale')->orWhereNull('type_personne');
                });
                self::auMoinsUnVide($physique, array_keys(self::PHYSIQUE));
            });
        });
    }

    /** Au moins une des colonnes est nulle ou vide. */
    private static function auMoinsUnVide(Builder $requete, array $colonnes): void
    {
        $requete->where(function (Builder $q) use ($colonnes) {
            foreach ($colonnes as $colonne) {
                $q->orWhereNull($colonne)->orWhere($colonne, '');
            }
        });
    }
}
