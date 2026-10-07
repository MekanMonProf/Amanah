<?php

use App\Models\DemandeAcces;
use App\Models\User;
use App\Support\Telephone;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component {
    public string $identifiant = '';

    /**
     * Deux chemins, selon ce qui a été tapé.
     *
     * Un email suit la route habituelle : la plateforme envoie elle-même un
     * lien de réinitialisation. Un numéro de téléphone ne le peut pas — aucun
     * fournisseur SMS n'est branché (voir config/sms.php) et WhatsApp passe par
     * un lien qu'un humain ouvre. La demande est donc portée jusqu'au
     * gestionnaire du dossier, qui réinitialise d'un clic depuis la fiche et
     * envoie le message. Le détour par un humain vérifie au passage que la
     * personne est bien celle qu'elle dit être.
     */
    public function demander(): void
    {
        $this->validate(
            ['identifiant' => ['required', 'string']],
            ['identifiant.required' => __("Indiquez votre email ou votre numéro de téléphone.")],
        );

        $this->sansAbus();

        if (filter_var($this->identifiant, FILTER_VALIDATE_EMAIL)) {
            Password::sendResetLink(['email' => $this->identifiant]);
        } else {
            $this->deposerUneDemande();
        }

        // La même phrase dans tous les cas, y compris quand rien n'a été trouvé :
        // un message qui changerait dirait à qui le teste quels numéros et quelles
        // adresses existent en base.
        session()->flash('status', __("Si ces informations correspondent à un compte, votre demande est prise en compte. Vous recevrez un message."));

        $this->reset('identifiant');
    }

    private function deposerUneDemande(): void
    {
        $numero = Telephone::normaliser($this->identifiant);

        if ($numero === null) {
            return;
        }

        $compte = User::where('telephone', $numero)->where('role', 'investisseur')->first();
        $investisseur = $compte?->investisseurLie;

        // Un dossier sans gestionnaire n'a personne pour traiter la demande, et
        // un compte révoqué ne doit pas se rouvrir par ce chemin.
        if (! $investisseur || ! $compte->actif) {
            return;
        }

        DemandeAcces::deposer($investisseur, $numero);
    }

    /**
     * Cinq essais, comme la connexion. Sans cela, l'écran servirait à savoir
     * quels numéros sont inscrits, un par un.
     */
    private function sansAbus(): void
    {
        $cle = 'acces|' . Str::transliterate(Str::lower($this->identifiant)) . '|' . request()->ip();

        if (RateLimiter::tooManyAttempts($cle, 5)) {
            throw ValidationException::withMessages([
                'identifiant' => __("Trop de tentatives. Merci de réessayer dans :secondes secondes.", [
                    'secondes' => RateLimiter::availableIn($cle),
                ]),
            ]);
        }

        RateLimiter::hit($cle);
    }
}; ?>

<div>
    <x-surtitre>{{ __("Mot de passe") }}</x-surtitre>
    <h1 class="mt-1 text-2xl font-bold text-gray-900">{{ __("Mot de passe oublié") }}</h1>
    <p class="mt-1 text-sm text-gray-500">
        {{ __("Indiquez l'email ou le numéro de téléphone de votre dossier, comme pour vous connecter.") }}
    </p>

    <x-auth-session-status class="mt-6" :status="session('status')" />

    <form wire:submit="demander" class="mt-6">
        <div>
            <x-input-label for="identifiant" :value="__('Email ou téléphone')" />
            <x-text-input wire:model="identifiant" id="identifiant" class="block mt-1 w-full"
                          type="text" name="identifiant" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('identifiant')" class="mt-2" />
        </div>

        {{-- Dire ce qui va se passer, et que ce n'est pas la même chose selon le
             canal : avec un email le lien arrive tout de suite, avec un numéro
             c'est un gestionnaire qui rappelle. Sans cela, qui a donné son
             numéro attend un message automatique qui ne viendra pas. --}}
        <p class="mt-4 text-xs text-gray-400">
            {{ __("Avec un email, le lien arrive immédiatement. Avec un numéro, votre gestionnaire vous envoie un nouveau mot de passe par WhatsApp.") }}
        </p>

        <x-primary-button class="mt-6 w-full justify-center">
            {{ __("Demander un nouvel accès") }}
        </x-primary-button>
    </form>

    <div class="mt-6 text-center">
        <a href="{{ route('login') }}" wire:navigate class="text-sm text-gray-500 hover:underline">
            {{ __("← Retour à la connexion") }}
        </a>
    </div>
</div>
