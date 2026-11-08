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

            <fieldset class="mt-10">
                <legend class="font-condensed text-xl font-semibold">La arena</legend>
                <p class="mb-4 text-sm text-ash-muted">Cada una tiene sus propios eventos y catástrofes.</p>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($terrains as $terrain)
                        <label class="flex cursor-pointer gap-3 rounded-sm border border-asphalt-line p-3 hover:bg-asphalt-raised has-checked:border-hazard has-checked:bg-asphalt-raised">
                            <input type="radio" name="terrain" value="{{ $terrain->value }}" class="mt-1 size-4 shrink-0 accent-hazard"
                                   @checked(old('terrain', 'city') === $terrain->value)>
                            <span>
                                <span class="block font-semibold">{{ $terrain->label() }}</span>
                                <span class="block text-sm text-ash-muted">{{ $terrain->description() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('terrain') <p class="field-error">{{ $message }}</p> @enderror
            </fieldset>

            <div class="mt-10 max-w-sm">
                <label for="bet" class="font-condensed text-xl font-semibold">¿Quién ganará?</label>
                <p class="mb-3 text-sm text-ash-muted">Apuesta por un tributo y lleva la cuenta de tus aciertos en Estadísticas.</p>
                <select id="bet" name="bet" class="field">
                    <option value="">Sin apuesta</option>
                    @foreach ($districts as $district)
                        <optgroup label="{{ $district->name }}">
                            @foreach ($district->characters as $character)
                                <option value="{{ $character->id }}" @selected((string) old('bet') === (string) $character->id)>{{ $character->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('bet') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <fieldset class="mt-10 max-w-2xl">
                <legend class="font-condensed text-xl font-semibold">Reglas especiales</legend>
                <p class="mb-4 text-sm text-ash-muted">Opcionales. Puedes combinar varias.</p>

                @error('rules') <p class="field-error mb-4">{{ $message }}</p> @enderror
                @error('rules.*') <p class="field-error mb-4">{{ $message }}</p> @enderror

                <label class="mb-4 flex cursor-pointer items-start gap-3 rounded-sm border border-blood/60 p-3 hover:bg-asphalt-raised">
                    <input type="checkbox" name="quell" value="1" class="mt-1 size-4 accent-blood" data-quell @checked(old('quell'))>
                    <span>
                        <span class="block font-semibold text-blood">Vasallaje de los 25</span>
                        <span class="block text-sm text-ash-muted">El Capitolio elige dos reglas por sorpresa. No sabrás cuáles hasta que empiece.</span>
                    </span>
                </label>

                <div class="grid gap-2 sm:grid-cols-2" data-rules>
                    @foreach ($gameRules as $rule)
                        <label class="flex cursor-pointer items-start gap-3 rounded-sm px-2 py-1.5 hover:bg-asphalt-raised">
                            <input type="checkbox" name="rules[]" value="{{ $rule->value }}" class="mt-1 size-4 accent-hazard"
                                   @checked(in_array($rule->value, old('rules', [])))>
                            <span>
                                <span class="block">{{ $rule->label() }}</span>
                                <span class="block text-sm text-ash-muted">{{ $rule->description() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <details class="mt-8 max-w-sm">
                <summary class="cursor-pointer text-ash-muted hover:text-ash">Repetir una partida concreta</summary>
                <div class="mt-3">
                    <label for="seed" class="field-label">Semilla</label>
                    <input id="seed" name="seed" type="number" min="1" max="999999" value="{{ old('seed') }}" class="field">
                    <p class="mt-1 text-sm text-ash-muted">
                        Con la misma semilla y los mismos tributos, la partida sale igual. Déjalo vacío para una partida nueva.
                        Con semilla no se puede apostar.
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

            // Con el Vasallaje, las reglas las elige el Capitolio: se desactivan las demás
            const quell = document.querySelector('[data-quell]');
            const syncRules = () => {
                document.querySelector('[data-rules]').classList.toggle('opacity-40', quell.checked);
                document.querySelectorAll('input[name="rules[]"]').forEach(box => box.disabled = quell.checked);
            };
            quell.addEventListener('change', syncRules);
            syncRules();

            // Con semilla ya se sabría quién gana, así que no se apuesta
            const seed = document.getElementById('seed');
            const bet = document.getElementById('bet');
            const syncBet = () => {
                bet.disabled = seed.value !== '';
                if (bet.disabled) bet.value = '';
            };
            seed.addEventListener('input', syncBet);
            syncBet();
        </script>
    @endif
</x-layout>
