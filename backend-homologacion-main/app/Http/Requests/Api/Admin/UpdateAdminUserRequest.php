<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
            'cedula' => ['sometimes', 'required', 'string', 'between:10,20', Rule::unique('users', 'cedula')->ignore($userId)],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'numero_celular' => ['sometimes', 'required', 'string', 'between:7,20'],
            'password' => ['sometimes', 'required', 'string', Password::defaults()],
            'rol_id' => ['sometimes', 'required', 'integer', Rule::exists('roles', 'id')],
        ];
    }
}
