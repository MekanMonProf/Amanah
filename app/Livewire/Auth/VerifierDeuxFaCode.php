<?php

namespace App\Livewire\Auth;

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use PragmaRX\Google2FAQRCode\Google2FA;

#[Layout('layouts.guest')]
class VerifierDeuxFaCode extends Component
{
    #[Validate('required|string|min:6|max:12')]
    public string $code = '';

    public function verifier(): void
    {
        $this->validate();

        $user = Auth::user();
        $google2fa = new Google2FA();

        // 1) Code à 6 chiffres de l'application d'authentification
        if (ctype_digit($this->code) && strlen($this->code) === 6) {
            if ($google2fa->verifyKey($user->deux_fa_secret, $this->code)) {
                session(['deux_fa_verifie' => true]);
                $this->redirectRoute('dashboard', navigate: true);
                return;
            }
        }

        // 2) Sinon, on tente un code de récupération (usage unique)
        $codesRestants = $user->deux_fa_codes_recuperation ?? [];
        if (($cle = array_search($this->code, $codesRestants, true)) !== false) {
            unset($codesRestants[$cle]);
            $user->update(['deux_fa_codes_recuperation' => array_values($codesRestants)]);

            session(['deux_fa_verifie' => true]);
            session()->flash('avertissement_2fa', __("Code de récupération utilisé — il ne pourra plus resservir. Il vous en reste :nombre.", ['nombre' => count($codesRestants)]));
            $this->redirectRoute('dashboard', navigate: true);
            return;
        }

        $this->addError('code', 'Code invalide.');
    }

    public function deconnexion(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.verifier-deux-fa-code');
    }
}
