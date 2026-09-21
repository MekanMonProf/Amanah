<div class="flex items-center gap-1" role="group" aria-label="{{ __('Langue de l\'interface') }}">
    @foreach ($langues as $code => $infos)
        <button type="button" wire:click="changer('{{ $code }}')"
                @class([
                    'px-2 py-1 text-xs rounded border transition',
                    'bg-emerald-700 text-white border-emerald-700' => $langue === $code,
                    'text-gray-600 border-gray-300 hover:bg-gray-50' => $langue !== $code,
                ])
                @if ($langue === $code) aria-current="true" @endif
                title="{{ $infos['libelle'] }}">
            {{ $infos['libelle'] }}
        </button>
    @endforeach
</div>
