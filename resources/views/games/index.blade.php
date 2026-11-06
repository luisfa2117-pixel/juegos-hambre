<x-layout title="Partidas">
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-4xl">Partidas</h1>
            <p class="mt-1 text-ash-muted">Todas las partidas jugadas, de la más reciente a la más antigua.</p>
        </div>

        <a href="{{ route('games.create') }}" class="btn">Nueva partida</a>
    </div>

    @if ($games->isEmpty())
        <div class="rounded-sm border border-dashed border-asphalt-line p-8 text-center">
            <p class="text-ash-muted">Todavía no has jugado ninguna partida.</p>
            <a href="{{ route('games.create') }}" class="btn mt-4">Jugar la primera</a>
        </div>
    @else
        <ul class="divide-y divide-asphalt-line border-y border-asphalt-line">
            @foreach ($games as $game)
                @php $winner = $game->winner(); @endphp
                <li>
                    <a href="{{ route('games.show', $game) }}"
                       class="flex items-center gap-4 px-2 py-4 hover:bg-asphalt-raised focus-visible:outline-2 focus-visible:outline-hazard">
                        @if ($winner)
                            <x-tribute-avatar :name="$winner->name" :district="$winner->district->slug" :image="$winner->character?->image_url" />
                        @else
                            <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-full border border-dashed border-asphalt-line text-ash-muted" aria-hidden="true">?</span>
                        @endif

                        <div class="min-w-0 flex-1">
                            <p class="font-semibold">
                                Partida #{{ $game->id }}:
                                {{ $winner ? "ganó {$winner->name}" : 'sin ganador' }}
                            </p>
                            <p class="text-sm text-ash-muted">
                                {{ $game->created_at->format('d/m/Y H:i') }},
                                {{ $game->tributes->count() }} tributos,
                                {{ trans_choice(':count ronda|:count rondas', $game->total_rounds) }}
                            </p>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $games->links() }}</div>
    @endif
</x-layout>
