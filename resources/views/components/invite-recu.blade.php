@props([
    // L'écriture qui vient d'être enregistrée.
    'ecriture',
    // Où l'on repart une fois le reçu envoyé, ou renoncé.
    'retour',
    // Phrase de confirmation, propre à l'opération.
    'message' => null,
])

{{--
    Ce qu'on propose juste après avoir enregistré une opération.

    Le reçu vit aussi sur chaque ligne du journal du compte, mais l'y chercher
    suppose de savoir qu'il existe et de retrouver la bonne ligne. Le moment où
    l'on vient d'encaisser est celui où l'on tient encore l'investisseur au
    téléphone ou devant soi : c'est là que la question se pose.

    L'écran ne repart pas tout seul vers la fiche. Rien n'est plus agaçant qu'une
    page qui s'en va pendant qu'on lit ce qu'elle annonce ; c'est « Terminer » qui
    décide du départ.
--}}
@php($lienWhatsapp = \App\Support\Recu::lienWhatsapp($ecriture))

<div class="bg-primaire-50 border border-primaire-200 rounded-champ p-5">
    <p class="text-sm text-primaire-900 font-medium">
        ✓ {{ $message ?? __("Opération enregistrée.") }}
    </p>

    <p class="mt-1 text-sm text-primaire-800">
        {{ __("Solde du compte après opération : :solde", ['solde' => \App\Support\Montant::avecDevise((float) $ecriture->solde_apres)]) }}
    </p>

    <div class="mt-4 flex flex-wrap items-center gap-2">
        @if ($lienWhatsapp)
            <a href="{{ $lienWhatsapp }}" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-2 whitespace-nowrap text-sm text-white bg-primaire-600 rounded-champ px-4 py-2 hover:bg-primaire-700">
                <svg width="16" height="16" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M17.47 14.38c-.3-.15-1.77-.87-2.04-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.14-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.22 3.08c.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.77-.72 2.02-1.42.25-.7.25-1.29.17-1.42-.07-.13-.27-.2-.57-.35Z"/>
                    <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.87 9.87 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.84 9.84 0 0 0 12.04 2Zm0 18.13h-.01a8.2 8.2 0 0 1-4.18-1.15l-.3-.18-3.11.82.83-3.04-.2-.31a8.2 8.2 0 0 1-1.26-4.36c0-4.54 3.7-8.23 8.24-8.23 2.2 0 4.27.86 5.82 2.41a8.18 8.18 0 0 1 2.41 5.83c0 4.54-3.7 8.23-8.24 8.23Z"/>
                </svg>
                {{ __("Envoyer le reçu par WhatsApp") }}
            </a>
        @endif

        <a href="{{ route('ecritures.recu', $ecriture) }}" target="_blank"
           class="whitespace-nowrap text-sm text-primaire-800 border border-primaire-300 rounded-champ px-4 py-2 hover:bg-primaire-100">
            {{ __("Voir le reçu") }}
        </a>

        <a href="{{ $retour }}" wire:navigate
           class="whitespace-nowrap text-sm text-gray-600 border rounded-champ px-4 py-2 hover:bg-gray-100">
            {{ __("Terminer") }}
        </a>
    </div>

    @if ($lienWhatsapp)
        <p class="mt-2 text-xs text-primaire-700">
            {{ __("WhatsApp s'ouvre avec le message et le lien du reçu déjà écrits — il vous reste à l'envoyer.") }}
        </p>
    @else
        <p class="mt-2 text-xs text-amber-700">
            {{ __("Aucun numéro sur ce dossier : le reçu ne peut pas être envoyé. Renseignez un téléphone pour l'expédier.") }}
        </p>
    @endif
</div>
