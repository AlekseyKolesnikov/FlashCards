<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Validation\Rules\Password;

/**
 * Validation rules shared by registration and the install page (SPECIFICATION.md §9.1).
 */
trait AccountValidationRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function accountRules(): array
    {
        return [
            // Uniqueness is case-insensitive through the column's collation.
            'username' => ['required', 'string', 'min:3', 'max:32', 'alpha_dash', 'unique:users,username'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }
}
