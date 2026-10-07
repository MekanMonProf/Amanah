<?php

namespace App\Livewire\Support;

use App\Models\DemandeSupport;
use App\Support\MessageSupport;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Le formulaire de demande de support.
 *
 * Deux temps, et c'est voulu. La demande est d'abord écrite en base : elle
 * existe, elle a un numéro, elle ne dépend plus de personne. Ensuite seulement
 * l'écran propose de prévenir par WhatsApp ou par email — avec le message déjà
 * rédigé, numéro de demande compris, pour que la conversation et la ligne
 * enregistrée parlent de la même chose.
 *
 * L'envoi reste un geste manuel : la plateforme n'a pas de compte Meta Business
 * et n'envoie rien toute seule (voir MessageWhatsapp, qui fait le même choix
 * pour les identifiants). Si le message n'est jamais envoyé, la demande est
 * quand même là — c'est précisément ce que le formulaire achète.
 */
#[Layout('layouts.app')]
class ContacterSupport extends Component
{
    /** L'écran d'où l'on vient, transmis par le lien d'aide. */
    #[Url(as: 'depuis', except: '')]
    public string $origine = '';

    public string $categorie = 'question';

    public string $sujet = '';

    public string $message = '';

    public ?int $demandeEnvoyee = null;

    protected function rules(): array
    {
        return [
            'categorie' => ['required', 'in:' . implode(',', array_keys(DemandeSupport::CATEGORIES))],
            'sujet' => ['required', 'string', 'min:5', 'max:150'],
            'message' => ['required', 'string', 'min:20'],
        ];
    }

    protected function messages(): array
    {
        return [
            'sujet.min' => __("Donnez un titre un peu plus parlant — quelques mots suffisent."),
            'message.min' => __("Décrivez ce qui s'est passé : ce que vous faisiez, ce que vous attendiez, ce qui est arrivé."),
        ];
    }

    public function envoyer(): void
    {
        $this->validate();

        $utilisateur = Auth::user();

        $demande = DemandeSupport::create([
            'user_id' => $utilisateur->id,
            'role' => $utilisateur->role,
            'categorie' => $this->categorie,
            'sujet' => trim($this->sujet),
            'message' => trim($this->message),
            'url_origine' => $this->origine !== '' ? $this->origine : null,
            'statut' => 'nouvelle',
        ]);

        $this->demandeEnvoyee = $demande->id;
        $this->reset(['sujet', 'message']);
        $this->resetValidation();
    }

    public function nouvelleDemande(): void
    {
        $this->reset(['demandeEnvoyee', 'categorie', 'sujet', 'message']);
        $this->categorie = 'question';
    }

    public function render()
    {
        $utilisateur = Auth::user();
        $demande = $this->demandeEnvoyee ? DemandeSupport::find($this->demandeEnvoyee) : null;

        return view('livewire.support.contacter-support', [
            'destinataire' => MessageSupport::destinataire($utilisateur),
            'horaires' => \App\Models\ParametreSupport::actuel()->horaires,
            'demande' => $demande,
            'lienWhatsapp' => $demande ? MessageSupport::lien($demande, $utilisateur) : null,
            'lienEmail' => $demande ? MessageSupport::lienEmail($demande, $utilisateur) : null,
            'mesDemandes' => DemandeSupport::where('user_id', $utilisateur->id)
                ->orderByDesc('id')
                ->take(5)
                ->get(),
        ]);
    }
}
