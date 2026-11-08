@props([
    'title' => 'Juegos del Hambre',
    'description' => 'Simulador de los Juegos del Hambre en una Norteamérica futurista en ruinas: crea tributos, mira cómo sobreviven ronda a ronda y patrocina a tus favoritos.',
])

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Lo usa el JavaScript que hace peticiones con fetch (por ejemplo, la vista previa de eventos) --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} | Juegos del Hambre</title>
    <meta name="description" content="{{ $description }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    {{-- Cómo se ve el enlace al compartirlo (WhatsApp, LinkedIn...) --}}
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title }} | Juegos del Hambre">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if (file_exists(public_path('og-image.png')))
        <meta property="og:image" content="{{ asset('og-image.png') }}">
    @endif

    {{-- Las fuentes ya no vienen de Google: van dentro del CSS que compila Vite (ver app.css) --}}

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-asphalt font-sans text-ash antialiased">
    <header class="border-b border-asphalt-line">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-x-6 gap-y-2 px-4 py-4">
            <a href="{{ url('/') }}" class="font-display text-2xl text-hazard">Juegos del Hambre</a>

            @auth
                @php
                    $sections = ['games' => 'Partidas', 'characters' => 'Personajes', 'stats' => 'Estadísticas'];

                    // Eventos solo aparece en el menú de los administradores
                    if (auth()->user()->can('manage-events')) {
                        $sections['events'] = 'Eventos';
                    }
                @endphp

                <nav class="flex flex-wrap items-center gap-x-5 gap-y-2">
                    @foreach ($sections as $section => $label)
                        <a href="{{ route("{$section}.index") }}"
                           @class([
                               'border-b-2 pb-0.5 hover:text-hazard',
                               'border-hazard text-hazard' => request()->routeIs("{$section}.*"),
                               'border-transparent' => ! request()->routeIs("{$section}.*"),
                           ])>
                            {{ $label }}
                        </a>
                    @endforeach

                    <form method="POST" action="{{ route('logout') }}" class="flex items-center gap-3 border-l border-asphalt-line pl-5"
                          @if (auth()->user()->is_guest)
                              onsubmit="return confirm('Eres un invitado: al salir se borrarán tus tributos y tus partidas. ¿Seguro?')"
                          @endif>
                        @csrf
                        <span class="text-sm text-ash-muted">{{ auth()->user()->name }}</span>
                        <button type="submit" class="btn-quiet">{{ auth()->user()->is_guest ? 'Salir y borrar' : 'Salir' }}</button>
                    </form>
                </nav>
            @endauth
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8">
        @if (session('status'))
            <p role="status" class="mb-6 rounded-sm border-l-4 border-ally bg-asphalt-raised px-4 py-3">
                {{ session('status') }}
            </p>
        @endif

        @if (session('error'))
            <p role="alert" class="mb-6 rounded-sm border-l-4 border-blood bg-asphalt-raised px-4 py-3">
                {{ session('error') }}
            </p>
        @endif

        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-5xl border-t border-asphalt-line px-4 py-6 text-sm text-ash-muted">
        Un proyecto de {{ config('juegos.author') }}.
        <a href="{{ route('about') }}" class="underline hover:text-hazard">Acerca del proyecto y privacidad</a>.
        <a href="{{ config('juegos.repository') }}" class="underline hover:text-hazard">El código, en GitHub</a>.
    </footer>
</body>
</html>
