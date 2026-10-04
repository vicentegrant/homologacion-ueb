<?php

namespace App\Http\Requests\Api\Coordinator;

use App\Rules\IdentificationNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('coordinador') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('student');

        return [
            'nombres_completos' => ['required', 'string', 'max:255'],
            'tipo_identificacion' => ['required', 'string', 'in:cedula,pasaporte'],
            'cedula' => ['required', 'string', new IdentificationNumber($this->string('tipo_identificacion')->toString()), Rule::unique('users', 'cedula')->ignore($id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'numero_celular' => ['required', 'string', 'between:7,20'],
            'carrera_id' => ['required', 'integer', 'exists:carreras,id'],
            'modalidad_id' => ['required', 'integer', 'exists:modalidades,id'],
            'rol_id' => ['prohibited', 'integer'], 'roles' => ['prohibited', 'array'], 'password' => ['prohibited', 'string'], 'cuenta_activa' => ['prohibited', 'boolean'],
        ];
    }
}
