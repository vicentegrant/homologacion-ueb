<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('administrador') ?? false;
    }

    public function rules(): array
    {
        $catalog = $this->route('catalog');
        $table = match ($catalog) {
            'modalidades' => 'modalidades','carreras' => 'carreras','requisitos' => 'documentos_requeridos_proceso',default => 'facultades'
        };
        $career = Rule::excludeIf($catalog !== null && $catalog !== 'carreras');
        $requirement = Rule::excludeIf($catalog !== null && $catalog !== 'requisitos');
        $rules = [
            'nombre' => ['required', 'string', 'max:150'], 'activa' => ['required', 'boolean'],
            'facultad_id' => [$career, Rule::requiredIf($catalog === 'carreras'), 'integer', Rule::exists('facultades', 'id')->where('activa', true)],
            'modalidad_ids' => [$career, Rule::requiredIf($catalog === 'carreras'), 'array', 'min:1'],
            'modalidad_ids.*' => [$career, 'integer', 'distinct', Rule::exists('modalidades', 'id')->where('activa', true)],
            'tramite_proceso_id' => [$requirement, Rule::requiredIf($catalog === 'requisitos'), 'integer', 'exists:tramite_proceso,id'],
            'carrera_id' => [$requirement, 'nullable', 'integer', 'exists:carreras,id'],
            'descripcion' => [$requirement, 'nullable', 'string', 'max:2000'],
            'obligatorio' => [$requirement, Rule::requiredIf($catalog === 'requisitos'), 'boolean'],
        ];
        if ($catalog !== 'requisitos') {
            $rules['nombre'][] = Rule::unique($table, 'nombre')->ignore($this->route('entry'));
        }

        return $rules;
    }
}
