<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IdentificationNumber implements ValidationRule
{
    public function __construct(private string $type) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->type === 'cedula' && (! is_string($value) || ! preg_match('/^[0-9]{10}$/D', $value))) {
            $fail('La cédula debe contener exactamente 10 dígitos.');
        } elseif ($this->type === 'pasaporte' && (! is_string($value) || ! preg_match('/^[A-Z0-9]{5,20}$/D', $value))) {
            $fail('El pasaporte debe contener entre 5 y 20 letras mayúsculas o números, sin espacios.');
        }
    }
}
