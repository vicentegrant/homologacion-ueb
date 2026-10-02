<?php

namespace App\Http\Requests\Api\Coordinator;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreComparisonRequest extends FormRequest
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
            'asignatura_origen_id' => ['required', 'integer', 'exists:asignaturas_creditos,id'],
            'asignatura_destino_id' => ['required', 'integer', 'different:asignatura_origen_id', 'exists:asignaturas_creditos,id'],
            'porcentaje_coincidencia' => ['required', 'numeric', 'between:0,100'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
