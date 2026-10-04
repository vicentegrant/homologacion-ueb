<?php

namespace App\Http\Requests\Api\Coordinator;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRequirementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('coordinador') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'carrera_id' => ['required', 'integer', Rule::exists('carreras', 'id')],
            'tramite_proceso_id' => ['required', 'integer', Rule::exists('tramite_proceso', 'id')],
            'activa' => ['required', 'boolean'],
            'obligatorio' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'Este campo es obligatorio.',
            'exists' => 'La opción seleccionada no existe.',
            'boolean' => 'Seleccione una opción válida.',
            'integer' => 'Seleccione una opción válida.',
            'string' => 'Ingrese un texto válido.',
            'nombre.max' => 'El nombre admite hasta 150 caracteres.',
            'descripcion.max' => 'Las indicaciones admiten hasta 2000 caracteres.',
        ];
    }
}
