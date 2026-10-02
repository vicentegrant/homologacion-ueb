<?php

namespace App\Http\Requests\Api\Coordinator;

use App\AnalysisConclusion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnalysisResultRequest extends FormRequest
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
            'conclusion_general' => ['required', Rule::enum(AnalysisConclusion::class)],
            'total_creditos_reconocidos' => ['required', 'integer', 'min:0'],
        ];
    }
}
