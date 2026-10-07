<?php

namespace App\Livewire\Aide;

use App\Models\ParametreSupport;
use App\Support\Aide;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Une page du mode d'emploi.
 *
 * Le contenu vient d'une vue sous `resources/views/aide/`, choisie par la
 * langue de l'utilisateur. La vidéo, elle, vient du paramétrage : tant que
 * personne ne l'a renseignée, l'emplacement n'apparaît pas du tout — un cadre
 * vide qui promet une vidéo absente est pire que pas de cadre.
 */
#[Layout('layouts.app')]
class AidePage extends Component
{
    public string $sujet;

    public function mount(string $sujet): void
    {
        abort_unless(Aide::existe($sujet), 404);

        // Le filtre n'est pas qu'un confort d'affichage : une page qui décrit
        // un écran interdit renseignerait sur ce qu'on ne peut pas voir.
        abort_unless(Aide::accessible($sujet, Auth::user()), 403, __(
            "Cette page du mode d'emploi ne concerne pas votre rôle.",
        ));

        $this->sujet = $sujet;
    }

    public function render()
    {
        $page = Aide::page($this->sujet);

        return view('livewire.aide.aide-page', [
            'definition' => Aide::SUJETS[$this->sujet],
            'page' => $page,
            'video' => ParametreSupport::actuel()->video($this->sujet),
            'voisins' => Aide::voisins($this->sujet, Auth::user()),
        ]);
    }
}
