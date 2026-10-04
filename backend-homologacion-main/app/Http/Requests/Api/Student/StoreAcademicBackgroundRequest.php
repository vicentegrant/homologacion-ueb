<?php

namespace App\Http\Requests\Api\Student;

use App\Models\AntecedenteAcademico;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAcademicBackgroundRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('estudiante') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'universidad_origen' => ['required', 'string', 'max:255'],
            'carrera_origen' => ['required', 'string', 'max:255'],
            'tipo_institucion' => ['required', 'string', 'in:publica,privada,instituto'],
            'periodo_cursado' => ['required', 'string', 'regex:'.AntecedenteAcademico::PERIOD_PATTERN],
        ];
    }
}
