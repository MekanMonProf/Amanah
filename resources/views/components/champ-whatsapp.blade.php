@props([
    'champ',        // nom de la propriete Livewire qui porte le numero WhatsApp
    'drapeau',      // nom de la propriete booleenne « meme numero »
    'actif',        // sa valeur courante
    'placeholder' => null,
])

{{--
    Le WhatsApp est presque toujours le meme numero que le telephone : la case
    est donc cochee par defaut et le champ reste masque. La decocher revele un
    champ a part, pour les cas — frequents dans la diaspora — ou l on garde un
    numero local pour les appels et un autre sur WhatsApp.
--}}
<div class="mt-1">
    <label class="inline-flex items-center gap-2 text-sm text-gray-600">
        <input type="checkbox" wire:model.live="{{ $drapeau }}"
               class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-700">
        {{ __("Même numéro sur WhatsApp") }}
    </label>

    @unless ($actif)
        <input type="text" wire:model="{{ $champ }}"
               placeholder="{{ $placeholder ?? __('Numéro WhatsApp') }}"
               class="w-full border rounded px-3 py-2 mt-1">
        @error($champ) <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
    @endunless
</div>
