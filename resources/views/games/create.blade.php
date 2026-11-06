<x-layout title="Nueva partida">
    <a href="{{ route('games.index') }}" class="text-sm text-ash-muted hover:text-hazard">Volver a partidas</a>
    <h1 class="mt-2 font-display text-4xl">Nueva partida</h1>

    @if ($totalCharacters < 2)
        <p class="mt-6 rounded-sm border border-dashed border-asphalt-line p-8 text-ash-muted">
            Hacen falta al menos dos personajes para jugar.
            <a href="{{ route('characters.create') }}" class="text-hazard underline">Crea un personaje</a>.
        </p>
    @else
        <p class="mt-1 mb-8 text-ash-muted">Elige quién entra en la arena. Hacen falta al menos dos.</p>

        <form method="POST" action="{{ route('games.store') }}">
            @csrf

            @error('characters') <p class="field-error mb-4">{{ $message }}</p> @enderror

            <div class="mb-6 flex gap-2">
                <button type="button" class="btn-quiet" data-check="all">Marcar todos</button>
                <button type="button" class="btn-quiet" data-check="none">Desmarcar todos</button>
            </div>

            <div class="grid gap-8 sm:grid-cols-2">
                @foreach ($districts as $district)
                    <fieldset>
                        <legend class="mb-2 font-condensed text-xl font-semibold">{{ $district->name }}</legend>

                        <div class="space-y-1">
                            @foreach ($district->characters as $character)
                                <label class="flex cursor-pointer items-center gap-3 rounded-sm px-2 py-1.5 hover:bg-asphalt-raised">
                                    <input type="checkbox" name="characters[]" value="{{ $character->id }}" class="size-4 accent-hazard"
                                           @checked(in_array($character->id, old('characters', $allIds)))>
                                    <x-tribute-avatar :name="$character->name" :district="$district->slug" :image="$character->image_url" size="sm" />
                                    {{ $character->name }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>

            <details class="mt-8 max-w-sm">
                <summary class="cursor-pointer text-ash-muted hover:text-ash">Repetir una partida concreta</summary>
                <div class="mt-3">
                    <label for="seed" class="field-label">Semilla</label>
                    <input id="seed" name="seed" type="number" min="1" max="999999" value="{{ old('seed') }}" class="field">
                    <p class="mt-1 text-sm text-ash-muted">
                        Con la misma semilla y los mismos tributos, la partida sale igual. Déjalo vacío para una partida nueva.
                    </p>
                    @error('seed') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </details>

            <button type="submit" class="btn mt-8">Empezar la partida</button>
        </form>

        <script>
            document.querySelectorAll('[data-check]').forEach(button => {
                button.addEventListener('click', () => {
                    const checked = button.dataset.check === 'all';
                    document.querySelectorAll('input[name="characters[]"]').forEach(box => box.checked = checked);
                });
            });
        </script>
    @endif
</x-layout>
