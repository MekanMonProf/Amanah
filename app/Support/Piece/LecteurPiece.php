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

        $resultat = Mrz::lire(preg_split('/\R/', $texte) ?: []);

        if ($resultat['format'] === null) {
            return self::rien(self::AUCUNE_MRZ);
        }

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
