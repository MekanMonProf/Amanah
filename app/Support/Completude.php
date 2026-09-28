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
     * Tout ce qu'un dossier de personne physique PEUT contenir, dans l'ordre du
     * formulaire, pour que la liste des manques se lise comme un parcours à faire
     * et non comme un inventaire en vrac.
     *
     * Le catalogue est plus large que ce qui est réclamé par défaut : c'est
     * l'écran de paramétrage qui décide, parmi ces lignes, lesquelles comptent.
     * En ajouter une ici ne change donc rien tant que personne ne l'a cochée.
     */
    public const CATALOGUE_PHYSIQUE = [
        'type_identification' => "Type de pièce d'identité",
        'numero_identification' => "Numéro de pièce d'identité",
        'piece_identite_path' => "Pièce d'identité scannée",
        'date_delivrance_piece' => "Date de délivrance de la pièce",
        'lieu_delivrance_piece' => "Lieu de délivrance de la pièce",
        'date_expiration_piece' => "Date d'expiration de la pièce",
        'date_naissance' => 'Date de naissance',
        'lieu_naissance' => 'Lieu de naissance',
        'nationalite' => 'Nationalité',
        'adresse' => 'Adresse',
        'ville' => 'Ville',
        'pays' => 'Pays de résidence',
        'email' => 'Email',
        'whatsapp' => 'Numéro WhatsApp',
        'convention_engagement_path' => "Convention d'engagement signée",
        'date_signature_convention' => "Date de signature de la convention",
        'beneficiaire_nom' => "Nom du bénéficiaire désigné",
        'beneficiaire_telephone' => "Téléphone du bénéficiaire",
    ];

    /**
     * Pour une personne morale, le registre du commerce et l'identifiant fiscal
     * tiennent lieu de pièce d'identité : une société n'a ni date de naissance,
     * ni nationalité au sens de l'état civil.
     */
    public const CATALOGUE_MORALE = [
        'rccm' => 'RCCM',
        'ninea' => 'NINEA',
        'adresse' => 'Adresse',
        'ville' => 'Ville',
        'pays' => 'Pays de résidence',
        'email' => 'Email',
        'representant_legal_nom' => "Nom du représentant légal",
        'representant_legal_telephone' => "Téléphone du représentant légal",
        'convention_engagement_path' => "Convention d'engagement signée",
        'date_signature_convention' => "Date de signature de la convention",
    ];

    /**
     * Ce qui était réclamé avant que l'écran n'existe, et donc ce qui l'est encore
     * au premier démarrage : la mise à jour ne change le compte des dossiers
     * incomplets pour personne.
     */
    public const DEFAUTS_PHYSIQUE = [
        'type_identification', 'numero_identification', 'piece_identite_path',
        'date_naissance', 'lieu_naissance', 'nationalite', 'adresse', 'pays',
        'convention_engagement_path',
    ];

    public const DEFAUTS_MORALE = [
        'rccm', 'ninea', 'adresse', 'pays', 'convention_engagement_path',
    ];

    /** @var array<string, array<string, string>>|null type => colonne => libelle */
    private static ?array $attendus = null;

    public static function catalogue(string $typePersonne): array
    {
        return $typePersonne === 'morale' ? self::CATALOGUE_MORALE : self::CATALOGUE_PHYSIQUE;
    }

    public static function defauts(string $typePersonne): array
    {
        return $typePersonne === 'morale' ? self::DEFAUTS_MORALE : self::DEFAUTS_PHYSIQUE;
    }

    /**
     * Les colonnes réellement réclamées pour ce type, lues une fois par requête.
     *
     * @return array<string, string> colonne => libellé
     */
    public static function reclamees(string $typePersonne): array
    {
        if (self::$attendus === null) {
            self::$attendus = [];

            foreach (['physique', 'morale'] as $type) {
                $actifs = \App\Models\ChampDossier::where('type_personne', $type)
                    ->where('actif', true)->pluck('champ')->all();

                // Table vide — première installation, ou migration pas encore jouée :
                // on retombe sur les défauts plutôt que de déclarer tout le monde complet.
                if ($actifs === []) {
                    $actifs = self::defauts($type);
                }

                self::$attendus[$type] = array_intersect_key(
                    self::catalogue($type),
                    array_flip($actifs),
                );
            }
        }

        return self::$attendus[$typePersonne] ?? [];
    }

    /** À appeler après toute écriture dans la table des champs. */
    public static function oublier(): void
    {
        self::$attendus = null;
    }

    /** @return array<string, string> colonne => libellé attendu pour ce dossier */
    public static function champsAttendus(Investisseur $investisseur): array
    {
        return self::reclamees($investisseur->type_personne === 'morale' ? 'morale' : 'physique');
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
                self::auMoinsUnVide($morale, array_keys(self::reclamees('morale')));
            })->orWhere(function (Builder $physique) {
                // Le test de type doit etre groupe : sans ces parentheses, le OR
                // deborderait sur la condition de completude et tout dossier non
                // moral serait declare incomplet, meme rempli.
                $physique->where(function (Builder $q) {
                    $q->where('type_personne', '!=', 'morale')->orWhereNull('type_personne');
                });
                self::auMoinsUnVide($physique, array_keys(self::reclamees('physique')));
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
