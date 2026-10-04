<?php

namespace App\Http\Requests\Api\Admin;

use App\Rules\IdentificationNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminUserRequest extends FormRequest
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
        $userId = $this->route('user')?->getKey();

        return [
            'nombres_completos' => ['sometimes', 'required', 'string', 'max:255'],
            'tipo_identificacion' => ['sometimes', 'string', 'in:cedula,pasaporte'],
            'cedula' => ['required_with:tipo_identificacion', 'string', new IdentificationNumber($this->input('tipo_identificacion', $this->route('user')?->tipo_identificacion ?? 'cedula')), Rule::unique('users', 'cedula')->ignore($userId)],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'numero_celular' => ['sometimes', 'required', 'string', 'between:7,20'],
            'password' => ['prohibited', 'string'],
            'rol_id' => ['sometimes', 'required', 'integer', Rule::exists('roles', 'id')],
        ];
    }
}
