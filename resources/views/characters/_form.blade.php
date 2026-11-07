{{-- Campos compartidos por "crear" y "editar". Recibe $character y $districts. --}}
@php use App\Enums\Gender; use App\Enums\Personality; @endphp

<div class="space-y-6">
    <div>
        <label for="name" class="field-label">Nombre</label>
        <input id="name" name="name" type="text" maxlength="40" required autofocus
               value="{{ old('name', $character->name) }}" class="field">
        @error('name') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="image" class="field-label">Foto</label>
        <div class="flex items-center gap-4">
            <x-tribute-avatar :name="$character->name ?? '?'" :district="$character->district?->slug"
                              :image="$character->image_url" size="lg" />
            <div class="space-y-2">
                <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp"
                       class="block text-sm text-ash-muted file:mr-3 file:rounded-sm file:border-0 file:bg-asphalt-raised file:px-3 file:py-1.5 file:text-ash hover:file:bg-asphalt-line">
                <p class="text-sm text-ash-muted">Opcional. JPG, PNG o WebP de hasta 2 MB. Se verá recortada en un círculo.</p>

                @if ($character->image_path)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="remove_image" value="1" class="accent-blood" @checked(old('remove_image'))>
                        Quitar la foto actual
                    </label>
                @endif
            </div>
        </div>
        @error('image') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <fieldset>
        <legend class="field-label">Género</legend>
        <p class="mb-2 text-sm text-ash-muted">Sirve para que los textos concuerden: «fue herido» o «fue herida».</p>
        <div class="flex gap-6">
            @foreach (Gender::cases() as $gender)
                <label class="flex items-center gap-2">
                    <input type="radio" name="gender" value="{{ $gender->value }}" class="accent-hazard"
                           @checked(old('gender', $character->gender?->value) === $gender->value)>
                    {{ $gender->label() }}
                </label>
            @endforeach
        </div>
        @error('gender') <p class="field-error">{{ $message }}</p> @enderror
    </fieldset>

    <div>
        <label for="personality" class="field-label">Personalidad</label>
        <select id="personality" name="personality" class="field">
            <option value="">Equilibrado: sin rasgos especiales</option>
            @foreach (Personality::cases() as $personality)
                <option value="{{ $personality->value }}" @selected(old('personality', $character->personality?->value) === $personality->value)>
                    {{ $personality->label() }}: {{ $personality->description() }}
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-sm text-ash-muted">Cambia cómo se comporta en la arena: con quién se alía, si traiciona, si busca pelea...</p>
        @error('personality') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="district_id" class="field-label">Distrito</label>
        <select id="district_id" name="district_id" required class="field">
            <option value="" disabled @selected(! old('district_id', $character->district_id))>Elige un distrito</option>
            @foreach ($districts as $district)
                <option value="{{ $district->id }}"
                        @selected((int) old('district_id', $character->district_id) === $district->id)>
                    {{ $district->name }} ({{ sprintf('%+d', $district->strength_bonus) }} fuerza, {{ sprintf('%+d', $district->energy_bonus) }} energía)
                </option>
            @endforeach
        </select>
        @error('district_id') <p class="field-error">{{ $message }}</p> @enderror
    </div>
</div>
