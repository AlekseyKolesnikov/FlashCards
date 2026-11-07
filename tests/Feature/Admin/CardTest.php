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
        $this->actingAs(User::factory()->create())->get('/admin/cards')->assertForbidden();
    }

    public function test_lists_cards_with_filters(): void
    {
        $fr = Language::factory()->create(['code' => 'fr']);
        $this->card('dog', 'der Hund', ['complexity' => 1]);
        $this->card('cat', 'die Katze', ['complexity' => 2]);
        Card::factory()->create(['lang1_id' => $fr->id, 'word1' => 'chien', 'lang2_id' => $this->en->id, 'word2' => 'hound']);

        $this->actingAs($this->admin)->get('/admin/cards')
            ->assertOk()->assertSee('der Hund')->assertSee('die Katze')->assertSee('chien');

        $this->get('/admin/cards?pair='.min($this->en->id, $this->de->id).'-'.max($this->en->id, $this->de->id))
            ->assertSee('der Hund')->assertDontSee('chien');

        $this->get('/admin/cards?complexity=2')->assertSee('die Katze')->assertDontSee('der Hund');

        // Searches both words.
        $this->get('/admin/cards?q=hund')->assertSee('der Hund')->assertDontSee('die Katze')->assertDontSee('chien');
        $this->get('/admin/cards?q=DOG')->assertSee('der Hund')->assertDontSee('die Katze');

        // Notes are searched only when asked.
        Card::where('word1', 'cat')->update(['notes2' => 'die Mieze']);
        $this->get('/admin/cards?q=mieze')->assertDontSee('die Katze');
        $this->get('/admin/cards?q=mieze&notes=1')->assertSee('die Katze')->assertDontSee('der Hund');

        $this->get('/admin/cards?sort=word2&dir=asc')->assertOk()->assertSeeInOrder(['der Hund', 'die Katze', 'hound']);
    }

    public function test_creates_a_card(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/cards', $this->cardData())
            ->assertRedirect('/admin/cards')
            ->assertSessionHas('status');

        $card = Card::sole();
        $this->assertSame('der hund', $card->word2_norm);
        $this->assertNull($card->notes1);
        $this->assertSame("Der Hund bellt.\nGuter Hund!", $card->examples2);
    }

    public function test_save_and_add_another_keeps_languages_and_complexity(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/cards', $this->cardData(['another' => '1']))
            ->assertRedirect(route('admin.cards.create', [
                'lang1_id' => $this->en->id, 'lang2_id' => $this->de->id, 'complexity' => 3,
            ]));
    }

    public function test_validates_fields(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/cards', $this->cardData([
                'lang2_id' => $this->en->id, 'word1' => '', 'complexity' => 11, 'examples1' => str_repeat('x', 2001),
            ]))
            ->assertSessionHasErrors(['lang2_id', 'word1', 'complexity', 'examples1']);

        $this->assertSame(0, Card::count());
    }

    public function test_rejects_duplicates_in_either_language_order(): void
    {
        $this->card('dog', 'der Hund');

        $this->actingAs($this->admin)
            ->post('/admin/cards', $this->cardData(['word1' => 'Dog.', 'word2' => 'der  hund']))
            ->assertSessionHasErrors(['word1' => 'This card already exists.']);

        $this->post('/admin/cards', $this->cardData([
            'lang1_id' => $this->de->id, 'word1' => 'der Hund', 'lang2_id' => $this->en->id, 'word2' => 'dog',
        ]))->assertSessionHasErrors('word1');

        // Accents count: "Hünd" is a different word.
        $this->post('/admin/cards', $this->cardData(['word2' => 'der Hünd']))->assertSessionHasNoErrors();

        $this->assertSame(2, Card::count());
    }

    public function test_warns_when_another_card_shares_a_word(): void
    {
        $this->card('bank', 'die Bank');

        $this->actingAs($this->admin)
            ->post('/admin/cards', $this->cardData(['word1' => 'bank', 'word2' => 'das Ufer']))
            ->assertSessionHas('warning', fn ($warning) => str_contains($warning, '“bank” – “die Bank”'));

        $this->assertSame(2, Card::count());
    }

    public function test_editing_words_keeps_progress(): void
    {
        $card = $this->card('dog', 'der Hund');
        $learner = User::factory()->create();
        LearnerCard::create(['user_id' => $learner->id, 'card_id' => $card->id, 'from_lang_id' => $this->en->id, 'retry' => 2]);

        $this->actingAs($this->admin)
            ->put("/admin/cards/{$card->id}", $this->cardData(['word2' => 'Hund, der']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Hund, der', $card->fresh()->word2);
        $this->assertSame(1, LearnerCard::count());
    }

    public function test_swapping_sides_keeps_progress(): void
    {
        $card = $this->card('dog', 'der Hund');
        LearnerCard::create(['user_id' => User::factory()->create()->id, 'card_id' => $card->id, 'from_lang_id' => $this->en->id]);

        $this->actingAs($this->admin)->put("/admin/cards/{$card->id}", $this->cardData([
            'lang1_id' => $this->de->id, 'word1' => 'der Hund', 'lang2_id' => $this->en->id, 'word2' => 'dog',
        ]))->assertSessionHasNoErrors();

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
            ->put("/admin/cards/{$card->id}", $this->cardData(['lang2_id' => $fr->id, 'word2' => 'chien']))
            ->assertSessionHasNoErrors();

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

        $this->actingAs($this->admin)->delete("/admin/cards/{$card->id}")->assertRedirect();

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
            ->delete('/admin/cards', ['ids' => [$dog->id, $cat->id]])
            ->assertSessionHas('status', 'Deleted 2 card(s).');

        $this->assertSame(['mouse'], Card::pluck('word1')->all());
    }

    public function test_sets_complexity_of_selected_cards(): void
    {
        $dog = $this->card('dog', 'der Hund');
        $cat = $this->card('cat', 'die Katze');
        $mouse = $this->card('mouse', 'die Maus');

        $this->actingAs($this->admin)
            ->patch('/admin/cards', ['ids' => [$dog->id, $cat->id], 'complexity' => 2])
            ->assertSessionHas('status', 'Set complexity 2 for 2 card(s).');

        $this->assertSame(2, $dog->fresh()->complexity);
        $this->assertSame(2, $cat->fresh()->complexity);
        $this->assertSame($mouse->complexity, $mouse->fresh()->complexity);
    }

    public function test_setting_complexity_needs_a_valid_value(): void
    {
        $dog = $this->card('dog', 'der Hund');

        $this->actingAs($this->admin)
            ->patch('/admin/cards', ['ids' => [$dog->id], 'complexity' => 99])
            ->assertSessionHasErrors('complexity');

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
