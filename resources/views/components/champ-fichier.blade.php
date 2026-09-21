@props(['model', 'accept' => null])

{{--
    Champ de fichier traduisible.

    Le bouton d un <input type="file"> natif et son « Aucun fichier choisi » sont dessines
    par le navigateur, dans la langue du navigateur : ni HTML ni CSS ne peuvent les
    traduire. On masque donc l input (sr-only, il reste accessible au clavier et aux
    lecteurs d ecran) et on declenche la selection par un <label for>, dont le texte est
    le notre. Le nom du fichier choisi est affiche par Alpine, immediatement, sans
    attendre la fin du televersement.
--}}

@php
    $identifiant = 'fichier-' . \Illuminate\Support\Str::slug($model) . '-' . \Illuminate\Support\Str::random(5);
@endphp

<div x-data="{ nom: '', aucun: @js(__('Aucun fichier choisi')) }" {{ $attributes->merge(['class' => 'mt-1']) }}>
    <input type="file"
           id="{{ $identifiant }}"
           wire:model="{{ $model }}"
           @if ($accept) accept="{{ $accept }}" @endif
           class="sr-only"
           x-on:change="nom = $event.target.files.length ? $event.target.files[0].name : ''">

    <div class="flex items-center gap-3">
        <label for="{{ $identifiant }}"
               class="cursor-pointer text-sm text-gray-700 border border-gray-300 rounded-lg px-4 py-2 hover:bg-gray-50 whitespace-nowrap">
            {{ __("Choisir un fichier") }}
        </label>
        <span class="text-sm text-gray-500 truncate" x-text="nom || aucun"></span>
    </div>
</div>
