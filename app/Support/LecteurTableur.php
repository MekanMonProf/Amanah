<?php

namespace App\Support;

/**
 * Lit un fichier tabulaire (CSV/TSV ou XLSX) et le rend sous forme de lignes
 * associatives, sans aucune dépendance externe.
 *
 * Pourquoi pas PhpSpreadsheet : la librairie exige l'extension PHP « gd », absente
 * de l'environnement du projet. Un .xlsx n'étant qu'une archive ZIP de fichiers XML,
 * la lecture directe (ZipArchive + SimpleXML) évite d'imposer une extension PHP
 * supplémentaire au serveur de production.
 *
 * Ce lecteur couvre ce que produit Excel/LibreOffice en usage normal : première
 * feuille, chaînes partagées, chaînes en ligne, nombres, et dates (converties en
 * AAAA-MM-JJ à partir du numéro de série Excel).
 */
class LecteurTableur
{
    /** Garde-fou : au-delà, on demande de découper le fichier. */
    public const MAX_LIGNES = 5000;

    protected const NS_RELATIONS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /** Formats de date/heure intégrés à Excel (numFmtId réservés). */
    protected const FORMATS_DATE_INTEGRES = [14, 15, 16, 17, 18, 19, 20, 21, 22, 27, 30, 36, 45, 46, 47, 50, 57];

    /**
     * @return array{entetes: array<int, string>, lignes: array<int, array{numero: int, valeurs: array<string, string>}>}
     *
     * @throws \RuntimeException message destiné à être affiché tel quel à l'utilisateur
     */
    public static function lire(string $chemin, ?string $extension = null): array
    {
        $extension = strtolower($extension ?: pathinfo($chemin, PATHINFO_EXTENSION));

        $matrice = match ($extension) {
            'csv', 'txt', 'tsv' => static::lireCsv($chemin),
            'xlsx', 'xlsm' => static::lireXlsx($chemin),
            'xls' => throw new \RuntimeException("Le format .xls (ancien Excel) n'est pas lisible directement. Ouvrez le fichier dans Excel puis « Enregistrer sous » en .xlsx ou en CSV."),
            default => throw new \RuntimeException("Format de fichier non reconnu (.{$extension}). Formats acceptés : .xlsx et .csv."),
        };

        return static::assembler($matrice);
    }

    /**
     * Transforme la matrice brute en lignes associatives : la première ligne non vide
     * sert d'en-tête. Le numéro conservé est celui de la ligne dans le fichier, pour
     * que l'utilisateur retrouve l'erreur dans son tableur.
     */
    protected static function assembler(array $matrice): array
    {
        $entetes = null;
        $numeroEntete = 0;
        $lignes = [];

        foreach ($matrice as $index => $cellules) {
            $numero = $index + 1;

            if (static::ligneVide($cellules)) {
                continue;
            }

            if ($entetes === null) {
                $entetes = [];
                foreach ($cellules as $position => $cellule) {
                    $nom = static::normaliserEntete((string) $cellule);
                    // Une colonne sans titre reste adressable, mais ne sera jamais reconnue.
                    $entetes[$position] = $nom !== '' ? $nom : 'colonne_' . ($position + 1);
                }
                $numeroEntete = $numero;
                continue;
            }

            $valeurs = [];
            foreach ($entetes as $position => $nom) {
                $valeurs[$nom] = trim((string) ($cellules[$position] ?? ''));
            }

            $lignes[] = ['numero' => $numero, 'valeurs' => $valeurs];

            if (count($lignes) > static::MAX_LIGNES) {
                throw new \RuntimeException(sprintf(
                    "Le fichier dépasse %s lignes. Découpez-le en plusieurs fichiers et importez-les l'un après l'autre.",
                    \App\Support\Montant::format(static::MAX_LIGNES)
                ));
            }
        }

        if ($entetes === null) {
            throw new \RuntimeException("Le fichier est vide : aucune ligne d'en-tête trouvée.");
        }

        if ($lignes === []) {
            throw new \RuntimeException(sprintf(
                'Aucune donnée après la ligne d\'en-tête (ligne %d). Le fichier ne contient que des titres de colonnes.',
                $numeroEntete
            ));
        }

        return ['entetes' => array_values($entetes), 'lignes' => $lignes];
    }

