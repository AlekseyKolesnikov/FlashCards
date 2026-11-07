<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Validation\Rules\Password;

/**
 * Validation rules shared by registration, the install page and password change (SPECIFICATION.md §9.1).
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
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', 'confirmed', Password::min(8)];
    }
}
