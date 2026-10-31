<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_defaults_for_a_new_learner(): void
    {
        config(['flashcards.session_size.default' => 20, 'flashcards.postpone_sessions.default' => 10]);

        $this->actingAs(User::factory()->create())
            ->get('/settings')
            ->assertOk()
            ->assertSee('value="20"', false)
            ->assertSee('value="10"', false);
    }

    public function test_saves_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/settings', ['session_size' => 30, 'postpone_sessions' => 5])
            ->assertRedirect('/settings')
            ->assertSessionHas('status');

        $this->assertSame(30, $user->fresh()->sessionSize());
        $this->assertSame(5, $user->fresh()->postponeSessions());
    }

    public function test_rejects_values_out_of_range(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/settings', ['session_size' => 4, 'postpone_sessions' => 101])
            ->assertSessionHasErrors(['session_size', 'postpone_sessions']);

        $this->assertNull($user->fresh()->session_size);
    }
}
