<?php

namespace App\Support\Piece;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Lit une pièce d'identité photographiée et en tire ce qui peut l'être.
 *
 * La lecture tourne sur ce serveur, avec Tesseract : la pièce ne sort pas de
 * l'établissement et rien n'est facturé au document.
 *
 * Seule la bande MRZ est exploitée — voir Mrz pour la raison : c'est la seule
 * partie d'une pièce dont on peut vérifier la lecture. Le reste de la carte est
 * ignoré, même s'il est parfaitement lisible à l'œil : proposer un lieu de
 * naissance deviné par OCR sur un dossier d'identification ferait plus de dégâts
 * que de ne rien proposer du tout.
 *
 * Si Tesseract n'est pas installé, la fonction s'éteint sans bruit : le
 * formulaire se remplit à la main, comme avant.
 */
class LecteurPiece
{
    /** Aucune proposition, avec la raison — pour l'afficher plutôt que de rester muet. */
    public const AUCUN_MOTEUR = 'moteur_absent';
    public const AUCUNE_MRZ = 'mrz_absente';
    public const ECHEC = 'echec_lecture';

    /**
     * Tesseract répond-il ? Mesuré une fois par requête : la réponse ne change
     * pas en cours de route et le test coûte un lancement de processus.
     */
    public static function disponible(): bool
    {
        static $disponible = null;

        if ($disponible !== null) {
            return $disponible;
        }

        try {
            $sonde = new Process([self::binaire(), '--version']);
            $sonde->setTimeout(5);
            $sonde->run();

            return $disponible = $sonde->isSuccessful();
        } catch (\Throwable) {
            return $disponible = false;
        }
    }

    /**
     * @return array{champs: array<string, string>, ecartes: array<string, string>, format: ?string, motif: ?string}
     */
    public static function lire(string $cheminImage): array
    {
        if (! self::disponible()) {
            return self::rien(self::AUCUN_MOTEUR);
        }

        if (! is_readable($cheminImage)) {
            return self::rien(self::ECHEC);
        }

        $texte = self::ocr($cheminImage);

        if ($texte === null) {
            return self::rien(self::ECHEC);
        }

        $brutes = preg_split('/\R/', $texte) ?: [];
        $resultat = Mrz::lire($brutes);

        if ($resultat['format'] === null) {
            return self::rien(self::AUCUNE_MRZ);
        }

        self::ajouterNumeroImprime($resultat, $brutes);

        unset($resultat['lignes'], $resultat['debutBande']);

        return $resultat + ['motif' => null];
    }

    private static function ocr(string $chemin): ?string
    {
        $config = config('piece.tesseract');

        try {
            $process = new Process([
                self::binaire(),
                $chemin,
                'stdout',
                // Bloc de texte uniforme : c'est exactement ce qu'est une MRZ.
                '--psm', '6',
                '-c', 'tessedit_char_whitelist=' . $config['alphabet'],
            ]);

            $process->setTimeout($config['delai']);
            $process->run();

            if (! $process->isSuccessful()) {
                Log::warning('Lecture de pièce : Tesseract a échoué.', [
                    'sortie' => mb_substr($process->getErrorOutput(), 0, 500),
                ]);

                return null;
            }

            return $process->getOutput();
        } catch (ProcessTimedOutException) {
            Log::warning('Lecture de pièce : Tesseract a dépassé le délai.');

            return null;
        } catch (\Throwable $e) {
            Log::warning('Lecture de pièce : ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Numero imprime juste au-dessus de la bande.
     *
     * Sur la carte d identite senegalaise, le numero qui compte est imprime
     * au-dessus des trois lignes et non dedans : la bande n en porte donc
     * aucune trace, et aucune cle ne permet de le verifier. Il rejoint les
     * valeurs non confirmees, a comparer a la piece — jamais une proposition
     * ferme.
     *
     * Il est propose meme quand la bande a livre un numero : sur la carte
     * senegalaise les deux existent et ne sont pas le meme — la bande porte un
     * numero de carte, le recto le numero d identification. C est au
     * gestionnaire de dire lequel il veut.
     */
    private static function ajouterNumeroImprime(array &$resultat, array $brutes): void
    {
        $debut = $resultat['debutBande'];

        if ($debut === null) {
            return;
        }

        // On remonte au-dessus de la bande jusqu a la premiere ligne non vide.
        for ($rang = $debut - 1; $rang >= 0; $rang--) {
            $candidat = self::numeroPlausible($brutes[$rang] ?? '');

            if ($candidat === null) {
                continue;
            }

            // Le numero de la carte senegalaise porte la date de naissance : si
            // elle est plausible, et surtout si elle coincide avec celle de la
            // bande, la lecture se verifie d elle-meme. Voir NumeroCni.
            $dateDeLaBande = $resultat['champs']['date_naissance'] ?? null;

            if (NumeroCni::concordeAvec($candidat, $dateDeLaBande)) {
                $resultat['champs']['numero_identification'] = $candidat;
            } elseif (NumeroCni::structureValide($candidat)) {
                // Structure conforme mais rien pour la recouper : mieux qu un
                // texte quelconque, pas assez pour etre affirme.
                $resultat['ecartes']['numero_imprime'] = $candidat;
                $resultat['champs']['date_naissance'] ??= NumeroCni::dateDeNaissance($candidat);
            } else {
                $resultat['ecartes']['numero_imprime'] = $candidat;
            }

            return;
        }
    }

    /**
     * Une ligne imprimee ressemble-t-elle a un numero de piece ?
     *
     * Assez de chiffres pour ne pas confondre avec une mention administrative,
     * assez court pour ne pas ramasser une adresse. Le NIN senegalais fait
     * treize chiffres ; on accepte un peu autour, sans jamais rien affirmer.
     */
    private static function numeroPlausible(string $ligne): ?string
    {
        $nettoye = preg_replace('/[^A-Z0-9]/', '', strtoupper($ligne)) ?? '';

        if (strlen($nettoye) < 6 || strlen($nettoye) > 20) {
            return null;
        }

        $chiffres = preg_match_all('/\d/', $nettoye);

        return $chiffres >= 6 && $chiffres >= strlen($nettoye) * 0.6 ? $nettoye : null;
    }

    private static function binaire(): string
    {
        return config('piece.tesseract.binaire');
    }

    /** @return array{champs: array, ecartes: array<string, string>, format: null, motif: string} */
    private static function rien(string $motif): array
    {
        return ['champs' => [], 'ecartes' => [], 'format' => null, 'motif' => $motif];
    }
}
