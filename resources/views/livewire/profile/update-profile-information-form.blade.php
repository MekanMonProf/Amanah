<?php

use App\Models\User;
use App\Support\Langue;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $nom = '';
    public string $prenom = '';
    public string $email = '';

    /**
     * La langue d'affichage par défaut du compte.
     *
     * Le sélecteur de la barre supérieure l'enregistre déjà, mais il se lit comme
     * un changement de vue — on ne devine pas qu'il fixe une préférence durable.
     * Le profil est l'endroit où l'on vient régler ce qui doit rester.
     */
    public string $langue = Langue::DEFAUT;

    public function mount(): void
    {
        $this->nom = Auth::user()->nom;
        $this->prenom = Auth::user()->prenom ?? '';
        $this->email = Auth::user()->email ?? '';
        $this->langue = Langue::normaliser(Auth::user()->langue);
    }

    /**
     * Un compte se reconnaît à son email ou à son téléphone.
     *
     * Les investisseurs du portail entrent souvent par le téléphone et n'ont pas
     * d'email : exiger l'adresse ici les empêcherait d'enregistrer leur propre nom.
     * Elle ne redevient obligatoire que lorsqu'elle est le seul identifiant.
     */
    public function emailObligatoire(): bool
    {
        return blank(Auth::user()->telephone);
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'nom' => ['required', 'string', 'max:150'],
            'prenom' => ['nullable', 'string', 'max:150'],
            'email' => [
                $this->emailObligatoire() ? 'required' : 'nullable',
                'string', 'lowercase', 'email', 'max:255',
                'unique:'.User::class.',email,'.$user->id,
            ],
            'langue' => ['required', 'in:' . implode(',', array_keys(Langue::DISPONIBLES))],
        ]);

        // Une adresse vide se range à null, pas à la chaîne vide : l'index unique
        // refuserait le deuxième compte sans email.
        $validated['email'] = blank($validated['email'] ?? null) ? null : $validated['email'];

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $changementDeLangue = $user->isDirty('langue');

        $user->save();

        // La langue change tout l'écran, jusqu'au sens d'écriture porté par la
        // balise <html> : un rendu partiel de Livewire ne suffirait pas. On ne
        // recharge que dans ce cas, pour ne pas faire clignoter la page à chaque
        // correction de nom.
        if ($changementDeLangue) {
            session(['langue' => $user->langue]);

            $this->redirect(route('profile'), navigate: false);

            return;
        }

        $this->dispatch('profile-updated', name: $user->nom);
    }

    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __("Informations du profil") }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Mettez à jour vos informations de compte et la langue de l'application.") }}
        </p>
    </header>

    <form wire:submit="updateProfileInformation" class="mt-6 space-y-6">
        <div>
            <x-input-label for="nom" :value="__('Nom')" />
            <x-text-input wire:model="nom" id="nom" name="nom" type="text" class="mt-1 block w-full" required autofocus autocomplete="family-name" />
            <x-input-error class="mt-2" :messages="$errors->get('nom')" />
        </div>

        <div>
            <x-input-label for="prenom" :value="__('Prénom')" />
            <x-text-input wire:model="prenom" id="prenom" name="prenom" type="text" class="mt-1 block w-full" autocomplete="given-name" />
            <x-input-error class="mt-2" :messages="$errors->get('prenom')" />
        </div>

        @if (filled(auth()->user()->telephone))
            <div>
                <x-input-label for="telephone" :value="__('Téléphone')" />
                <x-text-input id="telephone" name="telephone" type="text" class="mt-1 block w-full bg-gray-50 text-gray-600"
                              value="{{ auth()->user()->telephone }}" disabled />
                <p class="mt-2 text-sm text-gray-500">
                    {{-- Un compte peut porter les deux : dire « c'est le numéro avec lequel
                         vous vous connectez » à quelqu'un qui entre par son email serait faux. --}}
                    {{ filled(auth()->user()->email)
                        ? __("Vous pouvez aussi vous connecter avec ce numéro. Pour le changer, contactez votre gestionnaire.")
                        : __("C'est le numéro avec lequel vous vous connectez. Pour le changer, contactez votre gestionnaire.") }}
                </p>
            </div>
        @endif

        <div>
            <x-input-label for="langue" :value="__('Langue de l\'application')" />
            <select wire:model="langue" id="langue" name="langue" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                @foreach (\App\Support\Langue::DISPONIBLES as $code => $langueDisponible)
                    <option value="{{ $code }}">{{ $langueDisponible['libelle'] }}</option>
                @endforeach
            </select>
            <p class="mt-2 text-sm text-gray-500">
                {{ __("C'est la langue dans laquelle l'application s'ouvrira, sur n'importe quel poste.") }}
            </p>
            <x-input-error class="mt-2" :messages="$errors->get('langue')" />
        </div>

        <div>
            <x-input-label for="email" :value="$this->emailObligatoire() ? __('Email') : __('Email (facultatif)')" />
            <x-text-input wire:model="email" id="email" name="email" type="email" class="mt-1 block w-full" :required="$this->emailObligatoire()" autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __("Votre adresse email n'est pas vérifiée.") }}

                        <button wire:click.prevent="sendVerification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __("Cliquez ici pour renvoyer l'email de vérification.") }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __("Un nouveau lien de vérification a été envoyé à votre adresse email.") }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __("Enregistrer") }}</x-primary-button>

            <x-action-message class="me-3" on="profile-updated">
                {{ __("Enregistré.") }}
            </x-action-message>
        </div>
    </form>
</section>
