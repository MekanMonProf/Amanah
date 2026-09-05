<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Validate;
use Livewire\Component;
use PragmaRX\Google2FAQRCode\Google2FA;

class GererDeuxFa extends Component
{
    public bool $enCoursActivation = false;
    public string $secretTemporaire = '';
    public string $qrCodeSvg = '';

    #[Validate('required|digits:6')]
    public string $codeConfirmation = '';

    public ?array $codesRecuperationGeneres = null;

    public bool $confirmerDesactivation = false;
    #[Validate('required|current_password')]
    public string $motDePasseDesactivation = '';

    public function demarrerActivation(): void
    {
        $google2fa = new Google2FA();
        $this->secretTemporaire = $google2fa->generateSecretKey();

        $this->qrCodeSvg = $google2fa->getQRCodeInline(
            config('app.name', 'AMANAH'),
            Auth::user()->email,
            $this->secretTemporaire
        );

        $this->enCoursActivation = true;
        $this->codeConfirmation = '';
    }

    public function confirmerActivation(): void
    {
        // Uniquement le champ concerné : un validate() sans argument validerait aussi
        // motDePasseDesactivation, qui appartient au formulaire de désactivation et est
        // forcément vide ici — l'activation échouait alors sur un champ non affiché.
        $this->validate(['codeConfirmation' => ['required', 'digits:6']]);

        $google2fa = new Google2FA();

        if (! $google2fa->verifyKey($this->secretTemporaire, $this->codeConfirmation)) {
            $this->addError('codeConfirmation', 'Code invalide — vérifiez l\'heure de votre téléphone et réessayez.');
            return;
        }

        $codes = collect(range(1, 8))->map(fn () => strtoupper(str()->random(4) . '-' . str()->random(4)))->all();

        Auth::user()->update([
            'deux_fa_secret' => $this->secretTemporaire,
            'deux_fa_actif' => true,
            'deux_fa_confirme_le' => now(),
            'deux_fa_codes_recuperation' => $codes,
        ]);

        session(['deux_fa_verifie' => true]);

        $this->codesRecuperationGeneres = $codes;
        $this->enCoursActivation = false;

        \App\Models\AuditLog::enregistrer(action: 'activation_2fa', entite: 'utilisateur', entiteId: Auth::id());
    }

    public function demanderDesactivation(): void
    {
        $this->confirmerDesactivation = true;
    }

    public function desactiver(): void
    {
        $this->validate(['motDePasseDesactivation' => ['required', 'current_password']]);

        Auth::user()->update([
            'deux_fa_secret' => null,
            'deux_fa_actif' => false,
            'deux_fa_confirme_le' => null,
            'deux_fa_codes_recuperation' => null,
        ]);

        $this->confirmerDesactivation = false;
        $this->motDePasseDesactivation = '';

        \App\Models\AuditLog::enregistrer(action: 'desactivation_2fa', entite: 'utilisateur', entiteId: Auth::id());
    }

    public function render()
    {
        return view('livewire.auth.gerer-deux-fa');
    }
}
