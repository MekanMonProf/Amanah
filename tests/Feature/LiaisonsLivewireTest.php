<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

/**
 * Chaque wire:model d'une vue vise une propriété qui existe sur son composant.
 *
 * Une liaison vers une propriété absente ne casse rien au rendu : la page
 * s'affiche normalement. Elle ne casse qu'à la première interaction, quand le
 * navigateur renvoie la valeur et que Livewire ne trouve pas où la mettre — et
 * elle emporte alors tout le formulaire, bouton d'enregistrement compris.
 *
 * C'est exactement ce qui est arrivé à l'onglet « Aide et support » : le
 * composant avait perdu ses quatre champs de coordonnées, partis dans
 * « La société », et la vue les gardait. Un test qui se contente d'appeler la
 * méthode du composant ne voit rien, puisqu'il ne passe pas par les liaisons.
 */
class LiaisonsLivewireTest extends TestCase
{
    /** La vue que la convention associe à ce composant, ou null s'il n'en a pas. */
    private function vueDe(string $classe): ?string
    {
        $relatif = Str::after($classe, 'App\\Livewire\\');

        $segments = array_map(
            fn (string $s) => Str::kebab($s),
            explode('\\', $relatif),
        );

        $chemin = resource_path('views/livewire/' . implode('/', $segments) . '.blade.php');

        return file_exists($chemin) ? $chemin : null;
    }

    /** @return list<class-string> */
    private function composants(): array
    {
        $classes = [];
        $base = app_path('Livewire');

        $pile = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));

        foreach ($pile as $fichier) {
            if (! $fichier->isFile() || ! str_ends_with($fichier->getFilename(), '.php')) {
                continue;
            }

            $relatif = trim(str_replace([$base, DIRECTORY_SEPARATOR], ['', '\\'], $fichier->getPathname()), '\\');
            $classe = 'App\\Livewire\\' . substr($relatif, 0, -strlen('.php'));

            if (class_exists($classe) && is_subclass_of($classe, \Livewire\Component::class)) {
                $classes[] = $classe;
            }
        }

        sort($classes);

        return $classes;
    }

    public function test_chaque_liaison_vise_une_propriete_existante(): void
    {
        $composants = $this->composants();

        $this->assertNotEmpty($composants, 'aucun composant Livewire trouvé');

        $verifies = 0;

        foreach ($composants as $classe) {
            $vue = $this->vueDe($classe);

            if ($vue === null) {
                continue;
            }

            $proprietes = [];

            foreach ((new ReflectionClass($classe))->getProperties(\ReflectionProperty::IS_PUBLIC) as $p) {
                if (! $p->isStatic()) {
                    $proprietes[] = $p->getName();
                }
            }

            preg_match_all('/wire:model(?:\.[\w.]+)?="([^"]+)"/', file_get_contents($vue), $m);

            foreach ($m[1] as $liaison) {
                // « videos.{{ $code }} » et « videos.demarrer » visent tous deux
                // la propriété « videos » : seule la racine se vérifie ici.
                $racine = Str::before($liaison, '.');

                if ($racine === '' || str_contains($racine, '{') || str_starts_with($racine, '$')) {
                    continue;
                }

                $this->assertContains(
                    $racine,
                    $proprietes,
                    "{$classe} : la vue lie « {$liaison} » à une propriété qui n'existe pas"
                );

                $verifies++;
            }
        }

        $this->assertGreaterThan(20, $verifies, 'trop peu de liaisons vérifiées, le test ne prouve rien');
    }
}
