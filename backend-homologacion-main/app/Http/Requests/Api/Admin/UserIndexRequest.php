<?php

namespace App\Http\Requests\Api\Admin;

use App\SortDirection;
use App\UserSortField;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserIndexRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:255'],
            'rol' => ['nullable', 'string', 'max:255', Rule::exists('roles', 'nombre')],
            'cuenta_activa' => ['nullable', 'boolean'],
            'order_by' => ['nullable', Rule::enum(UserSortField::class)],
            'direction' => ['nullable', Rule::enum(SortDirection::class)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['rol.exists' => 'El rol seleccionado no existe.'];
    }
}
