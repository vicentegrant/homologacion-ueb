<?php

namespace App\Http\Requests\Api\Admin;

use App\Rules\IdentificationNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminUserRequest extends FormRequest
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
            'nombres_completos' => ['required', 'string', 'max:255'],
            'tipo_identificacion' => ['required', 'string', 'in:cedula,pasaporte'],
            'cedula' => ['required', 'string', new IdentificationNumber($this->input('tipo_identificacion', $this->route('user')?->tipo_identificacion ?? 'cedula')), Rule::unique('users', 'cedula')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'numero_celular' => ['required', 'string', 'between:7,20'],
            'password' => ['prohibited', 'string'],
            'rol_id' => ['required', 'integer', Rule::exists('roles', 'id')],
        ];
    }
}
