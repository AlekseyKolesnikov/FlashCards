<?php

namespace Tests\Feature\Learn;

use App\Models\Card;
use App\Models\Language;
use App\Models\LearnerCard;
use App\Models\StudySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Learn mode through the API (SPECIFICATION.md §6).
 */
class LearnScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $learner;

    private Language $en;

    private Language $de;

    private Card $card;

    protected function setUp(): void
    {
        parent::setUp();

        $this->learner = User::factory()->create(['session_size' => 20]);
        $this->en = Language::factory()->create(['code' => 'en', 'name' => 'English']);
        $this->de = Language::factory()->create(['code' => 'de', 'name' => 'German']);
        $this->card = Card::factory()->create([
            'lang1_id' => $this->en->id, 'word1' => 'dog', 'notes1' => 'animal', 'examples1' => 'The dog barks.',
            'lang2_id' => $this->de->id, 'word2' => 'der Hund', 'notes2' => 'male', 'examples2' => 'Der Hund bellt.',
        ]);
        $this->actingAs($this->learner);
    }

    private function startStudy(): StudySession
    {
        $this->postJson('/api/learn', ['from_lang_id' => $this->en->id, 'to_lang_id' => $this->de->id])->assertOk();

        return StudySession::sole();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function answer(StudySession $session, array $data): TestResponse
    {
        return $this->postJson("/api/learn/sessions/{$session->id}/answer", ['card_id' => $this->card->id, ...$data]);
    }

    public function test_start_page_lists_both_directions_with_counts(): void
    {
        $this->getJson('/api/learn')
            ->assertOk()
            ->assertJsonCount(2, 'directions')
            ->assertJsonPath('directions.0.from.name', 'English')
            ->assertJsonPath('directions.0.to.name', 'German')
            ->assertJsonPath('directions.1.from.name', 'German')
            ->assertJsonPath('directions.0.new', 1)
            ->assertJsonPath('directions.0.active', 0)
            ->assertJsonPath('directions.0.learned', 0)
            ->assertJsonPath('directions.0.cards_left', null);
    }

    public function test_start_resumes_the_open_session(): void
    {
        $session = $this->startStudy();

        $this->postJson('/api/learn', ['from_lang_id' => $this->en->id, 'to_lang_id' => $this->de->id])
            ->assertJsonPath('session_id', $session->id);
        $this->getJson('/api/learn')->assertJsonPath('directions.0.cards_left', 1);
    }

    public function test_question_contains_only_the_from_side(): void
    {
        $session = $this->startStudy();

        $response = $this->getJson("/api/learn/sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('session.open', true)
            ->assertJsonPath('session.cards_left', 1)
            ->assertJsonPath('session.to.name', 'German')
            ->assertJsonPath('question', [
                'card_id' => $this->card->id, 'word' => 'dog', 'notes' => 'animal', 'examples' => 'The dog barks.',
            ]);

        // Nothing of the other side reaches the browser before the answer (§6.2).
        foreach (['der Hund', 'male', 'Der Hund bellt.'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $response->getContent());
        }
    }

    public function test_wrong_answer_returns_the_correct_answer(): void
    {
        $session = $this->startStudy();

        $this->answer($session, ['answer' => 'Hund'])
            ->assertOk()
            ->assertJsonPath('result', [
                'card_id' => $this->card->id, 'typed' => 'Hund', 'correct' => false, 'cards_left' => 1,
                'prompt' => 'dog', 'expected' => 'der Hund', 'notes' => 'male', 'examples' => 'Der Hund bellt.',
            ]);

        $this->assertSame(2, LearnerCard::sole()->retry);
    }

    public function test_correct_answer_and_session_summary(): void
    {
        $session = $this->startStudy();

        $this->answer($session, ['dont_know' => true])->assertJsonPath('result.typed', null)->assertJsonPath('result.correct', false);
        $this->answer($session, ['answer' => 'Der Hund.'])->assertJsonPath('result.correct', true)->assertJsonPath('result.cards_left', 0);

        $this->getJson("/api/learn/sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('session.open', false)
            ->assertJsonMissingPath('question')
            ->assertJsonPath('summary', [
                'total' => 1,
                'first_try' => 0,
                'mistakes' => [['card_id' => $this->card->id, 'word' => 'dog', 'answer' => 'der Hund', 'wrong_count' => 1]],
            ]);
    }

    public function test_repeated_submission_changes_nothing(): void
    {
        Card::factory()->create(['lang1_id' => $this->en->id, 'lang2_id' => $this->de->id]);
        $session = $this->startStudy();
        $current = $session->currentEntry()->card;
        $payload = ['card_id' => $current->id, 'answer' => 'wrong'];

        $this->postJson("/api/learn/sessions/{$session->id}/answer", $payload)->assertOk();
        // The same card is now at the end of the queue, so the repeat is ignored (§6.6).
        $this->postJson("/api/learn/sessions/{$session->id}/answer", $payload)->assertConflict();

        $this->assertSame(2, LearnerCard::where('card_id', $current->id)->value('retry'));
    }

    public function test_empty_answer_is_rejected(): void
    {
        $session = $this->startStudy();

        $this->answer($session, ['answer' => ''])->assertJsonValidationErrors('answer');

        $this->assertSame(1, LearnerCard::sole()->retry);
    }

    public function test_learners_only_see_their_own_sessions(): void
    {
        $session = $this->startStudy();

        $this->actingAs(User::factory()->create());
        $this->getJson("/api/learn/sessions/{$session->id}")->assertNotFound();
        $this->answer($session, ['answer' => 'der Hund'])->assertNotFound();
    }

    public function test_message_when_nothing_to_learn(): void
    {
        $fr = Language::factory()->create();

        $this->postJson('/api/learn', ['from_lang_id' => $this->en->id, 'to_lang_id' => $fr->id])
            ->assertJsonValidationErrors(['direction' => 'There are no cards to learn in this direction right now.']);
    }

    public function test_admin_can_edit_a_card_after_answering(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $session = $this->startStudy();
        $this->answer($session, ['answer' => 'Hund'])->assertOk();

        // The card form over the result screen loads and saves the card (§6.3).
        $this->getJson("/api/admin/cards/{$this->card->id}")->assertJsonPath('card.word2', 'der Hund');
        $this->putJson("/api/admin/cards/{$this->card->id}", [
            ...$this->card->only('lang1_id', 'word1', 'lang2_id', 'complexity'), 'word2' => 'Hund, der',
        ])->assertOk()->assertJsonPath('card.word2', 'Hund, der');

        // Editing words keeps the session going.
        $this->getJson("/api/learn/sessions/{$session->id}")->assertJsonPath('question.card_id', $this->card->id);
    }
}
