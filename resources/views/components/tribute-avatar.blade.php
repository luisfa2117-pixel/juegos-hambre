{{--
    Hueco de la imagen de un tributo. De momento: iniciales sobre el color de su distrito.
    Cuando los personajes tengan foto, solo habrá que cambiar este fichero.

    Uso: <x-tribute-avatar name="Avery" district="costa_este" size="sm" :dead="true" />
--}}
@props(['name', 'district' => null, 'size' => 'md', 'dead' => false])

@php
    $colors = [
        'costa_este' => '#7da7f0',
        'sur_quemado' => '#eb9a5c',
        'cinturon_oxidado' => '#c98a66',
        'norte_helado' => '#b9dcef',
        'pacifico_tecno' => '#b99be8',
    ];

    $sizes = [
        'sm' => 'size-8 text-xs',
        'md' => 'size-10 text-sm',
        'lg' => 'size-20 text-2xl',
    ];

    $initials = collect(preg_split('/\s+/', trim($name)))
        ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->take(2)
        ->implode('');
@endphp

<span {{ $attributes->class([
        'inline-flex shrink-0 items-center justify-center rounded-full font-semibold text-asphalt',
        $sizes[$size] ?? $sizes['md'],
        'opacity-40 grayscale' => $dead,
    ]) }}
    style="background-color: {{ $colors[$district] ?? '#a3acb6' }}"
    aria-hidden="true">{{ $initials }}</span>
