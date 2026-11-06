<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el formulario de personajes, tanto al crear como al editar.
 * Si algo no es válido, Laravel vuelve al formulario con los errores
 * y lo que habías escrito, sin llegar a entrar en el controlador.
 */
class CharacterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:40',
                // Al editar, el propio personaje no cuenta como "repetido"
                Rule::unique('characters', 'name')->ignore($this->route('character')),
            ],
            'gender' => ['required', Rule::enum(Gender::class)],
            'district_id' => ['required', 'integer', 'exists:districts,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Ponle un nombre.',
            'name.max' => 'El nombre puede tener como mucho 40 caracteres.',
            'name.unique' => 'Ya hay un personaje con ese nombre.',
            'gender.required' => 'Elige un género.',
            'gender.enum' => 'Elige un género de la lista.',
            'district_id.required' => 'Elige un distrito.',
            'district_id.integer' => 'Elige un distrito de la lista.',
            'district_id.exists' => 'Elige un distrito de la lista.',
        ];
    }
}
