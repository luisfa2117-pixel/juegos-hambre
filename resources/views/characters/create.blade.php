<x-layout title="Crear personaje">
    <div class="max-w-lg">
        <a href="{{ route('characters.index') }}" class="text-sm text-ash-muted hover:text-hazard">Volver a personajes</a>
        <h1 class="mt-2 mb-8 font-display text-4xl">Crear personaje</h1>

        <form method="POST" action="{{ route('characters.store') }}">
            @csrf
            @include('characters._form')

            <button type="submit" class="btn mt-8">Crear personaje</button>
        </form>
    </div>
</x-layout>
