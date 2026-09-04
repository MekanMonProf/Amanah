<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component {
    public string $identifiant = '';
    public string $password = '';
    public bool $remember = false;

    public function login(): void
    {
        $this->validate([
            'identifiant' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureIsNotRateLimited();

        // Un investisseur peut se connecter par email OU par téléphone (la plupart n'ont
        // pas d'email) — on détecte le format saisi pour interroger la bonne colonne.
        // Le téléphone est normalisé avant comparaison : peu importe que la personne
        // tape « 771234567 », « 00221771234567 » ou « +221771234567 », c'est le même
        // numéro (voir App\Support\Telephone — c'est aussi la forme enregistrée par
        // InvestisseurShow::creerAcces()).
        // 'actif' => true bloque un compte désactivé dès la connexion, pas seulement sur
        // la requête suivante (voir EnsureCompteActif pour une session déjà ouverte).
        $estEmail = filter_var($this->identifiant, FILTER_VALIDATE_EMAIL);
        $champ = $estEmail ? 'email' : 'telephone';
        $valeur = $estEmail ? $this->identifiant : \App\Support\Telephone::normaliser($this->identifiant);

        if ($valeur === null || ! Auth::attempt([$champ => $valeur, 'password' => $this->password, 'actif' => true], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'identifiant' => 'Ces identifiants ne correspondent à aucun compte.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'identifiant' => "Trop de tentatives de connexion. Merci de réessayer dans {$seconds} secondes.",
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->identifiant).'|'.request()->ip());
    }
}; ?>

<div>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login">
        <div>
            <x-input-label for="identifiant" value="Email ou téléphone" />
            <x-text-input wire:model="identifiant" id="identifiant" class="block mt-1 w-full" type="text" name="identifiant" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('identifiant')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" value="Mot de passe" />
            <x-text-input wire:model="password" id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="block mt-4">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="remember" id="remember" type="checkbox" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800" name="remember">
                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Se souvenir de moi</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}" wire:navigate>
                    Mot de passe oublié ?
                </a>
            @endif

            <x-primary-button class="ms-3">
                Se connecter
            </x-primary-button>
        </div>
    </form>
</div>
