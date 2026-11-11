<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_defaults_for_a_new_learner(): void
    {
        config(['flashcards.session_size.default' => 20, 'flashcards.postpone_sessions.default' => 10]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.session_size', 20)
            ->assertJsonPath('user.postpone_sessions', 10);
    }

    public function test_saves_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/settings', ['session_size' => 30, 'postpone_sessions' => 5])
            ->assertOk()
            ->assertJsonPath('user.session_size', 30)
            ->assertJsonPath('message', 'Settings saved.');

        $this->assertSame(30, $user->fresh()->sessionSize());
        $this->assertSame(5, $user->fresh()->postponeSessions());
    }

    public function test_rejects_values_out_of_range(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/settings', ['session_size' => 4, 'postpone_sessions' => 101])
            ->assertJsonValidationErrors(['session_size', 'postpone_sessions']);

        $this->assertNull($user->fresh()->session_size);
    }

    public function test_changes_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/settings/password', [
                'current_password' => 'password',
                'password' => 'new-secret',
                'password_confirmation' => 'new-secret',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Password changed.');

        $this->assertTrue(Hash::check('new-secret', $user->fresh()->password));
    }

    public function test_password_change_needs_current_password_and_valid_new_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/settings/password', [
                'current_password' => 'wrong',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertJsonValidationErrors(['current_password', 'password']);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
