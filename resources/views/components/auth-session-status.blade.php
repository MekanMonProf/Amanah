@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-primaire-700']) }}>
        {{ $status }}
    </div>
@endif
