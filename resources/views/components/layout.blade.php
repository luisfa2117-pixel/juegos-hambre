@props(['title' => 'Juegos del Hambre'])

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | Juegos del Hambre</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600&family=Saira+Stencil+One&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-asphalt font-sans text-ash antialiased">
    <header class="border-b border-asphalt-line">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-x-6 gap-y-2 px-4 py-4">
            <a href="{{ url('/') }}" class="font-display text-2xl text-hazard">Juegos del Hambre</a>

            <nav class="flex gap-5">
                <a href="{{ route('characters.index') }}"
                   @class([
                       'border-b-2 pb-0.5 hover:text-hazard',
                       'border-hazard text-hazard' => request()->routeIs('characters.*'),
                       'border-transparent' => ! request()->routeIs('characters.*'),
                   ])>
                    Personajes
                </a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8">
        @if (session('status'))
            <p role="status" class="mb-6 rounded-sm border-l-4 border-ally bg-asphalt-raised px-4 py-3">
                {{ session('status') }}
            </p>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
