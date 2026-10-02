<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitudFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'estado' => ['nullable', 'string', 'max:100'],
            'carrera' => ['nullable', 'integer', Rule::exists('carreras', 'id')],
            'tipo_tramite' => ['nullable', 'string', 'max:100'],
            'tipo_proceso' => ['nullable', 'string', 'max:100'],
            'estudiante' => ['nullable', 'string', 'max:255'],
            'coordinador' => ['nullable', 'string', 'max:255'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
