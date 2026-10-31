<?php

namespace Tests\Feature\Admin;

use App\Enums\StudySessionStatus;
use App\Models\Card;
use App\Models\Language;
use App\Models\StudySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_adds_a_language_with_a_lower_case_code(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/languages', ['code' => ' PT-BR ', 'name' => 'Portuguese (Brazil)'])
            ->assertRedirect('/admin/languages');

        $this->assertSame('pt-br', Language::sole()->code);
        $this->get('/admin/languages')->assertSee('Portuguese (Brazil)');
    }

    public function test_codes_are_unique(): void
    {
        Language::factory()->create(['code' => 'de']);

        $this->actingAs($this->admin)
            ->post('/admin/languages', ['code' => 'DE', 'name' => 'Deutsch'])
            ->assertSessionHasErrors('code');
    }

    public function test_renames_a_language(): void
    {
        $language = Language::factory()->create(['code' => 'de', 'name' => 'Deutsch']);

        $this->actingAs($this->admin)
            ->put("/admin/languages/{$language->id}", ['code' => 'de', 'name' => 'German'])
            ->assertSessionHasNoErrors();

        $this->assertSame('German', $language->fresh()->name);
    }

    public function test_cannot_delete_a_language_used_by_cards(): void
    {
        $card = Card::factory()->create();

        $this->actingAs($this->admin)
            ->delete("/admin/languages/{$card->lang1_id}")
            ->assertSessionHas('error');

        $this->assertNotNull(Language::find($card->lang1_id));
    }

    public function test_deletes_an_unused_language_with_its_old_sessions(): void
    {
        [$en, $de] = Language::factory()->count(2)->create();
        StudySession::create([
            'user_id' => User::factory()->create()->id, 'from_lang_id' => $en->id, 'to_lang_id' => $de->id,
            'seq' => 1, 'status' => StudySessionStatus::Finished,
        ]);

        $this->actingAs($this->admin)->delete("/admin/languages/{$de->id}")->assertSessionHas('status');

        $this->assertNull(Language::find($de->id));
        $this->assertSame(0, StudySession::count());
    }
}
