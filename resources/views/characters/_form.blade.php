{{-- Campos compartidos por "crear" y "editar". Recibe $character y $districts. --}}
@php use App\Enums\Gender; @endphp

<div class="space-y-6">
    <div>
        <label for="name" class="field-label">Nombre</label>
        <input id="name" name="name" type="text" maxlength="40" required autofocus
               value="{{ old('name', $character->name) }}" class="field">
        @error('name') <p class="field-error">{{ $message }}</p> @enderror
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
