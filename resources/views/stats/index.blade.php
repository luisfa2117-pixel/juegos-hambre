<x-layout title="Estadísticas">
    <h1 class="font-display text-4xl">Estadísticas</h1>
    <p class="mt-1 text-ash-muted">
        Lo que ha pasado en las {{ trans_choice(':count partida jugada|:count partidas jugadas', $totalGames) }}.
    </p>

    @if ($totalGames === 0)
        <div class="mt-8 rounded-sm border border-dashed border-asphalt-line p-8 text-center">
            <p class="text-ash-muted">Aún no hay estadísticas: juega alguna partida primero.</p>
            <a href="{{ route('games.create') }}" class="btn mt-4">Nueva partida</a>
        </div>
    @else
        @php
            $rankings = [
                ['title' => 'Más victorias', 'rows' => $mostWins, 'value' => fn ($c) => trans_choice(':count victoria|:count victorias', $c->wins_count)],
                ['title' => 'Más asesinatos', 'rows' => $mostKills, 'value' => fn ($c) => trans_choice(':count asesinato|:count asesinatos', (int) $c->kills_total)],
                ['title' => 'Más letales por partida', 'note' => 'Con al menos 3 partidas jugadas.', 'rows' => $deadliest, 'value' => fn ($c) => number_format($c->kills_total / $c->games_count, 1, ',', '') . ' por partida'],
                ['title' => 'Siempre caen en la Cornucopia', 'rows' => $bloodbath, 'value' => fn ($c) => trans_choice(':count vez|:count veces', $c->bloodbath_deaths_count)],
            ];
        @endphp

        <div class="mt-8 grid gap-10 md:grid-cols-2">
            <section>
                <h2 class="font-condensed text-2xl font-semibold">Tus apuestas</h2>

                @if ($bets->total === 0)
                    <p class="mt-3 text-sm text-ash-muted">
                        Aún no has apostado. Al crear una partida, elige quién crees que ganará.
                    </p>
                @else
                    <div class="mt-3 flex items-baseline gap-6">
                        <p>
                            <span class="font-condensed text-5xl font-semibold text-hazard">
                                {{ $bets->settled > 0 ? number_format(100 * $bets->won / $bets->settled, 0, ',', '') . ' %' : '—' }}
                            </span>
                            <span class="block text-sm text-ash-muted">de acierto</span>
                        </p>
                        <p class="text-sm">
                            {{ trans_choice(':count acertada|:count acertadas', $bets->won) }} de {{ $bets->settled }}.
                            @if ($bets->total > $bets->settled)
                                <span class="block text-ash-muted">
                                    {{ trans_choice(':count partida sin terminar de ver|:count partidas sin terminar de ver', $bets->total - $bets->settled) }}
                                </span>
                            @endif
                        </p>
                    </div>
                @endif
            </section>

            <section>
                <h2 class="font-condensed text-2xl font-semibold">Rivalidades</h2>
                <p class="text-sm text-ash-muted">Quién ha matado más veces a quién (al menos dos).</p>

                @if ($rivalries->isEmpty())
                    <p class="mt-3 text-sm text-ash-muted">Todavía no hay cuentas pendientes.</p>
                @else
                    <ol class="mt-3 divide-y divide-asphalt-line border-y border-asphalt-line">
                        @foreach ($rivalries as $rivalry)
                            <li class="flex items-center gap-2 py-2">
                                <x-tribute-avatar :name="$rivalry->killer->name" :district="$rivalry->killer->district->slug" :image="$rivalry->killer->image_url" size="sm" />
                                <a href="{{ route('characters.show', $rivalry->killer) }}" class="font-semibold hover:text-hazard">{{ $rivalry->killer->name }}</a>
                                <span class="text-blood" aria-label="ha matado a">→</span>
                                <x-tribute-avatar :name="$rivalry->victim->name" :district="$rivalry->victim->district->slug" :image="$rivalry->victim->image_url" size="sm" />
                                <a href="{{ route('characters.show', $rivalry->victim) }}" class="flex-1 font-semibold hover:text-hazard">{{ $rivalry->victim->name }}</a>
                                <span class="text-sm text-ash-muted">{{ trans_choice(':count vez|:count veces', $rivalry->times) }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            @foreach ($rankings as $ranking)
                <section>
                    <h2 class="font-condensed text-2xl font-semibold">{{ $ranking['title'] }}</h2>
                    @isset($ranking['note']) <p class="text-sm text-ash-muted">{{ $ranking['note'] }}</p> @endisset

                    @if ($ranking['rows']->isEmpty())
                        <p class="mt-3 text-sm text-ash-muted">Todavía nadie.</p>
                    @else
                        <ol class="mt-3 divide-y divide-asphalt-line border-y border-asphalt-line">
                            @foreach ($ranking['rows'] as $character)
                                <li class="flex items-center gap-3 py-2">
                                    <span class="w-6 text-right font-condensed text-lg text-ash-muted">{{ $loop->iteration }}.</span>
                                    <x-tribute-avatar :name="$character->name" :district="$character->district->slug" :image="$character->image_url" size="sm" />
                                    <a href="{{ route('characters.show', $character) }}" class="flex-1 font-semibold hover:text-hazard">{{ $character->name }}</a>
                                    <span class="text-sm text-ash-muted">{{ $ranking['value']($character) }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            @endforeach

            <section>
                <h2 class="font-condensed text-2xl font-semibold">Las partidas más sangrientas</h2>
                <p class="text-sm text-ash-muted">Más asesinatos de un tributo en una sola partida.</p>

                @if ($bloodiestGames->isEmpty())
                    <p class="mt-3 text-sm text-ash-muted">Todavía nadie ha matado a nadie.</p>
                @else
                    <ol class="mt-3 divide-y divide-asphalt-line border-y border-asphalt-line">
                        @foreach ($bloodiestGames as $tribute)
                            <li class="flex items-center gap-3 py-2">
                                <span class="w-6 text-right font-condensed text-lg text-ash-muted">{{ $loop->iteration }}.</span>
                                <x-tribute-avatar :name="$tribute->name" :district="$tribute->district->slug" :image="$tribute->character?->image_url" size="sm" />
                                <span class="flex-1 font-semibold">{{ $tribute->name }}</span>
                                <a href="{{ route('games.show', $tribute->game_id) }}" class="text-sm text-ash-muted hover:text-hazard">
                                    {{ trans_choice(':count asesinato|:count asesinatos', $tribute->kills) }} en la partida #{{ $tribute->game_id }}
                                </a>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            <section>
                <h2 class="font-condensed text-2xl font-semibold">Personalidades</h2>
                <p class="text-sm text-ash-muted">De cada vez que ha jugado alguien con esa personalidad, cuántas ha ganado.</p>

                <table class="mt-3 w-full text-left text-sm">
                    <thead class="text-ash-muted">
                        <tr class="border-b border-asphalt-line">
                            <th class="py-2 font-normal">Personalidad</th>
                            <th class="py-2 text-right font-normal">Jugadas</th>
                            <th class="py-2 text-right font-normal">Ganadas</th>
                            <th class="py-2 text-right font-normal">% de victoria</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-asphalt-line">
                        @foreach ($personalities as $row)
                            <tr>
                                <td class="py-2">{{ $row->label }}</td>
                                <td class="py-2 text-right">{{ $row->played }}</td>
                                <td class="py-2 text-right">{{ $row->won }}</td>
                                <td class="py-2 text-right font-semibold">{{ number_format(100 * $row->won / $row->played, 1, ',', '') }} %</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            <section>
                <h2 class="font-condensed text-2xl font-semibold">Distritos</h2>
                <p class="text-sm text-ash-muted">De cada vez que alguien del distrito ha jugado, cuántas ha ganado.</p>

                <table class="mt-3 w-full text-left text-sm">
                    <thead class="text-ash-muted">
                        <tr class="border-b border-asphalt-line">
                            <th class="py-2 font-normal">Distrito</th>
                            <th class="py-2 text-right font-normal">Jugadas</th>
                            <th class="py-2 text-right font-normal">Ganadas</th>
                            <th class="py-2 text-right font-normal">% de victoria</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-asphalt-line">
                        @foreach ($districts as $row)
                            <tr>
                                <td class="py-2">{{ $row->district->name }}</td>
                                <td class="py-2 text-right">{{ $row->played }}</td>
                                <td class="py-2 text-right">{{ $row->won }}</td>
                                <td class="py-2 text-right font-semibold">{{ number_format(100 * $row->won / $row->played, 1, ',', '') }} %</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        </div>
    @endif
</x-layout>
