<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

/**
 * Login, logout, registration and activation by email (SPECIFICATION.md §9.1).
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const ACCOUNT = [
        'username' => 'new_learner',
        'email' => 'new@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ];

    public function test_guests_get_401_from_the_api(): void
    {
        $this->getJson('/api/admin/cards')->assertUnauthorized();
        $this->getJson('/api/learn')->assertUnauthorized();
        $this->getJson('/api/user')->assertOk()->assertExactJson(['user' => null]);
    }

    public function test_api_errors_are_json_without_the_accept_header(): void
    {
        $this->get('/api/learn')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_login_with_correct_password(): void
    {
        $user = User::factory()->create(['username' => 'anna']);

        // Usernames are case-insensitive.
        $this->postJson('/api/login', ['username' => 'Anna', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.username', 'anna')
            ->assertJsonPath('user.role', 'learner');

        $this->assertAuthenticatedAs($user);
        $this->getJson('/api/user')->assertJsonPath('user.id', $user->id);
    }

    public function test_login_with_wrong_password(): void
    {
        User::factory()->create(['username' => 'anna']);

        $this->postJson('/api/login', ['username' => 'anna', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');

        $this->assertGuest();
    }

    public function test_logout(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/api/logout')->assertOk();

        $this->assertGuest();
    }

    public function test_registration_creates_an_inactive_learner_and_sends_the_link(): void
    {
        Notification::fake();

        $this->postJson('/api/register', self::ACCOUNT)
            ->assertCreated()
            ->assertJsonPath('message', 'We sent an activation link to new@example.com. Open it to activate your account.');

        $user = User::where('username', 'new_learner')->firstOrFail();
        $this->assertSame(Role::Learner, $user->role);
        $this->assertSame('new@example.com', $user->email);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertGuest();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_inactive_account_cannot_log_in(): void
    {
        User::factory()->unverified()->create(['username' => 'anna']);

        $this->postJson('/api/login', ['username' => 'anna', 'password' => 'password'])
            ->assertForbidden()
            ->assertJsonPath('code', 'not_activated');

        $this->assertGuest();
    }

    public function test_activation_link_activates_and_leads_to_login(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->activationUrl($user))->assertRedirect(url('/app/login').'?activated=1');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->postJson('/api/login', ['username' => $user->username, 'password' => 'password'])->assertOk();
    }

    public function test_the_activation_email_contains_a_working_link(): void
    {
        Notification::fake();
        $this->postJson('/api/register', self::ACCOUNT)->assertCreated();
        $user = User::where('username', 'new_learner')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user) {
            $mail = $notification->toMail($user);
            $this->get($mail->actionUrl)->assertRedirect(url('/app/login').'?activated=1');

            return str_contains($mail->subject, 'Activate');
        });

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_expired_or_altered_links_do_not_activate(): void
    {
        $user = User::factory()->unverified()->create();
        $invalid = url('/app/login').'?activation=invalid';

        $expired = URL::temporarySignedRoute('api.email.verify', now()->subMinute(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($expired)->assertRedirect($invalid);

        $this->get(str_replace('signature=', 'signature=x', $this->activationUrl($user)))->assertRedirect($invalid);

        $wrongHash = URL::temporarySignedRoute('api.email.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1('other@example.com')]);
        $this->get($wrongHash)->assertRedirect($invalid);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_link_can_be_sent_again_with_the_password(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['username' => 'anna']);

        $this->postJson('/api/email/resend', ['username' => 'anna', 'password' => 'wrong'])
            ->assertJsonValidationErrors('username');
        Notification::assertNothingSent();

        $this->postJson('/api/email/resend', ['username' => 'anna', 'password' => 'password'])->assertOk();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resending_is_limited(): void
    {
        Notification::fake();
        User::factory()->unverified()->create(['username' => 'anna']);

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/email/resend', ['username' => 'anna', 'password' => 'password'])->assertOk();
        }
        $this->postJson('/api/email/resend', ['username' => 'anna', 'password' => 'password'])->assertTooManyRequests();
    }

    public function test_registration_survives_a_mail_failure(): void
    {
        Mail::extend('failing', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Connection refused');
            }

            public function __toString(): string
            {
                return 'failing';
            }
        });
        config(['mail.default' => 'failing', 'mail.mailers.failing' => ['transport' => 'failing']]);

        $this->postJson('/api/register', self::ACCOUNT)
            ->assertCreated()
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'could not be sent'));

        $this->assertDatabaseHas('users', ['username' => 'new_learner', 'email_verified_at' => null]);
    }

    public function test_registration_validates_input(): void
    {
        User::factory()->create(['username' => 'anna', 'email' => 'anna@example.com']);

        $this->postJson('/api/register', [
            'username' => 'ANNA',
            'email' => 'ANNA@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertJsonValidationErrors(['username', 'email', 'password']);

        $this->postJson('/api/register', ['username' => 'has space', 'email' => 'not an email'] + self::ACCOUNT)
            ->assertJsonValidationErrors(['username', 'email']);

        $this->assertSame(1, User::count());
    }

    public function test_registration_can_be_closed(): void
    {
        config(['flashcards.registration_open' => false]);

        $this->postJson('/api/register', self::ACCOUNT)->assertNotFound();
    }

    public function test_learners_cannot_use_admin_endpoints(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/admin/cards')->assertForbidden();
    }

    public function test_admins_can_learn(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/learn')->assertOk();
        $this->actingAs($admin)->putJson('/api/settings', ['session_size' => 10, 'postpone_sessions' => 3])->assertOk();
    }

    private function activationUrl(User $user): string
    {
        return URL::temporarySignedRoute('api.email.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
    }
}
