<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-install-token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['flashcards.install_token' => self::TOKEN]);
    }

    public function test_creates_the_first_admin(): void
    {
        $this->get('/install?token='.self::TOKEN)->assertOk()->assertSee('Install');

        $this->post('/install', [
            'token' => self::TOKEN,
            'username' => 'boss',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect('/');

        $admin = User::where('username', 'boss')->firstOrFail();
        $this->assertSame(Role::Admin, $admin->role);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_needs_the_right_token(): void
    {
        $this->get('/install')->assertNotFound();
        $this->get('/install?token=wrong')->assertNotFound();
        $this->post('/install', [
            'token' => 'wrong',
            'username' => 'boss',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertNotFound();

        $this->assertSame(0, User::count());
    }

    public function test_is_disabled_without_a_configured_token(): void
    {
        config(['flashcards.install_token' => null]);

        $this->get('/install?token=')->assertNotFound();
    }

    public function test_is_gone_once_an_admin_exists(): void
    {
        User::factory()->admin()->create();

        $this->get('/install?token='.self::TOKEN)->assertNotFound();
    }
}
