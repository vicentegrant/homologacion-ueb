<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre', 'activa'])]
class Modalidad extends Model
{
    use HasFactory;

    protected $table = 'modalidades';

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }
}
