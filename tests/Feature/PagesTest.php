<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The only pages Laravel renders: the landing page and the page that loads the React app
 * (SPECIFICATION.md §10.2).
 */
class PagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_landing_page_invites_guests(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Learn foreign words with flashcards')
            ->assertSee('href="'.url('/app/login').'"', false)
            ->assertSee('href="'.url('/app/register').'"', false);
    }

    public function test_landing_page_hides_registration_when_closed(): void
    {
        config(['flashcards.registration_open' => false]);

        $this->get('/')->assertOk()->assertDontSee('/app/register');
    }

    public function test_landing_page_opens_the_app_when_logged_in(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertSee('Open the app')
            ->assertDontSee('/app/login');
    }

    public function test_every_app_url_loads_the_react_app(): void
    {
        foreach (['/app', '/app/login', '/app/learn/sessions/5', '/app/admin/cards'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('<div id="root"></div>', false)
                ->assertSee('"appPath":"\/app"', false)
                ->assertSee('"apiPath":"\/api"', false);
        }
    }

    public function test_old_screens_are_gone(): void
    {
        $this->get('/login')->assertNotFound();
        $this->get('/admin/cards')->assertNotFound();
    }
}
