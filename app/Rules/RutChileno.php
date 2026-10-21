<?php

namespace App\Rules;

use App\Support\Rut;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** HU09: valida el formato y el dígito verificador (módulo 11) de un RUT chileno. */
class RutChileno implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Rut::esValido($value)) {
            $fail('validation.rut')->translate();
        }
    }
}
