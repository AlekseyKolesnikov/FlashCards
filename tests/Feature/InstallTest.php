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

    private const ACCOUNT = [
        'username' => 'boss',
        'email' => 'boss@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['flashcards.install_token' => self::TOKEN]);
    }

    public function test_creates_the_first_admin_already_activated(): void
    {
        $this->getJson('/api/install?token='.self::TOKEN)->assertOk();

        $this->postJson('/api/install', ['token' => self::TOKEN] + self::ACCOUNT)
            ->assertCreated()
            ->assertJsonPath('user.role', 'admin');

        $admin = User::where('username', 'boss')->firstOrFail();
        $this->assertSame(Role::Admin, $admin->role);
        $this->assertTrue($admin->hasVerifiedEmail());
        $this->assertAuthenticatedAs($admin);
    }

    public function test_needs_the_right_token(): void
    {
        $this->getJson('/api/install')->assertNotFound();
        $this->getJson('/api/install?token=wrong')->assertNotFound();
        $this->postJson('/api/install', ['token' => 'wrong'] + self::ACCOUNT)->assertNotFound();

        $this->assertSame(0, User::count());
    }

    public function test_is_disabled_without_a_configured_token(): void
    {
        config(['flashcards.install_token' => null]);

        $this->getJson('/api/install?token=')->assertNotFound();
    }

    public function test_is_gone_once_an_admin_exists(): void
    {
        User::factory()->admin()->create();

        $this->getJson('/api/install?token='.self::TOKEN)->assertNotFound();
    }
}
