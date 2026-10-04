<?php

namespace App\Http\Requests\Api\Coordinator;

use App\Models\AntecedenteAcademico;
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
        $internal = $this->input('procedencia') === 'interna';
        $external = $this->input('procedencia') === 'externa';

        return [
            'nombres_completos' => ['required', 'string', 'max:255'],
            'tipo_identificacion' => ['required', 'string', 'in:cedula,pasaporte'],
            'cedula' => ['required', 'string', new IdentificationNumber($this->string('tipo_identificacion')->toString()), Rule::unique('users', 'cedula')->ignore($id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'numero_celular' => ['required', 'string', 'regex:/\A[0-9]{10}\z/'],
            'carrera_id' => ['required', 'integer', 'exists:carreras,id'],
            'procedencia' => ['sometimes', 'required', Rule::in(['interna', 'externa'])],
            'carrera_origen_id' => [Rule::excludeIf($external), 'required', 'integer', 'exists:carreras,id', 'different:carrera_id'],
            'carrera_origen' => [Rule::excludeIf(! $external), 'required', 'string', 'max:255'],
            'universidad_origen' => [Rule::excludeIf($internal), 'required', 'string', 'max:255'],
            'tipo_institucion' => [Rule::excludeIf($internal), 'required', 'string', 'in:publica,privada,instituto'],
            'periodo_cursado' => ['required', 'string', 'regex:'.AntecedenteAcademico::PERIOD_PATTERN],
            'modalidad_id' => ['required', 'integer', 'exists:modalidades,id'],
            'rol_id' => ['prohibited', 'integer'], 'roles' => ['prohibited', 'array'], 'password' => ['prohibited', 'string'], 'cuenta_activa' => ['prohibited', 'boolean'],
        ];
    }
}
