<?php

namespace App\Http\Requests\Api\Coordinator;

use App\AcademicLevel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubjectRequest extends FormRequest
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
            'codigo_asignatura' => ['sometimes', 'required', 'string', 'max:50'],
            'nombre_asignatura' => ['sometimes', 'required', 'string', 'max:150'],
            'numero_creditos' => ['sometimes', 'required', 'integer', 'min:0'],
            'nivel_ciclo' => ['sometimes', 'required', Rule::enum(AcademicLevel::class)],
            'hr_carga_horaria' => ['sometimes', 'required', 'integer', 'min:0'],
        ];
    }
}
