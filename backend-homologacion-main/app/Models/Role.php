<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** @property string $nombre */
#[Fillable(['nombre'])]
class Role extends Model
{
    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_has_rol', 'rol_id', 'user_id')
            ->withTimestamps();
    }
}
