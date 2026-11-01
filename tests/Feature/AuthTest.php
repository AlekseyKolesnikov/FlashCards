<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/learn')->assertRedirect('/login');
    }

    public function test_each_role_lands_on_its_start_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/')->assertRedirect('/admin');
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/learn');
    }

    public function test_login_with_correct_password(): void
    {
        $user = User::factory()->create(['username' => 'anna']);

        // Usernames are case-insensitive.
        $this->post('/login', ['username' => 'Anna', 'password' => 'password'])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_wrong_password(): void
    {
        User::factory()->create(['username' => 'anna']);

        $this->from('/login')
            ->post('/login', ['username' => 'anna', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_logout(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_registration_creates_a_learner(): void
    {
        $this->post('/register', [
            'username' => 'new_learner',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect('/');

        $user = User::where('username', 'new_learner')->firstOrFail();
        $this->assertSame(Role::Learner, $user->role);
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_validates_input(): void
    {
        User::factory()->create(['username' => 'anna']);

        $this->post('/register', [
            'username' => 'ANNA',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['username', 'password']);

        $this->post('/register', [
            'username' => 'has space',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors('username');

        $this->assertSame(1, User::count());
    }

    public function test_registration_can_be_closed(): void
    {
        config(['flashcards.registration_open' => false]);

        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'username' => 'new_learner',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertNotFound();
        $this->get('/login')->assertDontSee('Register');
    }

    public function test_learners_cannot_open_admin_pages(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_admins_can_learn(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/learn')->assertOk();
        $this->actingAs($admin)->get('/settings')->assertOk();
    }
}
