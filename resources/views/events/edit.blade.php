<x-layout title="Editar evento">
    <div class="max-w-2xl">
        <a href="{{ route('events.index', ['category' => $event->category]) }}" class="text-sm text-ash-muted hover:text-hazard">Volver a eventos</a>
        <h1 class="mt-2 mb-8 font-display text-4xl">Editar evento</h1>

        <form method="POST" action="{{ route('events.update', $event) }}">
            @csrf
            @method('PUT')
            @include('events._form')

            <button type="submit" class="btn mt-8">Guardar cambios</button>
        </form>
    </div>
</x-layout>
