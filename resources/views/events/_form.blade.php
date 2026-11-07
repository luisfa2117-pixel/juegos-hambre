{{-- Campos compartidos por "crear" y "editar". Recibe $event e $items. --}}
@use('App\Enums\EventCategory')
@use('App\Enums\EventPhase')
@use('App\Enums\Personality')
@use('App\Support\EventPreview')

@php
    $currentCategory = old('category', $event->category?->value);
    $preview = EventPreview::both(old('text', $event->text ?? ''));
@endphp

<div class="space-y-8">
    <div>
        <label for="category" class="field-label">Tipo de evento</label>
        <select id="category" name="category" class="field" data-category>
            @foreach (EventCategory::editable() as $case)
                <option value="{{ $case->value }}" data-participants="{{ $case->participants() }}" data-actors="{{ $case->actors() }}"
                        @selected($currentCategory === $case->value)>
                    {{ $case->label() }} ({{ trans_choice(':count participante|:count participantes', $case->participants()) }})
                </option>
            @endforeach
        </select>
        @error('category') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="phase" class="field-label">Cuándo puede pasar</label>
        <select id="phase" name="phase" class="field">
            <option value="">De día o de noche</option>
            @foreach (EventPhase::cases() as $phase)
                <option value="{{ $phase->value }}" @selected(old('phase', $event->phase?->value) === $phase->value)>{{ $phase->label() }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-sm text-ash-muted">
            En el baño de sangre (la primera ronda) salen los eventos de la Cornucopia; si un tipo no tiene ninguno, se usan los de día o de noche.
        </p>
        @error('phase') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="personality" class="field-label">Solo para la personalidad</label>
        <select id="personality" name="personality" class="field">
            <option value="">Cualquier tributo</option>
            @foreach (Personality::cases() as $personality)
                <option value="{{ $personality->value }}" @selected(old('personality', $event->personality?->value) === $personality->value)>
                    {{ $personality->label() }}
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-sm text-ash-muted">
            Si eliges una personalidad, solo puede protagonizarlo (A) un tributo que la tenga, y le saldrá más a menudo.
        </p>
        @error('personality') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="text" class="field-label">Qué pasa</label>
        <textarea id="text" name="text" rows="2" maxlength="255" required class="field" data-text>{{ old('text', $event->text) }}</textarea>
        @error('text') <p class="field-error">{{ $message }}</p> @enderror

        <details class="mt-2 text-sm text-ash-muted">
            <summary class="cursor-pointer hover:text-ash">Cómo se escriben los nombres y el género</summary>
            <ul class="mt-2 space-y-1">
                <li><strong class="text-ash">{A}</strong> es el nombre del protagonista; <strong class="text-ash">{B}</strong>, {C} y {D}, los demás.</li>
                <li><strong class="text-ash">herid{A:o/a}</strong> sale «herido» o «herida» según A.</li>
                <li><strong class="text-ash">junt{AB:os/as}</strong> sale «juntas» solo si A y B son mujeres; si no, «juntos».</li>
            </ul>
        </details>

        <div class="mt-3 rounded-sm border border-asphalt-line p-3 text-sm" aria-live="polite">
            <p><span class="text-ash-muted">Con chicos:</span> <span data-preview-men>{{ $preview['men'] }}</span></p>
            <p class="mt-1"><span class="text-ash-muted">Con chicas:</span> <span data-preview-women>{{ $preview['women'] }}</span></p>
        </div>
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <fieldset>
            <legend class="field-label" data-actors-legend>Efectos en A</legend>
            <div class="grid grid-cols-2 gap-3">
                @foreach (['actor_hp' => 'Vida', 'actor_energy' => 'Energía'] as $field => $label)
                    <div>
                        <label for="{{ $field }}" class="text-sm text-ash-muted">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="number" min="-20" max="20" required
                               value="{{ old($field, $event->{$field}) }}" class="field">
                        @error($field) <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </fieldset>

        <fieldset data-targets>
            <legend class="field-label" data-targets-legend>Efectos en los demás</legend>
            <div class="grid grid-cols-2 gap-3">
                @foreach (['target_hp' => 'Vida', 'target_energy' => 'Energía'] as $field => $label)
                    <div>
                        <label for="{{ $field }}" class="text-sm text-ash-muted">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="number" min="-20" max="20" required
                               value="{{ old($field, $event->{$field}) }}" class="field">
                        @error($field) <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </fieldset>
    </div>
    <p class="-mt-5 text-sm text-ash-muted">Negativo quita, positivo da. La vida máxima es 20.</p>

    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <label for="requires_item_id" class="field-label">Objeto que necesita A</label>
            <select id="requires_item_id" name="requires_item_id" class="field">
                <option value="">Ninguno</option>
                @foreach ($items as $item)
                    <option value="{{ $item->id }}" @selected((int) old('requires_item_id', $event->requires_item_id) === $item->id)>{{ $item->name }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-sm text-ash-muted">Si lo eliges, el evento solo sale si A lo lleva, y lo gasta.</p>
            @error('requires_item_id') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div data-gives>
            <label for="gives_item_id" class="field-label">Objeto que encuentra</label>
            <select id="gives_item_id" name="gives_item_id" class="field">
                <option value="">Ninguno</option>
                @foreach ($items as $item)
                    <option value="{{ $item->id }}" @selected((int) old('gives_item_id', $event->gives_item_id) === $item->id)>{{ $item->name }}</option>
                @endforeach
            </select>
            @error('gives_item_id') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </div>
</div>

<script>
    // 1. Mostrar u ocultar campos según el tipo de evento
    const category = document.querySelector('[data-category]');
    const targets = document.querySelector('[data-targets]');
    const gives = document.querySelector('[data-gives]');

    function updateFields() {
        const participants = Number(category.selectedOptions[0].dataset.participants);
        const actors = Number(category.selectedOptions[0].dataset.actors);
        const labels = ['A', 'B', 'C', 'D'].slice(0, participants);
        const join = list => list.length > 1 ? list.slice(0, -1).join(', ') + ' y ' + list.at(-1) : list[0];

        document.querySelector('[data-actors-legend]').textContent = 'Efectos en ' + join(labels.slice(0, actors));
        document.querySelector('[data-targets-legend]').textContent = 'Efectos en ' + (join(labels.slice(actors)) ?? 'los demás');
        targets.hidden = participants === 1;
        gives.hidden = category.value !== 'discovery';
        if (gives.hidden) gives.querySelector('select').value = '';
    }

    category.addEventListener('change', updateFields);
    updateFields();

    // 2. Vista previa: al dejar de escribir, pide al servidor cómo queda el texto
    const text = document.querySelector('[data-text]');
    let timer;

    text.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(updatePreview, 300);   // espera 300 ms para no hacer una petición por letra
    });

    async function updatePreview() {
        const response = await fetch(@js(route('events.preview')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ text: text.value }),
        });

        if (! response.ok) return;

        const preview = await response.json();
        // textContent (y no innerHTML): el texto se muestra tal cual, sin interpretar HTML
        document.querySelector('[data-preview-men]').textContent = preview.men;
        document.querySelector('[data-preview-women]').textContent = preview.women;
    }
</script>
