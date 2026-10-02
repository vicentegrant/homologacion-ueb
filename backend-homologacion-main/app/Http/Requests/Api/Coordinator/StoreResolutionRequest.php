<?php

namespace App\Http\Requests\Api\Coordinator;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreResolutionRequest extends FormRequest
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
            'numero_resolucion' => ['required', 'string', 'max:100', Rule::unique('resoluciones_solicitud', 'numero_resolucion')],
            'fecha_aprobacion' => ['required', 'date'],
            'archivo' => ['required', File::types(['pdf'])->max(config('uploads.private_pdf_max_kb'))],
        ];
    }
}
