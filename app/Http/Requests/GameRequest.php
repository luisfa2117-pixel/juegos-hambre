<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'characters.*' => ['integer', 'distinct', 'exists:characters,id'],
            'seed' => ['nullable', 'integer', 'min:1', 'max:999999'],
        ];
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
        ];
    }
}
