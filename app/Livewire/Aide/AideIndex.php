<?php

namespace App\Livewire\Aide;

use App\Support\Aide;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Le sommaire du mode d'emploi.
 *
 * La recherche porte sur le titre et le résumé, pas sur le corps des pages :
 * chercher dans le texte supposerait de l'indexer, et dix pages se parcourent
 * plus vite qu'on n'écrit une requête. Le champ sert à retrouver une page dont
 * on a le mot en tête, pas à fouiller.
 */
#[Layout('layouts.app')]
class AideIndex extends Component
{
    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    /**
     * Comparaison sans accents ni casse : « dividende » doit trouver
     * « Dividendes », et « deces » doit trouver « Décès ».
     */
    private function aplatir(string $texte): string
    {
        return Str::lower(Str::ascii($texte));
    }

    public function render()
    {
        $sujets = Aide::pour(Auth::user());
        $terme = trim($this->recherche);

        if ($terme !== '') {
            $recherche = $this->aplatir($terme);

            $sujets = array_filter(
                $sujets,
                fn (array $sujet) => str_contains($this->aplatir(__($sujet['titre'])), $recherche)
                    || str_contains($this->aplatir(__($sujet['resume'])), $recherche),
            );
        }

        return view('livewire.aide.aide-index', ['sujets' => $sujets]);
    }
}
