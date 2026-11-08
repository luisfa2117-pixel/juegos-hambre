<?php

namespace App\Http\Requests;

use App\Enums\GameRule;
use App\Simulation\GameRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'characters' => ['required', 'array', 'min:2', 'max:48'],
            // Que exista Y que sea tuyo: no puedes meter en tu partida personajes de otro usuario
            'characters.*' => ['integer', 'distinct', Rule::exists('characters', 'id')->where('user_id', $this->user()->id)],
            'seed' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'rules' => ['nullable', 'array'],
            'rules.*' => ['distinct', Rule::enum(GameRule::class)],
            'quell' => ['nullable', 'boolean'],
        ];
    }

    /** Las reglas que no tienen sentido juntas (sin alianzas no hay a quién traicionar) */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->boolean('quell') || $validator->errors()->has('rules.*')) {
                    return;
                }

                $rules = $this->gameRules()->all();
                foreach ($rules as $rule) {
                    foreach ($rules as $other) {
                        if ($rule->conflictsWith($other)) {
                            $validator->errors()->add('rules', "«{$rule->label()}» y «{$other->label()}» no se pueden jugar juntas.");

                            return;
                        }
                    }
                }
            },
        ];
    }

    /** Las reglas elegidas, para el motor (con el Vasallaje las elige la semilla, así que aquí no cuentan) */
    public function gameRules(): GameRules
    {
        return GameRules::fromValues($this->boolean('quell') ? [] : (array) $this->input('rules', []));
    }

    public function messages(): array
    {
        return [
            'characters.required' => 'Elige al menos dos tributos.',
            'characters.min' => 'Elige al menos dos tributos.',
            'characters.max' => 'Como mucho pueden jugar 48 tributos.',
            'characters.*.exists' => 'Alguno de los personajes elegidos ya no existe.',
            'characters.*.integer' => 'Alguno de los personajes elegidos no es válido.',
            'characters.*.distinct' => 'Has elegido a un personaje dos veces.',
            'seed.integer' => 'La semilla tiene que ser un número.',
            'seed.min' => 'La semilla tiene que estar entre 1 y 999999.',
            'seed.max' => 'La semilla tiene que estar entre 1 y 999999.',
            'rules.*.enum' => 'Alguna de las reglas elegidas no existe.',
            'rules.*.distinct' => 'Has elegido una regla dos veces.',
        ];
    }
}
