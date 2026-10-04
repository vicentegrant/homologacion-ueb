<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\Carrera;
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
        // Al editar se permite conservar asociaciones inactivas, pero no agregar nuevas.
        $existingCareer = $catalog === 'carreras' && $this->route('entry')
            ? Carrera::query()->find($this->route('entry')) : null;
        $facultyExists = Rule::exists('facultades', 'id')->where(fn ($query) => $query
            ->where('activa', true)->orWhere('id', $existingCareer?->facultad_id ?? 0));
        $modalityExists = Rule::exists('modalidades', 'id')->where(fn ($query) => $query
            ->where('activa', true)->orWhereIn('id', $existingCareer?->modalidades()->pluck('modalidades.id')->all() ?? []));
        $rules = [
            'nombre' => ['required', 'string', 'max:150'], 'activa' => ['required', 'boolean'],
            'facultad_id' => [$career, Rule::requiredIf($catalog === 'carreras'), 'integer', $facultyExists],
            'modalidad_ids' => [$career, Rule::requiredIf($catalog === 'carreras'), 'array', 'min:1'],
            'modalidad_ids.*' => [$career, 'integer', 'distinct', $modalityExists],
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
