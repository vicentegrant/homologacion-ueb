<?php

namespace App\Http\Requests\Api\Coordinator;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSyllabusTopicRequest extends FormRequest
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
            'tema' => ['sometimes', 'required', 'string', 'max:255'],
            'unidad_analitica' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }
}
