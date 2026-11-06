<x-layout title="Editar a {{ $character->name }}">
    <div class="max-w-lg">
        <a href="{{ route('characters.index') }}" class="text-sm text-ash-muted hover:text-hazard">Volver a personajes</a>
        <h1 class="mt-2 mb-8 font-display text-4xl">Editar a {{ $character->name }}</h1>

        <form method="POST" action="{{ route('characters.update', $character) }}">
            @csrf
            @method('PUT')
            @include('characters._form')

            <button type="submit" class="btn mt-8">Guardar cambios</button>
        </form>
    </div>
</x-layout>
