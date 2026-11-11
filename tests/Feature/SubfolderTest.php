<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The app served from a subfolder of another website, e.g. https://example.com/flashcards/
 * (SPECIFICATION.md §10.2). Laravel sees the folder through SCRIPT_NAME, as under Apache.
 */
class SubfolderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        // Cookies follow the folder unless SESSION_PATH says otherwise.
        config(['session.path' => null]);
        $this->withServerVariables([
            'SCRIPT_NAME' => '/flashcards/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
        ]);
    }

    public function test_pages_link_inside_the_folder(): void
    {
        $this->get($this->at('/app/admin/cards'))
            ->assertOk()
            ->assertSee('"appPath":"\/flashcards\/app"', false)
            ->assertSee('"apiPath":"\/flashcards\/api"', false)
            ->assertSee('"cookiePath":"\/flashcards"', false)
            ->assertSee('"landingUrl":'.json_encode($this->root().'/flashcards'), false);
    }

    public function test_login_cookies_belong_to_the_folder(): void
    {
        $response = $this->get($this->at('/app/login'));

        $this->assertSame('/flashcards', $response->getCookie('XSRF-TOKEN', false)->getPath());
    }

    public function test_login_and_api_work_in_the_folder(): void
    {
        $user = User::factory()->admin()->create(['username' => 'anna']);

        $this->postJson($this->at('/api/login'), ['username' => 'anna', 'password' => 'password'])->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->getJson($this->at('/api/admin/cards'))->assertOk();
    }

    public function test_activation_link_stays_in_the_folder(): void
    {
        Notification::fake();

        $this->postJson($this->at('/api/register'), [
            'username' => 'anna', 'email' => 'anna@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertCreated();

        $user = User::where('username', 'anna')->sole();
        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;
            $this->assertStringStartsWith($this->root().'/flashcards/api/email/verify/', $url);

            $this->get($url)->assertRedirect($this->root().'/flashcards/app/login?activated=1');

            return true;
        });

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    /**
     * Absolute URL in the folder. The test client builds relative URLs with url(), which
     * already contains the folder after the first request.
     */
    private function at(string $path): string
    {
        return $this->root().'/flashcards'.$path;
    }

    /** Scheme and host the tests run on (APP_URL). */
    private function root(): string
    {
        return rtrim(config('app.url'), '/');
    }
}
