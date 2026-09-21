<?php

namespace App\Livewire;

use App\Support\Langue;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Sélecteur de langue, utilisable avant comme après connexion.
 *
 * Connecté, le choix est enregistré sur le compte et suit l'utilisateur d'un poste à
 * l'autre. Non connecté (écran de connexion), il ne vit que le temps de la session.
 */
class SelecteurLangue extends Component
{
    public string $langue = Langue::DEFAUT;

    public function mount(): void
    {
        $this->langue = app()->getLocale();
    }

    public function changer(string $code): void
    {
        // Le code arrive du navigateur : on refuse tout ce qui n'est pas une langue
        // proposée plutôt que de le normaliser en silence.
        abort_unless(Langue::estValide($code), 422, __("Langue non prise en charge."));

        $this->langue = $code;
        session(['langue' => $code]);

        if (Auth::check()) {
            Auth::user()->update(['langue' => $code]);
        }

        // Rechargement complet : la langue change le contenu de toute la page, et le sens
        // d'écriture est porté par la balise <html>, hors du périmètre de ce composant.
        $this->redirect(request()->header('Referer') ?: '/', navigate: false);
    }

    public function render()
    {
        return view('livewire.selecteur-langue', [
            'langues' => Langue::DISPONIBLES,
        ]);
    }
}
