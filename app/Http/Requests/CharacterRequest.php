<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use App\Enums\Personality;
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
                // Único entre TUS personajes; al editar, el propio personaje no cuenta como repetido
                Rule::unique('characters', 'name')
                    ->where('user_id', $this->user()->id)
                    ->ignore($this->route('character')),
            ],
            'gender' => ['required', Rule::enum(Gender::class)],
            'personality' => ['nullable', Rule::enum(Personality::class)],
            'district_id' => ['required', 'integer', 'exists:districts,id'],
            // max se mide en kilobytes: 2048 = 2 MB
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Ponle un nombre.',
            'name.max' => 'El nombre puede tener como mucho 40 caracteres.',
            'name.unique' => 'Ya tienes un personaje con ese nombre.',
            'gender.required' => 'Elige un género.',
            'gender.enum' => 'Elige un género de la lista.',
            'personality.enum' => 'Elige una personalidad de la lista.',
            'district_id.required' => 'Elige un distrito.',
            'district_id.integer' => 'Elige un distrito de la lista.',
            'district_id.exists' => 'Elige un distrito de la lista.',
            'image.image' => 'El fichero tiene que ser una imagen.',
            'image.mimes' => 'La foto tiene que ser JPG, PNG o WebP.',
            'image.max' => 'La foto puede pesar como mucho 2 MB.',
            'image.uploaded' => 'No se ha podido subir la foto. Prueba con una más pequeña.',
        ];
    }
}
