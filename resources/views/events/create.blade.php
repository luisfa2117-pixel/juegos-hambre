<x-layout title="Crear evento">
    <div class="max-w-2xl">
        <a href="{{ route('events.index') }}" class="text-sm text-ash-muted hover:text-hazard">Volver a eventos</a>
        <h1 class="mt-2 mb-8 font-display text-4xl">Crear evento</h1>

        <form method="POST" action="{{ route('events.store') }}">
            @csrf
            @include('events._form')

            <button type="submit" class="btn mt-8">Crear evento</button>
        </form>
    </div>
</x-layout>
