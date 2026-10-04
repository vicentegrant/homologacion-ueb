<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre', 'activa'])]
class Facultad extends Model
{
    use HasFactory;

    protected $table = 'facultades';

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }
}
