<?php

namespace App\Http\Requests;

use App\Enums\EventCategory;
use App\Enums\EventPhase;
use App\Enums\ItemType;
use App\Rules\EventText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GameEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Muchas reglas dependen del tipo de evento elegido (null si no es válido)
        $category = $this->enum('category', EventCategory::class);
        $stat = ['required', 'integer', 'between:-20,20'];

        return [
            'category' => ['required', Rule::enum(EventCategory::class)],

            'text' => array_filter([
                'required', 'string', 'max:255',
                $category ? new EventText($category->participants()) : null,
                Rule::unique('game_events', 'text')
                    ->where('category', $category?->value)
                    ->ignore($this->route('event')),
            ]),

            'phase' => ['nullable', Rule::enum(EventPhase::class)],

            'actor_hp' => $stat,
            'actor_energy' => $stat,
            'target_hp' => $stat,
            'target_energy' => $stat,

            // En un ataque con arma es obligatorio, y tiene que ser un arma
            'requires_item_id' => $category === EventCategory::Weapon
                ? ['required', Rule::exists('items', 'id')->where('type', ItemType::Weapon->value)]
                : ['nullable', Rule::exists('items', 'id')],

            // Solo los descubrimientos dan objetos, y siempre dan uno
            'gives_item_id' => [
                Rule::requiredIf($category === EventCategory::Discovery),
                Rule::prohibitedIf($category !== null && $category !== EventCategory::Discovery),
                'nullable',
                Rule::exists('items', 'id'),
            ],
        ];
    }

    public function messages(): array
    {
        $between = 'Tiene que ser un número entre -20 y 20.';

        return [
            'category.required' => 'Elige el tipo de evento.',
            'category.enum' => 'Elige un tipo de evento de la lista.',
            'text.required' => 'Escribe lo que pasa.',
            'text.max' => 'El texto puede tener como mucho 255 caracteres.',
            'text.unique' => 'Ya hay un evento de este tipo con ese mismo texto.',
            'phase.enum' => 'Elige cuándo puede pasar de la lista.',
            '*.required' => 'Este campo es obligatorio.',
            'actor_hp.between' => $between,
            'actor_energy.between' => $between,
            'target_hp.between' => $between,
            'target_energy.between' => $between,
            'requires_item_id.required' => 'Elige el arma que hace falta para este ataque.',
            'requires_item_id.exists' => 'Para un ataque con arma, el objeto tiene que ser un arma.',
            'gives_item_id.required' => 'Elige qué objeto encuentra.',
            'gives_item_id.prohibited' => 'Solo los descubrimientos dan objetos.',
        ];
    }

    /** Los datos listos para guardar, con lo que se deduce del tipo de evento */
    public function eventData(): array
    {
        $data = $this->validated();
        $category = $this->enum('category', EventCategory::class);
        $data['participants'] = $category->participants();

        $data['requires_item_id'] ??= null;
        $data['phase'] ??= null;
        $data['gives_item_id'] = $category === EventCategory::Discovery ? ($data['gives_item_id'] ?? null) : null;

        // En los eventos de una persona no hay "los demás"
        if ($data['participants'] === 1) {
            $data['target_hp'] = 0;
            $data['target_energy'] = 0;
        }

        return $data;
    }
}
