<?php

namespace App\Http\Requests\Api\Coordinator;

use App\CurriculumType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CurriculumIndexRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:150'],
            'tipo' => ['nullable', Rule::enum(CurriculumType::class)],
            'carrera' => ['nullable', 'integer', 'exists:carreras,id'],
            'estudiante' => ['nullable', 'integer', 'exists:users,id'],
            'activa' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
