<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Language;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (['en' => 'English', 'de' => 'German', 'pl' => 'Polish', 'ru' => 'Russian'] as $code => $name) {
            Language::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        // Development accounts only. On a server the first admin comes from the /install page.
        if (app()->isLocal()) {
            foreach (['admin' => Role::Admin, 'learner' => Role::Learner] as $username => $role) {
                $user = User::firstOrNew(['username' => $username]);
                $user->email ??= "{$username}@example.com";
                $user->email_verified_at ??= now();
                $user->password = 'password';
                $user->role = $role;
                $user->save();
            }
        }
    }
}
