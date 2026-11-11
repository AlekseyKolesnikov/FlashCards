<?php

namespace Tests\Feature\Admin;

use App\Enums\StudySessionStatus;
use App\Models\Card;
use App\Models\Language;
use App\Models\LearnerCard;
use App\Models\StudySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Language $en;

    private Language $de;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->en = Language::factory()->create(['code' => 'en', 'name' => 'English']);
        $this->de = Language::factory()->create(['code' => 'de', 'name' => 'German']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function cardData(array $overrides = []): array
    {
        return array_merge([
            'lang1_id' => $this->en->id, 'word1' => 'dog', 'notes1' => '', 'examples1' => '',
            'lang2_id' => $this->de->id, 'word2' => 'der Hund', 'notes2' => 'male', 'examples2' => "Der Hund bellt.\nGuter Hund!",
            'complexity' => 3,
        ], $overrides);
    }

    private function card(string $word1, string $word2, array $attributes = []): Card
    {
        return Card::factory()->create(array_merge(
            ['lang1_id' => $this->en->id, 'word1' => $word1, 'lang2_id' => $this->de->id, 'word2' => $word2],
            $attributes,
        ));
    }

    public function test_learners_cannot_manage_cards(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/admin/cards')->assertForbidden();
    }

    /**
     * Word 2 of the listed cards, in list order.
     *
     * @return list<string>
     */
    private function listed(string $query = ''): array
    {
        return array_column($this->getJson('/api/admin/cards?'.$query)->assertOk()->json('cards'), 'word2');
    }

    public function test_lists_cards_with_filters(): void
    {
        $fr = Language::factory()->create(['code' => 'fr']);
        $this->card('dog', 'der Hund', ['complexity' => 1]);
        $this->card('cat', 'die Katze', ['complexity' => 2]);
        Card::factory()->create(['lang1_id' => $fr->id, 'word1' => 'chien', 'lang2_id' => $this->en->id, 'word2' => 'hound']);
        $this->actingAs($this->admin);

        $this->getJson('/api/admin/cards')
            ->assertJsonPath('total', 3)
            ->assertJsonPath('cards.0.lang1', ['code' => 'fr', 'name' => $fr->name])
            ->assertJsonCount(2, 'pairs');

        $this->assertEqualsCanonicalizing(['der Hund', 'die Katze'],
            $this->listed('pair='.min($this->en->id, $this->de->id).'-'.max($this->en->id, $this->de->id)));

        $this->assertSame(['die Katze'], $this->listed('complexity=2'));

        // Searches both words.
        $this->assertSame(['der Hund'], $this->listed('q=hund'));
        $this->assertSame(['der Hund'], $this->listed('q=DOG'));

        // Notes are searched only when asked.
        Card::where('word1', 'cat')->update(['notes2' => 'die Mieze']);
        $this->assertSame([], $this->listed('q=mieze'));
        $this->assertSame(['die Katze'], $this->listed('q=mieze&notes=1'));

        $this->assertSame(['der Hund', 'die Katze', 'hound'], $this->listed('sort=word2&dir=asc'));
    }

    public function test_lists_50_cards_per_page(): void
    {
        Card::factory()->count(51)->create(['lang1_id' => $this->en->id, 'lang2_id' => $this->de->id]);

        $this->actingAs($this->admin)->getJson('/api/admin/cards?page=2')
            ->assertJsonPath('page', 2)
            ->assertJsonPath('last_page', 2)
            ->assertJsonCount(1, 'cards');
    }

    public function test_creates_a_card(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/admin/cards', $this->cardData())
            ->assertCreated()
            ->assertJsonPath('card.word2', 'der Hund')
            ->assertJsonPath('message', 'Saved “dog” – “der Hund”.')
            ->assertJsonPath('warning', null);

        $card = Card::sole();
        $this->assertSame('der hund', $card->word2_norm);
        $this->assertNull($card->notes1);
        $this->assertSame("Der Hund bellt.\nGuter Hund!", $card->examples2);
    }

    public function test_shows_a_card_for_editing(): void
    {
        $card = $this->card('dog', 'der Hund', ['notes2' => 'male']);

        $this->actingAs($this->admin)->getJson("/api/admin/cards/{$card->id}")
            ->assertOk()
            ->assertJsonPath('card.id', $card->id)
            ->assertJsonPath('card.notes2', 'male')
            ->assertJsonMissingPath('card.word1_norm');

        $this->getJson('/api/admin/cards/999999')->assertNotFound();
    }

    public function test_validates_fields(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/admin/cards', $this->cardData([
                'lang2_id' => $this->en->id, 'word1' => '', 'complexity' => 11, 'examples1' => str_repeat('x', 2001),
            ]))
            ->assertJsonValidationErrors(['lang2_id', 'word1', 'complexity', 'examples1']);

        $this->assertSame(0, Card::count());
    }

    public function test_rejects_duplicates_in_either_language_order(): void
    {
        $this->card('dog', 'der Hund');

        $this->actingAs($this->admin)
            ->postJson('/api/admin/cards', $this->cardData(['word1' => 'Dog.', 'word2' => 'der  hund']))
            ->assertJsonValidationErrors(['word1' => 'This card already exists.']);

        $this->postJson('/api/admin/cards', $this->cardData([
            'lang1_id' => $this->de->id, 'word1' => 'der Hund', 'lang2_id' => $this->en->id, 'word2' => 'dog',
        ]))->assertJsonValidationErrors('word1');

        // Accents count: "Hünd" is a different word.
        $this->postJson('/api/admin/cards', $this->cardData(['word2' => 'der Hünd']))->assertCreated();

        $this->assertSame(2, Card::count());
    }

    public function test_warns_when_another_card_shares_a_word(): void
    {
        $this->card('bank', 'die Bank');

        $this->actingAs($this->admin)
            ->postJson('/api/admin/cards', $this->cardData(['word1' => 'bank', 'word2' => 'das Ufer']))
            ->assertJsonPath('warning', fn (?string $warning) => str_contains((string) $warning, '“bank” – “die Bank”'));

        $this->assertSame(2, Card::count());
    }

    public function test_editing_words_keeps_progress(): void
    {
        $card = $this->card('dog', 'der Hund');
        $learner = User::factory()->create();
        LearnerCard::create(['user_id' => $learner->id, 'card_id' => $card->id, 'from_lang_id' => $this->en->id, 'retry' => 2]);

        // Form input arrives as strings, e.g. "3" for a language ID. That is not a language change.
        $this->actingAs($this->admin)
            ->putJson("/api/admin/cards/{$card->id}", array_map('strval', $this->cardData(['word2' => 'Hund, der'])))
            ->assertOk()
            // Numbers in the response, so the app can compare language IDs.
            ->assertJsonPath('card.lang1_id', $this->en->id)
            ->assertJsonPath('card.complexity', 3);

        $this->assertSame('Hund, der', $card->fresh()->word2);
        $this->assertSame(1, LearnerCard::count());
    }

    public function test_swapping_sides_keeps_progress(): void
    {
        $card = $this->card('dog', 'der Hund');
        LearnerCard::create(['user_id' => User::factory()->create()->id, 'card_id' => $card->id, 'from_lang_id' => $this->en->id]);

        $this->actingAs($this->admin)->putJson("/api/admin/cards/{$card->id}", $this->cardData([
            'lang1_id' => $this->de->id, 'word1' => 'der Hund', 'lang2_id' => $this->en->id, 'word2' => 'dog',
        ]))->assertOk();

        $this->assertSame(1, LearnerCard::count());
    }

    public function test_changing_languages_resets_progress(): void
    {
        $fr = Language::factory()->create();
        $card = $this->card('dog', 'der Hund');
        $other = $this->card('cat', 'die Katze');
        $learner = User::factory()->create();
        LearnerCard::create(['user_id' => $learner->id, 'card_id' => $card->id, 'from_lang_id' => $this->en->id]);

        // An open session that only has this card left, and one that has another card too.
        $onlyThis = $this->openSession($learner, [$card]);
        $mixed = $this->openSession($learner, [$card, $other], seq: 2);

        $this->actingAs($this->admin)
            ->putJson("/api/admin/cards/{$card->id}", $this->cardData(['lang2_id' => $fr->id, 'word2' => 'chien']))
            ->assertOk();

        $this->assertSame(0, LearnerCard::count());
        $this->assertSame(StudySessionStatus::Finished, $onlyThis->fresh()->status);
        $this->assertSame(StudySessionStatus::Open, $mixed->fresh()->status);
        $this->assertSame(1, $mixed->cards()->count());
    }

    public function test_deletes_a_card_with_its_progress(): void
    {
        $card = $this->card('dog', 'der Hund');
        $learner = User::factory()->create();
        LearnerCard::create(['user_id' => $learner->id, 'card_id' => $card->id, 'from_lang_id' => $this->en->id]);
        $session = $this->openSession($learner, [$card]);

        $this->actingAs($this->admin)->deleteJson("/api/admin/cards/{$card->id}")->assertOk();

        $this->assertSame(0, Card::count());
        $this->assertSame(0, LearnerCard::count());
        $this->assertSame(StudySessionStatus::Finished, $session->fresh()->status);
    }

    public function test_deletes_selected_cards(): void
    {
        $dog = $this->card('dog', 'der Hund');
        $cat = $this->card('cat', 'die Katze');
        $this->card('mouse', 'die Maus');

        $this->actingAs($this->admin)
            ->deleteJson('/api/admin/cards', ['ids' => [$dog->id, $cat->id]])
            ->assertJsonPath('message', 'Deleted 2 card(s).');

        $this->assertSame(['mouse'], Card::pluck('word1')->all());
    }

    public function test_sets_complexity_of_selected_cards(): void
    {
        $dog = $this->card('dog', 'der Hund');
        $cat = $this->card('cat', 'die Katze');
        $mouse = $this->card('mouse', 'die Maus');

        $this->actingAs($this->admin)
            ->patchJson('/api/admin/cards', ['ids' => [$dog->id, $cat->id], 'complexity' => 2])
            ->assertJsonPath('message', 'Set complexity 2 for 2 card(s).');

        $this->assertSame(2, $dog->fresh()->complexity);
        $this->assertSame(2, $cat->fresh()->complexity);
        $this->assertSame($mouse->complexity, $mouse->fresh()->complexity);
    }

    public function test_setting_complexity_needs_a_valid_value(): void
    {
        $dog = $this->card('dog', 'der Hund');

        $this->actingAs($this->admin)
            ->patchJson('/api/admin/cards', ['ids' => [$dog->id], 'complexity' => 99])
            ->assertJsonValidationErrors('complexity');

        $this->assertSame($dog->complexity, $dog->fresh()->complexity);
    }

    /**
     * @param  list<Card>  $cards
     */
    private function openSession(User $learner, array $cards, int $seq = 1): StudySession
    {
        $session = StudySession::create([
            'user_id' => $learner->id, 'from_lang_id' => $this->en->id, 'to_lang_id' => $this->de->id,
            'seq' => $seq, 'status' => StudySessionStatus::Open,
        ]);

        foreach ($cards as $position => $card) {
            $session->cards()->create(['card_id' => $card->id, 'position' => $position + 1]);
        }

        return $session;
    }
}
