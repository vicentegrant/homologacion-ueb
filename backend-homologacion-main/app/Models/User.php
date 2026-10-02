<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['nombres_completos', 'cedula', 'email', 'password', 'numero_celular', 'cuenta_activa', 'creador_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cuenta_activa' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(self::class, 'creador_id');
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_has_rol', 'user_id', 'rol_id')
            ->withTimestamps();
    }

    public function hasRole(Role|string|int|array $roles): bool
    {
        if (is_array($roles)) {
            return collect($roles)->contains(fn (Role|string|int $role): bool => $this->hasRole($role));
        }

        $roleId = $roles instanceof Role || is_int($roles)
            ? (int) ($roles instanceof Role ? $roles->getKey() : $roles)
            : null;
        $roleName = is_string($roles) ? Str::lower($roles) : null;

        return $this->roles()->where(function (Builder $query) use ($roleId, $roleName): void {
            $query->when($roleId !== null, fn (Builder $builder) => $builder->whereKey($roleId))
                ->when($roleName !== null, fn (Builder $builder) => $builder->where('nombre', $roleName));
        })->exists();
    }

    public function assignRole(Role|string|int $role): self
    {
        $resolvedRole = $this->resolveRole($role);
        $this->roles()->syncWithoutDetaching([$resolvedRole->getKey()]);
        $this->unsetRelation('roles');

        return $this;
    }

    /** @param iterable<Role|string|int> $roles */
    public function syncRoles(iterable $roles): self
    {
        $roleIds = collect($roles)->map(fn (Role|string|int $role): int => (int) $this->resolveRole($role)->getKey());
        $this->roles()->sync($roleIds);
        $this->unsetRelation('roles');

        return $this;
    }

    /** @return Collection<int, string> */
    public function getRoleNames(): Collection
    {
        return $this->roles()->orderBy('nombre')->pluck('nombre');
    }

    /** @param Role|string|int|array<Role|string|int> $roles */
    public function scopeRole(Builder $query, Role|string|int|array $roles): Builder
    {
        $roles = is_array($roles) ? $roles : [$roles];
        $roleIds = collect($roles)->filter(fn (mixed $role): bool => $role instanceof Role || is_int($role))
            ->map(fn (Role|int $role): int => (int) ($role instanceof Role ? $role->getKey() : $role));
        $roleNames = collect($roles)->filter(fn (mixed $role): bool => is_string($role))
            ->map(fn (string $role): string => Str::lower($role));

        return $query->whereHas('roles', function (Builder $roleQuery) use ($roleIds, $roleNames): void {
            $roleQuery->where(function (Builder $matchingRole) use ($roleIds, $roleNames): void {
                $matchingRole->when($roleIds->isNotEmpty(), fn (Builder $builder) => $builder->whereIn('roles.id', $roleIds))
                    ->when($roleNames->isNotEmpty(), fn (Builder $builder) => $builder->orWhereIn('roles.nombre', $roleNames));
            });
        });
    }

    private function resolveRole(Role|string|int $role): Role
    {
        if ($role instanceof Role) {
            return $role;
        }

        return is_int($role)
            ? Role::query()->findOrFail($role)
            : Role::query()->where('nombre', Str::lower($role))->firstOrFail();
    }

    /** @return HasMany<User, $this> */
    public function usuariosCreados(): HasMany
    {
        return $this->hasMany(self::class, 'creador_id');
    }

    /** @return HasMany<AntecedenteAcademico, $this> */
    public function antecedentesAcademicos(): HasMany
    {
        return $this->hasMany(AntecedenteAcademico::class, 'estudiante_id');
    }

    /** @return HasMany<CoordinadorCarrera, $this> */
    public function coordinaciones(): HasMany
    {
        return $this->hasMany(CoordinadorCarrera::class, 'coordinador_id');
    }

    /** @return BelongsToMany<Carrera, $this> */
    public function carrerasCoordinadas(): BelongsToMany
    {
        return $this->belongsToMany(Carrera::class, 'coordinador_carreras', 'coordinador_id', 'carrera_id')
            ->withTimestamps();
    }

    /** @return HasMany<EstudianteCarrera, $this> */
    public function carrerasComoEstudiante(): HasMany
    {
        return $this->hasMany(EstudianteCarrera::class, 'estudiante_id');
    }

    /** @return HasMany<Solicitud, $this> */
    public function solicitudesComoEstudiante(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'estudiante_id');
    }

    /** @return HasMany<Solicitud, $this> */
    public function solicitudesComoCoordinador(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'coordinador_id');
    }

    /** @return HasMany<MallaCurricular, $this> */
    public function mallasCreadas(): HasMany
    {
        return $this->hasMany(MallaCurricular::class, 'creador_id');
    }

    /** @return HasMany<MallaCurricular, $this> */
    public function mallasDeOrigen(): HasMany
    {
        return $this->hasMany(MallaCurricular::class, 'estudiante_id');
    }

    /** @return HasMany<VerificacionDocumento, $this> */
    public function verificacionesRealizadas(): HasMany
    {
        return $this->hasMany(VerificacionDocumento::class, 'coordinador_id');
    }

    /** @return HasMany<ResultadoSolicitud, $this> */
    public function resultadosEmitidos(): HasMany
    {
        return $this->hasMany(ResultadoSolicitud::class, 'coordinador_id');
    }

    /** @return HasMany<HistorialEstadoSolicitud, $this> */
    public function cambiosEstadoRealizados(): HasMany
    {
        return $this->hasMany(HistorialEstadoSolicitud::class, 'usuario_responsable_id');
    }

    /** @return HasMany<ResolucionSolicitud, $this> */
    public function resolucionesEmitidas(): HasMany
    {
        return $this->hasMany(ResolucionSolicitud::class, 'coordinador_id');
    }
}
