@use('App\Enums\EventPhase')
@use('App\Simulation\Tribute')
@use('App\Support\TextHighlighter')

<x-layout title="Partida #{{ $game->id }}">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('games.index') }}" class="text-sm text-ash-muted hover:text-hazard">Volver a partidas</a>
            <h1 class="mt-2 font-display text-4xl">Partida #{{ $game->id }}</h1>
            <p class="mt-1 text-ash-muted">
                Jugada el {{ $game->created_at->format('d/m/Y') }} a las {{ $game->created_at->format('H:i') }}
                con {{ $game->tributes->count() }} tributos. Semilla {{ $game->seed }}.
            </p>
        </div>

        <form method="POST" action="{{ route('games.destroy', $game) }}"
              onsubmit="return confirm(@js("¿Eliminar la partida #{$game->id}? No se puede deshacer."))">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-danger">Eliminar partida</button>
        </form>
    </div>

    <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_18rem]">
        {{-- El relato, ronda a ronda --}}
        <div>
            @foreach ($visibleRounds as $gameRound)
                @php
                    $deaths = collect($gameRound->events)->pluck('deaths')->flatten();
                    $phase = EventPhase::forRound($gameRound->number);
                @endphp

                <section aria-labelledby="ronda-{{ $gameRound->number }}" class="mt-10 first:mt-0">
                    <h2 id="ronda-{{ $gameRound->number }}" @class([
                        'mb-2 scroll-mt-6 rounded-sm p-2',
                        'blood-stripes' => $phase === EventPhase::Bloodbath,
                        'hazard-stripes' => $phase === EventPhase::Day,
                        'night-stripes' => $phase === EventPhase::Night,
                    ])>
                        <span @class([
                            'rounded-sm bg-asphalt px-3 py-1 font-display text-2xl',
                            'text-blood' => $phase === EventPhase::Bloodbath,
                            'text-hazard' => $phase === EventPhase::Day,
                            'text-night' => $phase === EventPhase::Night,
                        ])>{{ EventPhase::roundTitle($gameRound->number) }}</span>
                    </h2>

                    <ul class="divide-y divide-asphalt-line/60">
                        @foreach ($gameRound->events as $event)
                            {{-- El anuncio de una catástrofe de los Vigilantes --}}
                            @if ($event['arena'] ?? null)
                                <li class="my-3 rounded-sm border-2 border-blood bg-blood/10 p-4">
                                    <p class="font-display text-2xl text-blood">{{ $event['arena'] }}</p>
                                    <p class="mt-1">{{ $event['text'] }}</p>
                                </li>
                                @continue
                            @endif

                            <li class="py-3">
                                <p class="text-ash/90">{{ TextHighlighter::names($event['text'], $tributeNames) }}</p>

                                @if ($event['notes'])
                                    <ul class="mt-1 space-y-0.5 text-sm text-ash-muted">
                                        @foreach ($event['notes'] as $note)
                                            <li>{{ $note }}</li>
                                        @endforeach
                                    </ul>
                                @endif

                                @foreach ($event['deaths'] as $name)
                                    @php $killers = collect($event['kills'] ?? [])->firstWhere('victim', $name)['killers'] ?? []; @endphp
                                    <p class="mt-1 font-semibold text-blood">
                                        {{ $name }} ha muerto{{ $killers ? ' a manos de ' . collect($killers)->join(', ', ' y ') : '' }}.
                                    </p>
                                @endforeach
                            </li>
                        @endforeach
                    </ul>

                    @if ($deaths->isNotEmpty())
                        <p class="mt-2 border-l-4 border-blood pl-3 text-sm">
                            {{ trans_choice('Se oye :count cañonazo|Se oyen :count cañonazos', $deaths->count()) }}:
                            {{ $deaths->join(', ', ' y ') }}.
                        </p>
                    @endif

                    {{-- Al final de cada noche, los rostros de los caídos del día se proyectan en el cielo --}}
                    @if ($phase === EventPhase::Night)
                        @php
                            // La noche 1 recoge también el baño de sangre; las demás, el día y la noche anteriores
                            $from = $gameRound->number === 3 ? 1 : $gameRound->number - 1;
                            $fallen = $game->tributes
                                ->filter(fn ($t) => $t->died_in_round !== null && $t->died_in_round >= $from && $t->died_in_round <= $gameRound->number)
                                ->sortBy('died_in_round');
                        @endphp

                        <div class="mt-4 rounded-sm border border-asphalt-line bg-asphalt-raised p-4">
                            <p class="font-condensed text-lg font-semibold text-night">Los rostros en el cielo</p>
                            <p class="text-sm text-ash-muted">
                                {{ $gameRound->number === 3 ? 'Los caídos desde el baño de sangre.' : 'Los caídos del día ' . intdiv($gameRound->number, 2) . '.' }}
                            </p>

                            @if ($fallen->isEmpty())
                                <p class="mt-3 text-sm">Esta noche el cielo está vacío: no ha caído nadie.</p>
                            @else
                                <ul class="mt-3 flex flex-wrap gap-x-6 gap-y-3">
                                    @foreach ($fallen as $tribute)
                                        <li class="flex items-center gap-2">
                                            <x-tribute-avatar :name="$tribute->name" :district="$tribute->district->slug" :image="$tribute->character?->image_url" />
                                            <span>
                                                <span class="block font-semibold">{{ $tribute->name }}</span>
                                                <span class="block text-xs text-ash-muted">{{ $tribute->district->name }}</span>
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif
                </section>
            @endforeach

            @if (! $finished)
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('games.show', [$game, 'round' => $round + 1]) }}#ronda-{{ $round + 1 }}" class="btn">Siguiente ronda</a>
                    <a href="{{ route('games.show', [$game, 'round' => $game->total_rounds]) }}#resultado" class="btn-quiet">Ver el final</a>
                </div>
            @else
                {{-- Resumen final --}}
                <section id="resultado" class="mt-12 scroll-mt-6">
                    @if ($winner)
                        <div class="flex items-center gap-5 rounded-sm bg-asphalt-raised p-6">
                            <x-tribute-avatar :name="$winner->name" :district="$winner->district->slug" :image="$winner->character?->image_url" size="lg" />
                            <div>
                                <p class="font-display text-3xl text-hazard sm:text-4xl">{{ $winner->name }} gana los Juegos</p>
                                <p class="mt-1 text-ash-muted">
                                    {{ $winner->district->name }}. Sobrevivió {{ trans_choice(':count ronda|:count rondas', $game->total_rounds) }}.
                                </p>
                            </div>
                        </div>
                    @else
                        <div class="rounded-sm bg-asphalt-raised p-6">
                            <p class="font-display text-3xl">Nadie sobrevivió</p>
                            <p class="mt-1 text-ash-muted">Los últimos tributos cayeron en la misma ronda.</p>
                        </div>
                    @endif

                    <h2 class="mt-10 mb-3 font-condensed text-2xl font-semibold">Clasificación</h2>
                    <ol class="divide-y divide-asphalt-line border-y border-asphalt-line">
                        @foreach ($game->tributes as $tribute)
                            <li class="flex items-center gap-3 py-2">
                                <span class="w-8 text-right font-condensed text-xl text-ash-muted">{{ $tribute->placement }}.</span>
                                <x-tribute-avatar :name="$tribute->name" :district="$tribute->district->slug" :image="$tribute->character?->image_url" size="sm" />
                                <span class="flex-1">
                                    @if ($tribute->character_id)
                                        <a href="{{ route('characters.show', $tribute->character_id) }}" class="font-semibold hover:text-hazard">{{ $tribute->name }}</a>
                                    @else
                                        <span class="font-semibold">{{ $tribute->name }}</span>
                                    @endif
                                    <span class="text-sm text-ash-muted">{{ $tribute->district->name }}</span>
                                </span>
                                <span class="text-right text-sm text-ash-muted">
                                    {{ $tribute->died_in_round ? 'Murió en: ' . mb_strtolower(EventPhase::roundTitle($tribute->died_in_round)) : 'Sobrevivió' }}{{ $tribute->killed_by ? ", a manos de {$tribute->killed_by}" : '' }}
                                    @if ($tribute->kills > 0)
                                        <span class="block text-blood">{{ trans_choice(':count asesinato|:count asesinatos', $tribute->kills) }}</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ol>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('games.create') }}" class="btn">Jugar otra partida</a>

                        @if ($canReplay)
                            <form method="POST" action="{{ route('games.store') }}">
                                @csrf
                                <input type="hidden" name="seed" value="{{ $game->seed }}">
                                @foreach ($game->tributes as $tribute)
                                    <input type="hidden" name="characters[]" value="{{ $tribute->character_id }}">
                                @endforeach
                                <button type="submit" class="btn-quiet">Repetir esta partida</button>
                            </form>
                        @endif
                    </div>
                </section>
            @endif
        </div>

        {{-- Estado de los tributos tras la última ronda vista --}}
        <aside class="lg:sticky lg:top-6 lg:self-start">
            <h2 class="mb-3 font-condensed text-xl font-semibold">{{ EventPhase::afterRound($round) }}</h2>

            <ul class="space-y-3">
                @foreach ($snapshot as $state)
                    @php $tribute = $tributesByName[$state['name']]; @endphp

                    <li @class(['rounded-sm bg-asphalt-raised p-3', 'opacity-60' => ! $state['alive']])>
                        <div class="flex items-center gap-3">
                            <x-tribute-avatar :name="$state['name']" :district="$tribute->district->slug" :image="$tribute->character?->image_url" size="sm" :dead="! $state['alive']" />
                            <div class="min-w-0">
                                <p @class(['font-semibold', 'line-through decoration-blood' => ! $state['alive']])>{{ $state['name'] }}</p>
                                <p class="truncate text-xs text-ash-muted">
                                    {{ $tribute->district->name . (($state['personality'] ?? null) ? ". {$state['personality']}" : '') }}
                                </p>
                            </div>
                        </div>

                        @if ($state['alive'])
                            <dl class="mt-3 space-y-1.5 text-xs">
                                <div>
                                    <dt class="flex justify-between"><span>Vida</span><span>{{ $state['hp'] }}/{{ Tribute::MAX_HP }}</span></dt>
                                    <dd class="mt-0.5 h-1.5 rounded-full bg-asphalt-line">
                                        <div class="h-full rounded-full bg-blood" style="width: {{ $state['hp'] / Tribute::MAX_HP * 100 }}%"></div>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="flex justify-between"><span>Energía</span><span>{{ $state['energy'] }}/{{ $state['max_energy'] }}</span></dt>
                                    <dd class="mt-0.5 h-1.5 rounded-full bg-asphalt-line">
                                        <div class="h-full rounded-full bg-hazard" style="width: {{ $state['max_energy'] > 0 ? $state['energy'] / $state['max_energy'] * 100 : 0 }}%"></div>
                                    </dd>
                                </div>
                            </dl>

                            @if ($state['items'])
                                <p class="mt-2 text-xs"><span class="text-ash-muted">Mochila:</span> {{ implode(', ', $state['items']) }}</p>
                            @endif

                            @if ($state['allies'])
                                <p class="mt-1 text-xs text-ally">Alianza con {{ collect($state['allies'])->join(', ', ' y ') }}</p>
                            @endif
                        @else
                            <p class="mt-2 text-xs text-blood">Murió en: {{ mb_strtolower(EventPhase::roundTitle($tribute->died_in_round)) }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </aside>
    </div>
</x-layout>
