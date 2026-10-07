<?php

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

            // Distinguer les deux échecs, mais seulement quand le mot de passe est bon :
            // un compte révoqué qui s'entend dire que ses identifiants sont inconnus
            // rappelle son gestionnaire pour un problème qui n'existe pas, et celui-ci
            // voit pourtant « Accès portail révoqué » sur la fiche. EnsureCompteActif
            // tient déjà ce discours à une session ouverte au moment de la révocation ;
            // les deux chemins disent maintenant la même chose.
            //
            // Rien n'est divulgué au passage : sans le bon mot de passe, le message
            // reste celui d'identifiants inconnus.
            $compte = $valeur === null ? null : User::where($champ, $valeur)->first();

            $revoque = $compte
                && ! $compte->actif
                && Hash::check($this->password, $compte->password);

            throw ValidationException::withMessages([
                'identifiant' => $revoque
                    ? __("Ce compte a été désactivé. Contactez votre gestionnaire.")
                    : __("Ces identifiants ne correspondent à aucun compte."),
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
            'identifiant' => __("Trop de tentatives de connexion. Merci de réessayer dans :secondes secondes.", ['secondes' => $seconds]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->identifiant).'|'.request()->ip());
    }
}; ?>

<div>
    <x-surtitre>{{ __("Connexion") }}</x-surtitre>
    <h1 class="mt-1 text-2xl font-bold text-gray-900">{{ __("Se connecter") }}</h1>
    {{-- La plupart des investisseurs n'ont pas d'email : le dire ici évite
         qu'ils cherchent un identifiant qu'ils n'ont jamais eu. --}}
    <p class="mt-1 text-sm text-gray-500">
        {{ __("Votre email ou le numéro de téléphone de votre dossier.") }}
    </p>

    <x-auth-session-status class="mt-6" :status="session('status')" />

    <form wire:submit="login" class="mt-6">
        <div>
            <x-input-label for="identifiant" :value="__('Email ou téléphone')" />
            <x-text-input wire:model="identifiant" id="identifiant" class="block mt-1 w-full" type="text" name="identifiant" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('identifiant')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Mot de passe')" />
            <x-text-input wire:model="password" id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="remember" id="remember" type="checkbox" class="rounded border-gray-300 text-primaire-700 shadow-sm focus:ring-primaire-600" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __("Se souvenir de moi") }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm text-primaire-700 hover:underline rounded-champ focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primaire-600" href="{{ route('password.request') }}" wire:navigate>
                    {{ __("Mot de passe oublié ?") }}
                </a>
            @endif
        </div>

        {{-- Un seul bouton sur l'écran, et rien ne le dispute : il prend toute
             la largeur plutôt que de se blottir dans un coin. --}}
        <x-primary-button class="mt-6 w-full justify-center">
            {{ __("Se connecter") }}
        </x-primary-button>
    </form>
</div>
