<?php

namespace App\Livewire\Parametrage;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Support\Droits;
use App\Support\Modules;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Le paramétrage de l'application, en onglets.
 *
 * Un seul écran plutôt qu'un par sujet : ces réglages se touchent rarement, et
 * les éparpiller obligerait à chercher lequel porte quoi. Chaque onglet est un
 * composant à part entière — la grille des droits n'a rien à voir avec la liste
 * des champs attendus, et les mêler dans une seule classe ne tiendrait pas.
 */
#[Layout('layouts.app')]
class Parametrage extends Component
{
    public string $onglet = 'droits';

    /** @var array<string, array<string, string>> role => module => niveau */
    public array $grille = [];

    public function mount(): void
    {
        Droits::exiger(Modules::MODULE_VERROU, Modules::ECRITURE);

        $this->chargerGrille();
    }

    public function changerOnglet(string $onglet): void
    {
        $this->onglet = in_array($onglet, ['droits', 'comptes', 'champs', 'financiers', 'societe', 'aide'], true) ? $onglet : 'droits';
    }

    private function chargerGrille(): void
    {
        $enBase = Droits::grille();

        // On reconstruit la grille complète plutôt que d'afficher ce que la table
        // contient : un module ajouté au catalogue doit apparaître à l'écran sans
        // qu'on ait à écrire une migration pour lui.
        foreach (Modules::ROLES as $role) {
            foreach (array_keys(Modules::CATALOGUE) as $module) {
                $this->grille[$role][$module] = $enBase[$role][$module] ?? Modules::AUCUN;
            }
        }
    }

    public function enregistrerDroits(): void
    {
        Droits::exiger(Modules::MODULE_VERROU, Modules::ECRITURE);

        $avant = Droits::grille();
        $modifications = [];

        foreach (Modules::ROLES as $role) {
            foreach (array_keys(Modules::CATALOGUE) as $module) {
                $niveau = $this->niveauRetenu($role, $module);

                if (($avant[$role][$module] ?? Modules::AUCUN) === $niveau) {
                    continue;
                }

                Permission::updateOrCreate(
                    ['role' => $role, 'module' => $module],
                    ['niveau' => $niveau],
                );

                $modifications[] = sprintf('%s / %s : %s → %s',
                    $role, $module, $avant[$role][$module] ?? Modules::AUCUN, $niveau);
            }
        }

        Droits::oublier();
        $this->chargerGrille();

        if ($modifications === []) {
            session()->flash('info_parametrage', __("Aucun changement à enregistrer."));

            return;
        }

        AuditLog::enregistrer(
            action: 'modification_droits',
            entite: 'permission',
            apres: ['modifications' => $modifications],
        );

        session()->flash('succes_parametrage', __(
            ":nombre droit(s) modifié(s). Les personnes connectées en verront l'effet à leur prochain écran.",
            ['nombre' => count($modifications)],
        ));
    }

    /**
     * Le niveau réellement retenu pour cette case.
     *
     * Deux garde-fous, appliqués ici et non dans la vue : un niveau au-dessus du
     * maximum du module n'a pas de sens, et l'administrateur ne peut pas se
     * retirer le paramétrage — c'est la porte par laquelle on rattrape une
     * grille mal réglée.
     */
    private function niveauRetenu(string $role, string $module): string
    {
        if ($module === Modules::MODULE_VERROU && $role === Modules::ROLE_VERROU) {
            return Modules::ECRITURE;
        }

        $choisi = $this->grille[$role][$module] ?? Modules::AUCUN;
        $possibles = Modules::niveauxPossibles($module);

        return in_array($choisi, $possibles, true) ? $choisi : Modules::AUCUN;
    }

    /** La case est-elle figée ? Vrai pour le verrou du paramétrage. */
    public function estFigee(string $role, string $module): bool
    {
        return $module === Modules::MODULE_VERROU && $role === Modules::ROLE_VERROU;
    }

    public function render()
    {
        return view('livewire.parametrage.parametrage', [
            'modules' => Modules::CATALOGUE,
            'roles' => Modules::ROLES,
        ]);
    }
}
