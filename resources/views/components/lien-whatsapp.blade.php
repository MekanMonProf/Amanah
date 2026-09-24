@props([
    'numero',
    'telephone' => null,   // pour ne rappeler le numero que s il differe
])

@php
    // wa.me n accepte que des chiffres : ni « + », ni espaces, ni tirets.
    $chiffres = preg_replace('/\D+/', '', (string) $numero);
    $memeQueLeTelephone = $telephone !== null && \App\Support\Telephone::identiques($numero, $telephone);
@endphp

@if ($chiffres !== '')
    <a href="https://wa.me/{{ $chiffres }}" target="_blank" rel="noopener"
       {{ $attributes->class(['inline-flex items-center gap-1 text-emerald-700 hover:underline']) }}
       title="{{ __('Écrire sur WhatsApp') }}">
        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.87 9.87 0 004.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0012.04 2zm5.8 14.08c-.24.68-1.42 1.31-1.96 1.35-.5.04-.98.22-3.3-.69-2.79-1.1-4.56-3.95-4.7-4.13-.14-.18-1.12-1.49-1.12-2.85s.71-2.02.97-2.3c.25-.27.55-.34.73-.34.18 0 .37 0 .53.01.17.01.4-.07.62.48.24.57.8 1.97.87 2.11.07.14.12.31.02.49-.09.18-.14.29-.28.45-.14.16-.29.36-.42.48-.14.14-.28.29-.12.57.16.28.72 1.19 1.55 1.93 1.06.95 1.96 1.24 2.24 1.38.28.14.44.12.6-.07.17-.19.69-.8.88-1.08.18-.28.37-.23.62-.14.25.09 1.6.76 1.87.9.28.14.46.21.53.32.07.12.07.66-.17 1.34z"/>
        </svg>
        <span>{{ $memeQueLeTelephone ? __('WhatsApp') : $numero }}</span>
    </a>
@endif