    protected static function ligneVide(array $cellules): bool
    {
        foreach ($cellules as $cellule) {
            if (trim((string) $cellule) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * « Date de naissance » et « DATE_NAISSANCE » désignent la même colonne : les
     * en-têtes sont ramenés à une forme technique (minuscules, sans accent, underscores).
     */
    public static function normaliserEntete(string $valeur): string
    {
        $valeur = strtolower(static::sansAccents(trim($valeur)));
        $valeur = preg_replace('/[^a-z0-9]+/', '_', $valeur);

        return trim((string) $valeur, '_');
    }

    public static function sansAccents(string $valeur): string
    {
        return strtr($valeur, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Ã' => 'A', 'Å' => 'A',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N', 'Œ' => 'OE', 'Æ' => 'AE',
        ]);
    }

    // ---------------------------------------------------------------- CSV

    protected static function lireCsv(string $chemin): array
    {
        $contenu = @file_get_contents($chemin);

        if ($contenu === false) {
            throw new \RuntimeException("Le fichier n'a pas pu être ouvert.");
        }

        $contenu = static::normaliserEncodage($contenu);
        $delimiteur = static::detecterDelimiteur($contenu);

        $flux = fopen('php://temp', 'r+');
        fwrite($flux, $contenu);
        rewind($flux);

        $matrice = [];
        while (($cellules = fgetcsv($flux, 0, $delimiteur, '"', '')) !== false) {
            $matrice[] = $cellules === [null] ? [] : $cellules;
        }

        fclose($flux);

        return $matrice;
    }

    /**
     * Excel en français exporte en Windows-1252 : sans conversion, « Aïssatou »
     * arriverait en base avec des caractères cassés.
     */
    protected static function normaliserEncodage(string $contenu): string
    {
        if (str_starts_with($contenu, "\xEF\xBB\xBF")) {
            $contenu = substr($contenu, 3);
        }

        if (! mb_check_encoding($contenu, 'UTF-8')) {
            $contenu = mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
        }

        return $contenu;
    }

    /** Excel francophone sépare par « ; », les exports anglo-saxons par « , ». */
    protected static function detecterDelimiteur(string $contenu): string
    {
        $premiereLigne = strtok($contenu, "\r\n") ?: '';

        $occurrences = [
            ';' => substr_count($premiereLigne, ';'),
            ',' => substr_count($premiereLigne, ','),
            "\t" => substr_count($premiereLigne, "\t"),
        ];

        arsort($occurrences);
        $meilleur = array_key_first($occurrences);

        return $occurrences[$meilleur] > 0 ? $meilleur : ';';
    }

    // --------------------------------------------------------------- XLSX

    protected static function lireXlsx(string $chemin): array
    {
        $zip = new \ZipArchive();

        if ($zip->open($chemin) !== true) {
            throw new \RuntimeException('Le fichier Excel est illisible ou endommagé. Réenregistrez-le depuis Excel puis réessayez.');
        }

        try {
            $chaines = static::chainesPartagees($zip);
            [$stylesDate, $base1904] = static::stylesDeDate($zip);
            $feuille = static::premiereFeuille($zip);

            return static::cellulesDeLaFeuille($feuille, $chaines, $stylesDate, $base1904);
        } finally {
            $zip->close();
        }
    }

    protected static function xml(\ZipArchive $zip, string $entree): ?\SimpleXMLElement
    {
        $contenu = $zip->getFromName($entree);

        if ($contenu === false || trim($contenu) === '') {
            return null;
        }

        $prealable = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contenu);
        libxml_clear_errors();
        libxml_use_internal_errors($prealable);

        return $xml ?: null;
    }

    /** @return array<int, string> */
    protected static function chainesPartagees(\ZipArchive $zip): array
    {
        $xml = static::xml($zip, 'xl/sharedStrings.xml');

        if ($xml === null) {
            return [];
        }

        $chaines = [];
        foreach ($xml->si as $item) {
            $chaines[] = static::texteConcatene($item);
        }

        return $chaines;
    }

    /** Une chaîne Excel peut être découpée en plusieurs fragments (mise en forme partielle). */
    protected static function texteConcatene(\SimpleXMLElement $noeud): string
    {
        $texte = '';

        foreach ($noeud->t as $fragment) {
            $texte .= (string) $fragment;
        }

        foreach ($noeud->r as $fragment) {
            $texte .= (string) $fragment->t;
        }

        return $texte;
    }

    /**
     * Une date Excel est un nombre : seul le format appliqué à la cellule dit qu'il
     * s'agit d'une date. On repère donc les index de style porteurs d'un format date.
     *
     * @return array{0: array<int, bool>, 1: bool}
     */
    protected static function stylesDeDate(\ZipArchive $zip): array
    {
        $base1904 = false;
        $classeur = static::xml($zip, 'xl/workbook.xml');
        if ($classeur !== null && isset($classeur->workbookPr)) {
            $base1904 = in_array((string) $classeur->workbookPr['date1904'], ['1', 'true'], true);
        }

        $xml = static::xml($zip, 'xl/styles.xml');

        if ($xml === null) {
            return [[], $base1904];
        }

        $formatsPersonnalises = [];
        if (isset($xml->numFmts)) {
            foreach ($xml->numFmts->numFmt as $format) {
                $formatsPersonnalises[(int) $format['numFmtId']] = (string) $format['formatCode'];
            }
        }

        $stylesDate = [];
        if (isset($xml->cellXfs)) {
            $index = 0;
            foreach ($xml->cellXfs->xf as $xf) {
                $numFmtId = (int) $xf['numFmtId'];

                $estDate = in_array($numFmtId, static::FORMATS_DATE_INTEGRES, true)
                    || (isset($formatsPersonnalises[$numFmtId]) && static::formatEstUneDate($formatsPersonnalises[$numFmtId]));

                if ($estDate) {
                    $stylesDate[$index] = true;
                }

                $index++;
            }
        }

        return [$stylesDate, $base1904];
    }

    protected static function formatEstUneDate(string $formatCode): bool
    {
        // On retire les littéraux ([Rouge], "CFA", \-) avant de chercher j/m/a.
        $nettoye = preg_replace('/\[[^\]]*\]|"[^"]*"|\\\\./', '', $formatCode);

        return (bool) preg_match('/[dmyhs]/i', (string) $nettoye);
    }

    protected static function premiereFeuille(\ZipArchive $zip): \SimpleXMLElement
    {
        $classeur = static::xml($zip, 'xl/workbook.xml');
        $cible = null;

        if ($classeur !== null && isset($classeur->sheets->sheet[0])) {
            $identifiant = (string) $classeur->sheets->sheet[0]->attributes(static::NS_RELATIONS)['id'];
            $relations = static::xml($zip, 'xl/_rels/workbook.xml.rels');

            if ($relations !== null) {
                foreach ($relations->Relationship as $relation) {
                    if ((string) $relation['Id'] === $identifiant) {
                        $cible = ltrim((string) $relation['Target'], '/');
                        break;
                    }
                }
            }
        }

        $candidats = array_filter([$cible ? 'xl/' . $cible : null, $cible, 'xl/worksheets/sheet1.xml']);

        foreach ($candidats as $entree) {
            $feuille = static::xml($zip, str_replace('xl/xl/', 'xl/', (string) $entree));

            if ($feuille !== null && isset($feuille->sheetData)) {
                return $feuille;
            }
        }

        throw new \RuntimeException('Aucune feuille de calcul exploitable dans ce fichier Excel.');
    }

    protected static function cellulesDeLaFeuille(\SimpleXMLElement $feuille, array $chaines, array $stylesDate, bool $base1904): array
    {
        $matrice = [];

        foreach ($feuille->sheetData->row as $ligne) {
            $numero = isset($ligne['r']) ? (int) $ligne['r'] : count($matrice) + 1;
            $cellules = [];

            foreach ($ligne->c as $cellule) {
                $colonne = static::indexColonne((string) $cellule['r'], count($cellules));
                $cellules[$colonne] = static::valeurCellule($cellule, $chaines, $stylesDate, $base1904);
            }

            if ($cellules !== []) {
                ksort($cellules);
                // Les colonnes vides intercalées doivent exister pour rester alignées sur l'en-tête.
                $complete = [];
                for ($i = 0; $i <= max(array_keys($cellules)); $i++) {
                    $complete[$i] = $cellules[$i] ?? '';
                }
                $cellules = $complete;
            }

            // L'index du tableau reflète le vrai numéro de ligne du tableur.
            $matrice[$numero - 1] = $cellules;

            if (count($matrice) > static::MAX_LIGNES + 1) {
                break;
            }
        }

        ksort($matrice);

        $complet = [];
        for ($i = 0; $i <= (empty($matrice) ? -1 : max(array_keys($matrice))); $i++) {
            $complet[$i] = $matrice[$i] ?? [];
        }

        return $complet;
    }

    protected static function indexColonne(string $reference, int $defaut): int
    {
        if (! preg_match('/^([A-Z]+)/i', $reference, $correspondance)) {
            return $defaut;
        }

        $lettres = strtoupper($correspondance[1]);
        $index = 0;

        for ($i = 0; $i < strlen($lettres); $i++) {
            $index = $index * 26 + (ord($lettres[$i]) - 64);
        }

        return $index - 1;
    }

    protected static function valeurCellule(\SimpleXMLElement $cellule, array $chaines, array $stylesDate, bool $base1904): string
    {
        $type = (string) $cellule['t'];

        if ($type === 's') {
            return $chaines[(int) $cellule->v] ?? '';
        }

        if ($type === 'inlineStr') {
            return isset($cellule->is) ? static::texteConcatene($cellule->is) : '';
        }

        if ($type === 'b') {
            return (string) $cellule->v === '1' ? '1' : '0';
        }

        if ($type === 'e') {
            return ''; // #N/A, #REF!... : traité comme une cellule vide
        }

        $brut = isset($cellule->v) ? (string) $cellule->v : '';

        if ($brut === '' || ! is_numeric($brut)) {
            return $brut;
        }

        $style = $cellule['s'] !== null ? (int) $cellule['s'] : null;

        if ($style !== null && isset($stylesDate[$style])) {
            return static::serieVersDate((float) $brut, $base1904);
        }

        return $brut;
    }

    /** Numéro de série Excel → date AAAA-MM-JJ (base 1899-12-30, bug historique de 1900 inclus). */
    protected static function serieVersDate(float $serie, bool $base1904): string
    {
        if ($serie < 1) {
            return (string) $serie; // une heure seule, pas une date
        }

        $origine = $base1904 ? '1904-01-01' : '1899-12-30';

        return (new \DateTimeImmutable($origine))
            ->modify('+' . (int) floor($serie) . ' days')
            ->format('Y-m-d');
    }
}
