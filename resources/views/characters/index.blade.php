<x-layout title="Personajes">
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-4xl">Personajes</h1>
            <p class="mt-1 text-ash-muted">
                Los tributos que pueden entrar en la arena. Ahora mismo hay {{ $total }}.
            </p>
        </div>

        <a href="{{ route('characters.create') }}" class="btn">Crear personaje</a>
    </div>

    @if ($total === 0)
        <p class="rounded-sm border border-dashed border-asphalt-line p-8 text-center text-ash-muted">
            Todavía no hay personajes. Crea al menos dos para poder jugar una partida.
        </p>
    @endif

    <div class="space-y-10">
        @foreach ($districts as $district)
            <section>
                <div class="mb-3 flex flex-wrap items-baseline gap-x-3 border-b border-asphalt-line pb-2">
                    <h2 class="font-condensed text-2xl font-semibold">{{ $district->name }}</h2>
                    <p class="text-sm text-ash-muted">
                        {{ sprintf('%+d', $district->strength_bonus) }} fuerza,
                        {{ sprintf('%+d', $district->energy_bonus) }} energía
                    </p>
                </div>

                @if ($district->characters->isEmpty())
                    <p class="text-sm text-ash-muted">Nadie de este distrito todavía.</p>
                @else
                    <ul class="grid gap-3 sm:grid-cols-2">
                        @foreach ($district->characters as $character)
                            <li class="flex items-center justify-between gap-4 rounded-sm bg-asphalt-raised px-4 py-3">
                                <x-tribute-avatar :name="$character->name" :district="$district->slug" :image="$character->image_url" />
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('characters.show', $character) }}" class="text-lg font-semibold hover:text-hazard">{{ $character->name }}</a>
                                    <p class="text-sm text-ash-muted">
                                        {{ $character->gender->label() }}.
                                        @if ($character->games_count === 0)
                                            Aún no ha jugado.
                                        @else
                                            {{ trans_choice(':count partida|:count partidas', $character->games_count) }},
                                            {{ trans_choice(':count victoria|:count victorias', $character->wins_count) }},
                                            {{ trans_choice(':count asesinato|:count asesinatos', (int) $character->kills_total) }}.
                                        @endif
                                    </p>
                                </div>

                                <div class="flex shrink-0 gap-1">
                                    <a href="{{ route('characters.edit', $character) }}" class="btn-quiet">Editar</a>

                                    <form method="POST" action="{{ route('characters.destroy', $character) }}"
                                          onsubmit="return confirm(@js("¿Eliminar a {$character->name}? Sus partidas guardadas no se borran."))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-danger">Eliminar</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    </div>
</x-layout>
