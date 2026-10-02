<?php

namespace App\Http\Requests\Api\Coordinator;

use App\CurriculumType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCurriculumRequest extends FormRequest
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
            'tipo' => ['required', Rule::enum(CurriculumType::class)],
            'carrera_id' => ['nullable', 'integer', 'exists:carreras,id', 'required_if:tipo,institucional'],
            'estudiante_id' => ['nullable', 'integer', 'exists:users,id', 'required_if:tipo,origen'],
            'activa' => ['sometimes', 'boolean'],
        ];
    }
}
