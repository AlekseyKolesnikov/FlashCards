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
            ->postJson('/api/admin/languages', ['code' => ' PT-BR ', 'name' => 'Portuguese (Brazil)'])
            ->assertCreated()
            ->assertJsonPath('message', 'Added Portuguese (Brazil).');

        $this->assertSame('pt-br', Language::sole()->code);
        $this->getJson('/api/admin/languages')->assertJsonPath('languages.0.name', 'Portuguese (Brazil)');
    }

    public function test_codes_are_unique(): void
    {
        Language::factory()->create(['code' => 'de']);

        $this->actingAs($this->admin)
            ->postJson('/api/admin/languages', ['code' => 'DE', 'name' => 'Deutsch'])
            ->assertJsonValidationErrors('code');
    }

    public function test_renames_a_language(): void
    {
        $language = Language::factory()->create(['code' => 'de', 'name' => 'Deutsch']);

        $this->actingAs($this->admin)->getJson("/api/admin/languages/{$language->id}")->assertJsonPath('language.name', 'Deutsch');

        $this->putJson("/api/admin/languages/{$language->id}", ['code' => 'de', 'name' => 'German'])->assertOk();

        $this->assertSame('German', $language->fresh()->name);
    }

    public function test_cannot_delete_a_language_used_by_cards(): void
    {
        $card = Card::factory()->create();

        $this->actingAs($this->admin)
            ->deleteJson("/api/admin/languages/{$card->lang1_id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'cannot be deleted'));

        $this->assertNotNull(Language::find($card->lang1_id));
    }

    public function test_deletes_an_unused_language_with_its_old_sessions(): void
    {
        [$en, $de] = Language::factory()->count(2)->create();
        StudySession::create([
            'user_id' => User::factory()->create()->id, 'from_lang_id' => $en->id, 'to_lang_id' => $de->id,
            'seq' => 1, 'status' => StudySessionStatus::Finished,
        ]);

        $this->actingAs($this->admin)->deleteJson("/api/admin/languages/{$de->id}")->assertOk();

        $this->assertNull(Language::find($de->id));
        $this->assertSame(0, StudySession::count());
    }

    public function test_counts_words_per_language_and_complexity(): void
    {
        [$en, $de, $pl] = Language::factory()->count(3)->sequence(
            ['code' => 'en', 'name' => 'English'], ['code' => 'de', 'name' => 'German'], ['code' => 'pl', 'name' => 'Polish'],
        )->create();
        Card::factory()->count(2)->create(['lang1_id' => $en->id, 'lang2_id' => $de->id, 'complexity' => 1]);
        Card::factory()->create(['lang1_id' => $pl->id, 'lang2_id' => $en->id, 'complexity' => 3]);

        $data = $this->actingAs($this->admin)->getJson('/api/admin/languages')->assertOk()->json();
        $byLanguage = array_column($data['languages'], 'by_complexity', 'code');

        // Sorted by name: English, German, Polish.
        $this->assertSame(['en', 'de', 'pl'], array_column($data['languages'], 'code'));
        $this->assertSame([3, 2, 1], array_column($data['languages'], 'cards_count'));
        $this->assertSame([1 => 2, 3 => 1], $byLanguage['en']);
        $this->assertSame([1 => 2], $byLanguage['de']);
        $this->assertSame([3 => 1], $byLanguage['pl']);
        $this->assertSame([1, 3], $data['complexities']);
        $this->assertSame([1 => 2, 3 => 1], $data['totals']);
    }

    public function test_complexity_counts_are_objects_even_when_empty(): void
    {
        Language::factory()->create();

        // The app reads counts by complexity; an empty PHP array would arrive as [] instead of {}.
        $this->actingAs($this->admin)->get('/api/admin/languages')
            ->assertSee('"by_complexity":{}', false)
            ->assertSee('"totals":{}', false);
    }
}
