@use('App\Enums\EventCategory')
@use('App\Support\EventPreview')
@use('App\Support\TextHighlighter')

<x-layout title="Eventos">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-4xl">Eventos</h1>
            <p class="mt-1 max-w-prose text-ash-muted">
                Todo lo que puede pasar en la arena. Los textos se muestran con tributos de ejemplo,
                tal como saldrán en una partida.
            </p>
        </div>

        <a href="{{ route('events.create', ['category' => $category]) }}" class="btn">Crear evento</a>
    </div>

    <nav aria-label="Tipos de evento" class="mb-6 flex flex-wrap gap-2">
        <a href="{{ route('events.index') }}"
           @class(['rounded-sm border px-3 py-1 text-sm', 'border-hazard text-hazard' => ! $category, 'border-asphalt-line hover:border-ash-muted' => $category])>
            Todos ({{ $counts->sum() }})
        </a>
        @foreach (EventCategory::editable() as $case)
            <a href="{{ route('events.index', ['category' => $case]) }}"
               @class(['rounded-sm border px-3 py-1 text-sm', 'border-hazard text-hazard' => $category === $case, 'border-asphalt-line hover:border-ash-muted' => $category !== $case])>
                {{ $case->label() }} ({{ $counts[$case->value] ?? 0 }})
            </a>
        @endforeach
    </nav>

    @if ($events->isEmpty())
        <p class="rounded-sm border border-dashed border-asphalt-line p-8 text-center text-ash-muted">
            No hay eventos de este tipo. Mientras no haya ninguno, la partida usará otro tipo en su lugar.
        </p>
    @else
        <ul class="divide-y divide-asphalt-line border-y border-asphalt-line">
            @foreach ($events as $event)
                <li class="flex flex-wrap items-start justify-between gap-x-6 gap-y-2 py-3">
                    <div class="min-w-0 flex-1 basis-80">
                        <p>{{ TextHighlighter::names(EventPreview::mixed($event->text), ['Avery', 'Dean', 'Hannah', 'Noah']) }}</p>
                        <p class="mt-0.5 text-sm text-ash-muted">
                            @unless ($category) {{ $event->category->label() }}. @endunless
                            @if ($event->phase) {{ $event->phase->label() }}. @endif
                            @if ($event->personality) Solo para: {{ $event->personality->label() }}. @endif
                            {{ EventPreview::effects($event) }}
                            @if ($event->requiresItem) Necesita: {{ $event->requiresItem->name }}. @endif
                            @if ($event->givesItem) Da: {{ $event->givesItem->name }}. @endif
                        </p>
                    </div>

                    <div class="flex shrink-0 gap-1">
                        <a href="{{ route('events.edit', $event) }}" class="btn-quiet">Editar</a>
                        <form method="POST" action="{{ route('events.destroy', $event) }}"
                              onsubmit="return confirm('¿Eliminar este evento?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger">Eliminar</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-layout>
