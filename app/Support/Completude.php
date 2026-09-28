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
        'piece_procuration_path' => "Procuration",
        'piece_justificatif_domicile_path' => "Justificatif de domicile",
        'piece_rib_path' => "RIB ou coordonnées bancaires",
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
        'piece_procuration_path' => "Procuration",
        'piece_justificatif_domicile_path' => "Justificatif de domicile",
        'piece_rib_path' => "RIB ou coordonnées bancaires",
    ];

    /**
     * Ce qu'on réclame en plus quand l'investisseur est décédé.
     *
     * Ces champs s'ajoutent à ceux de son type, ils ne les remplacent pas. Les
     * réclamer à tout le monde signalerait trente-sept dossiers vivants comme
     * incomplets faute d'un acte de décès.
     *
     * Les autres pièces d'une succession — procuration du mandataire, certificat
     * d'hérédité, pièce d'identité de l'héritier — ne sont pas ici : elles vivent
     * sur l'héritier, pas sur le dossier, et l'écran de succession les exige déjà
     * au moment où elles servent.
     */
    public const CATALOGUE_SUCCESSION = [
        'piece_acte_deces_path' => "Acte de décès",
        'mandataire:piece_identite_path' => "Pièce d'identité du mandataire",
        'mandataire:piece_certificat_heredite_path' => "Certificat d'hérédité ou acte de notoriété",
        'mandataire:piece_justificative_path' => "Procuration signée par la famille",
    ];

    /**
     * Les trois pièces du mandataire étaient exigées à la désignation : le formulaire
     * refusait d'enregistrer sans elles. Elles restent réclamées par défaut, mais
     * comme signalement — une succession s'ouvre souvent avant que la famille
     * n'ait réuni ses papiers, et bloquer la désignation retardait tout le reste.
     */
    public const DEFAUTS_SUCCESSION = [
        'piece_acte_deces_path',
        'mandataire:piece_identite_path',
        'mandataire:piece_certificat_heredite_path',
        'mandataire:piece_justificative_path',
    ];

    /**
     * Préfixe des champs qui ne vivent pas sur le dossier mais sur le mandataire.
     *
     * Le mandataire est le premier héritier désigné ; ses pièces appartiennent à la
     * succession sans appartenir à l'investisseur, d'où ce détour plutôt qu'une
     * recopie de colonnes sur le dossier.
     */
    public const PREFIXE_MANDATAIRE = 'mandataire:';

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

    /** Les trois contextes, dans l'ordre où l'écran les présente. */
    public const CONTEXTES = ['physique', 'morale', 'succession'];

    public static function catalogue(string $contexte): array
    {
        return match ($contexte) {
            'morale' => self::CATALOGUE_MORALE,
            'succession' => self::CATALOGUE_SUCCESSION,
            default => self::CATALOGUE_PHYSIQUE,
        };
    }

    public static function defauts(string $contexte): array
    {
        return match ($contexte) {
            'morale' => self::DEFAUTS_MORALE,
            'succession' => self::DEFAUTS_SUCCESSION,
            default => self::DEFAUTS_PHYSIQUE,
        };
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

            foreach (self::CONTEXTES as $type) {
                $lignes = \App\Models\ChampDossier::where('contexte', $type)->get();

                // Le repli sur les défauts ne vaut que si le contexte n'a aucune ligne
                // — première installation, ou migration pas encore jouée. Le déclencher
                // dès que plus rien n'est coché rendrait le décochage impossible : on
                // retirerait la dernière case et les anciens champs reviendraient.
                $actifs = $lignes->isEmpty()
                    ? self::defauts($type)
                    : $lignes->where('actif', true)->pluck('champ')->all();

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
        $attendus = self::reclamees($investisseur->type_personne === 'morale' ? 'morale' : 'physique');

        // Un défunt cumule : les pièces de son type, et celles que la succession
        // ajoute par-dessus.
        if ($investisseur->estDecede()) {
            $attendus += self::reclamees('succession');
        }

        return $attendus;
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
            if (blank(self::valeur($investisseur, $colonne))) {
                $manquants[] = $libelle;
            }
        }

        return $manquants;
    }

    /**
     * La valeur d'un champ attendu, sur le dossier ou sur son mandataire.
     *
     * Une pièce du mandataire est tenue pour fournie dès qu'un héritier la porte :
     * c'est le dossier de succession qui est complet ou non, pas telle personne.
     */
    private static function valeur(Investisseur $investisseur, string $colonne): mixed
    {
        if (! str_starts_with($colonne, self::PREFIXE_MANDATAIRE)) {
            return $investisseur->$colonne;
        }

        $piece = substr($colonne, strlen(self::PREFIXE_MANDATAIRE));

        return $investisseur->heritiers->pluck($piece)->filter()->first();
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

            // Les pieces de succession s'ajoutent pour un defunt. Sans cette
            // troisieme branche, la fiche afficherait l'acte de deces manquant
            // pendant que la liste le compterait pour complet : c'est exactement
            // le genre d'ecart que le filtre et l'affichage ne doivent pas avoir.
            $succession = array_keys(self::reclamees('succession'));

            if ($succession !== []) {
                $surLeDossier = array_values(array_filter(
                    $succession,
                    fn (string $c) => ! str_starts_with($c, self::PREFIXE_MANDATAIRE),
                ));

                $surLeMandataire = array_map(
                    fn (string $c) => substr($c, strlen(self::PREFIXE_MANDATAIRE)),
                    array_values(array_filter(
                        $succession,
                        fn (string $c) => str_starts_with($c, self::PREFIXE_MANDATAIRE),
                    )),
                );

                $q->orWhere(function (Builder $defunt) use ($surLeDossier, $surLeMandataire) {
                    $defunt->where('statut', 'decede')
                        ->where(function (Builder $manque) use ($surLeDossier, $surLeMandataire) {
                            if ($surLeDossier !== []) {
                                $manque->where(function (Builder $dossier) use ($surLeDossier) {
                                    self::auMoinsUnVide($dossier, $surLeDossier);
                                });
                            }

                            // Une piece du mandataire manque des lors qu'aucun heritier
                            // ne la porte : c'est le dossier de succession qu'on juge,
                            // pas telle personne. Un defunt sans aucun heritier les
                            // manque donc toutes, ce qui est exact.
                            foreach ($surLeMandataire as $piece) {
                                $manque->orWhereDoesntHave(
                                    'heritiers',
                                    fn (Builder $h) => $h->whereNotNull($piece),
                                );
                            }
                        });
                });
            }
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
