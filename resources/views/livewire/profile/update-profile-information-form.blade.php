<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $nom = '';
    public string $prenom = '';
    public string $email = '';

    public function mount(): void
    {
        $this->nom = Auth::user()->nom;
        $this->prenom = Auth::user()->prenom ?? '';
        $this->email = Auth::user()->email ?? '';
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
        ]);

        // Une adresse vide se range à null, pas à la chaîne vide : l'index unique
        // refuserait le deuxième compte sans email.
        $validated['email'] = blank($validated['email'] ?? null) ? null : $validated['email'];

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

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
            {{ __("Mettez à jour votre nom et votre adresse email.") }}
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
                    {{ __("C'est le numéro avec lequel vous vous connectez. Pour le changer, contactez votre gestionnaire.") }}
                </p>
            </div>
        @endif

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
