<?php

namespace App;

enum UserSortField: string
{
    case Id = 'id';
    case NombresCompletos = 'nombres_completos';
    case Cedula = 'cedula';
    case Email = 'email';
    case CreatedAt = 'created_at';
}
