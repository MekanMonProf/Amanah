<?php

/**
 * Extraction brute d'une feuille du classeur vers un tableau PHP [ligne][lettre] => valeur.
 * Les dates stylées sont converties en AAAA-MM-JJ.
 */
function extraireFeuille(string $fichier, string $feuilleVoulue, int $ligneDebut = 1, ?int $ligneFin = null): array
{
    $NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    $zip = new ZipArchive();
    $zip->open($fichier);

    $classeur = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
    $relations = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));

    $cibles = [];
    foreach ($relations->Relationship as $relation) {
        $cibles[(string) $relation['Id']] = 'xl/' . ltrim(str_replace('/xl/', '', (string) $relation['Target']), '/');
    }

    $cible = null;
    foreach ($classeur->sheets->sheet as $feuille) {
        if ((string) $feuille['name'] === $feuilleVoulue) {
            $cible = $cibles[(string) $feuille->attributes($NS_REL)['id']] ?? null;
        }
    }

    if (! $cible) {
        throw new RuntimeException("Feuille introuvable : {$feuilleVoulue}");
    }

    $chaines = [];
    $lecteur = new XMLReader();
    if ($lecteur->open('zip://' . realpath($fichier) . '#xl/sharedStrings.xml')) {
        while ($lecteur->read()) {
            if ($lecteur->nodeType === XMLReader::ELEMENT && $lecteur->name === 'si') {
                $noeud = simplexml_load_string($lecteur->readOuterXml());
                $texte = '';
                foreach ($noeud->t as $t) {
                    $texte .= (string) $t;
                }
                foreach ($noeud->r as $r) {
                    $texte .= (string) $r->t;
                }
                $chaines[] = $texte;
            }
        }
        $lecteur->close();
    }

    $formatsDate = [14, 15, 16, 17, 18, 19, 20, 21, 22, 27, 30, 36, 45, 46, 47, 50, 57];
    $stylesDate = [];
    $styles = simplexml_load_string($zip->getFromName('xl/styles.xml'));
    $personnalises = [];
    if (isset($styles->numFmts)) {
        foreach ($styles->numFmts->numFmt as $f) {
            $personnalises[(int) $f['numFmtId']] = (string) $f['formatCode'];
        }
    }
    $i = 0;
    foreach ($styles->cellXfs->xf as $xf) {
        $id = (int) $xf['numFmtId'];
        $code = $personnalises[$id] ?? '';
        $nettoye = preg_replace('/\[[^\]]*\]|"[^"]*"|\\\\./', '', $code);
        if (in_array($id, $formatsDate, true) || ($code !== '' && preg_match('/[dmy]/i', (string) $nettoye))) {
            $stylesDate[$i] = true;
        }
        $i++;
    }

    $lignes = [];
    $lecteur = new XMLReader();
    $lecteur->open('zip://' . realpath($fichier) . '#' . $cible);

    while ($lecteur->read()) {
        if ($lecteur->nodeType !== XMLReader::ELEMENT || $lecteur->name !== 'row') {
            continue;
        }

        $numero = (int) $lecteur->getAttribute('r');

        if ($numero < $ligneDebut) {
            continue;
        }
        if ($ligneFin !== null && $numero > $ligneFin) {
            break;
        }

        $xml = simplexml_load_string($lecteur->readOuterXml());
        $cellules = [];

        foreach ($xml->c as $cellule) {
            $lettre = preg_replace('/\d+/', '', (string) $cellule['r']);
            $type = (string) $cellule['t'];

            if ($type === 's') {
                $valeur = $chaines[(int) $cellule->v] ?? '';
            } elseif ($type === 'inlineStr') {
                $valeur = (string) ($cellule->is->t ?? '');
            } elseif ($type === 'e') {
                $valeur = '';
            } else {
                $valeur = isset($cellule->v) ? (string) $cellule->v : '';
                $style = $cellule['s'] !== null ? (int) $cellule['s'] : null;
                if ($valeur !== '' && is_numeric($valeur) && $style !== null && isset($stylesDate[$style]) && $valeur >= 1) {
                    $valeur = (new DateTimeImmutable('1899-12-30'))->modify('+' . (int) floor((float) $valeur) . ' days')->format('Y-m-d');
                }
            }

            $valeur = trim(preg_replace('/\s+/', ' ', (string) $valeur));

            if ($valeur !== '') {
                $cellules[$lettre] = $valeur;
            }
        }

        if ($cellules !== []) {
            $lignes[$numero] = $cellules;
        }
    }

    $lecteur->close();
    $zip->close();

    return $lignes;
}
