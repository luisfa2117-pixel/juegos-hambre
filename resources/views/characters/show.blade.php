@use('App\Enums\EventPhase')

<x-layout :title="$character->name">
    <a href="{{ route('characters.index') }}" class="text-sm text-ash-muted hover:text-hazard">Volver a personajes</a>

    <div class="mt-4 flex flex-wrap items-center gap-5">
        <x-tribute-avatar :name="$character->name" :district="$character->district->slug" :image="$character->image_url" size="lg" />
        <div>
            <h1 class="font-display text-4xl">{{ $character->name }}</h1>
            <p class="text-ash-muted">{{ $character->district->name }}. {{ $character->gender->label() }}.</p>
        </div>
        <a href="{{ route('characters.edit', $character) }}" class="btn-quiet ml-auto">Editar</a>
    </div>

    @if ($character->games_count === 0)
        <p class="mt-8 rounded-sm border border-dashed border-asphalt-line p-8 text-center text-ash-muted">
            Todavía no ha jugado ninguna partida.
        </p>
    @else
        @php
            $stats = [
                'Partidas' => $character->games_count,
                'Victorias' => $character->wins_count,
                'Asesinatos' => (int) $character->kills_total,
                'Muertes' => $character->deaths_count,
                'Caídas en la Cornucopia' => $character->bloodbath_deaths_count,
            ];
        @endphp

        <dl class="mt-8 grid grid-cols-2 gap-px overflow-hidden rounded-sm bg-asphalt-line sm:grid-cols-5">
            @foreach ($stats as $label => $value)
                <div class="bg-asphalt-raised p-4">
                    <dt class="text-sm text-ash-muted">{{ $label }}</dt>
                    <dd class="font-condensed text-3xl font-semibold">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>

        <h2 class="mt-10 mb-3 font-condensed text-2xl font-semibold">Sus partidas</h2>
        <ul class="divide-y divide-asphalt-line border-y border-asphalt-line">
            @foreach ($appearances as $appearance)
                <li>
                    <a href="{{ route('games.show', [$appearance->game_id, 'round' => $appearance->game->total_rounds]) }}#resultado"
                       class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 px-2 py-3 hover:bg-asphalt-raised">
                        <span>
                            <span class="font-semibold">Partida #{{ $appearance->game_id }}</span>
                            <span class="text-sm text-ash-muted">{{ $appearance->game->created_at->format('d/m/Y') }}</span>
                        </span>
                        <span class="text-sm">
                            @if ($appearance->died_in_round === null && $appearance->placement === 1)
                                <span class="font-semibold text-hazard">Ganó</span>
                            @else
                                Puesto {{ $appearance->placement }}.
                                @if ($appearance->died_in_round)
                                    Murió en: {{ mb_strtolower(EventPhase::roundTitle($appearance->died_in_round)) }}{{ $appearance->killed_by ? ", a manos de {$appearance->killed_by}" : '' }}.
                                @endif
                            @endif
                            @if ($appearance->kills > 0)
                                <span class="text-blood">{{ trans_choice(':count asesinato|:count asesinatos', $appearance->kills) }}.</span>
                            @endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layout>
