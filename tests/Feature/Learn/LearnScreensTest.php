<?php

namespace Tests\Feature\Learn;

use App\Models\Card;
use App\Models\Language;
use App\Models\LearnerCard;
use App\Models\StudySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Learn mode screens (SPECIFICATION.md §6).
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
        $this->post('/learn', ['from_lang_id' => $this->en->id, 'to_lang_id' => $this->de->id])->assertRedirect();

        return StudySession::sole();
    }

    public function test_start_page_lists_both_directions_with_counts(): void
    {
        $this->get('/learn')
            ->assertOk()
            ->assertSee('English → German')
            ->assertSee('German → English')
            ->assertSeeInOrder(['1', 'new', '0', 'learning', '0', 'learned']);
    }

    public function test_question_shows_only_the_from_side(): void
    {
        $session = $this->startStudy();

        $this->get("/learn/sessions/{$session->id}")
            ->assertOk()
            ->assertSee('dog')
            ->assertSee('animal')
            ->assertSee('The dog barks.')
            ->assertSee('1 card left')
            ->assertDontSee('der Hund')
            ->assertDontSee('male')
            ->assertDontSee('Der Hund bellt.');

        $this->get('/learn')->assertSee('Resume (1 left)');
    }

    public function test_wrong_answer_shows_the_correct_answer(): void
    {
        $session = $this->startStudy();

        $this->post("/learn/sessions/{$session->id}/answer", ['card_id' => $this->card->id, 'answer' => 'Hund'])
            ->assertRedirect("/learn/sessions/{$session->id}/result");

        $this->get("/learn/sessions/{$session->id}/result")
            ->assertOk()
            ->assertSee('Incorrect')
            ->assertSee('Your answer:')
            ->assertSee('Hund')
            ->assertSee('der Hund')
            ->assertSee('male')
            ->assertSee('Der Hund bellt.');

        $this->assertSame(2, LearnerCard::sole()->retry);
    }

    public function test_correct_answer_and_session_summary(): void
    {
        $session = $this->startStudy();
        $answer = fn (array $data) => $this->post("/learn/sessions/{$session->id}/answer", ['card_id' => $this->card->id, ...$data]);

        $answer(['dont_know' => '1']);
        $this->get("/learn/sessions/{$session->id}/result")->assertSee('Not known')->assertDontSee('Your answer:');

        $answer(['answer' => 'Der Hund.']);
        $this->get("/learn/sessions/{$session->id}/result")->assertSee('Correct')->assertSee('See results');

        $this->get("/learn/sessions/{$session->id}")
            ->assertOk()
            ->assertSee('Session complete')
            ->assertSeeInOrder(['0', 'of', '1'])
            ->assertSeeInOrder(['dog', 'der Hund', '1'])
            ->assertSee('Start next session');
    }

    public function test_repeated_submission_changes_nothing(): void
    {
        Card::factory()->create(['lang1_id' => $this->en->id, 'lang2_id' => $this->de->id]);
        $session = $this->startStudy();
        $current = $session->currentEntry()->card;
        $payload = ['card_id' => $current->id, 'answer' => 'wrong'];

        $this->post("/learn/sessions/{$session->id}/answer", $payload);
        // The same card is now at the end of the queue, so the repeat is ignored.
        $this->post("/learn/sessions/{$session->id}/answer", $payload)->assertRedirect("/learn/sessions/{$session->id}");

        $this->assertSame(2, LearnerCard::where('card_id', $current->id)->value('retry'));
    }

    public function test_empty_answer_is_rejected(): void
    {
        $session = $this->startStudy();

        $this->post("/learn/sessions/{$session->id}/answer", ['card_id' => $this->card->id, 'answer' => ''])
            ->assertSessionHasErrors('answer');

        $this->assertSame(1, LearnerCard::sole()->retry);
    }

    public function test_learners_only_see_their_own_sessions(): void
    {
        $session = $this->startStudy();

        $this->actingAs(User::factory()->create())
            ->get("/learn/sessions/{$session->id}")
            ->assertNotFound();
        $this->post("/learn/sessions/{$session->id}/answer", ['card_id' => $this->card->id, 'answer' => 'der Hund'])
            ->assertNotFound();
    }

    public function test_message_when_nothing_to_learn(): void
    {
        $fr = Language::factory()->create();

        $this->post('/learn', ['from_lang_id' => $this->en->id, 'to_lang_id' => $fr->id])
            ->assertRedirect('/learn')
            ->assertSessionHas('warning', 'There are no cards to learn in this direction right now.');
    }

    public function test_admin_can_edit_a_card_from_the_result_screen(): void
    {
        $this->actingAs($admin = User::factory()->admin()->create());
        $session = $this->startStudy();
        $this->post("/learn/sessions/{$session->id}/answer", ['card_id' => $this->card->id, 'answer' => 'Hund']);
        $editUrl = "/admin/cards/{$this->card->id}/edit?session={$session->id}";

        $this->get("/learn/sessions/{$session->id}/result")->assertSee('Edit card')->assertSee($editUrl, false);
        $this->get($editUrl)->assertOk();
        $this->put("/admin/cards/{$this->card->id}", [
            ...$this->card->only('lang1_id', 'word1', 'lang2_id', 'complexity'), 'word2' => 'Hund, der',
        ])->assertRedirect("/learn/sessions/{$session->id}/result");

        $this->get("/learn/sessions/{$session->id}/result")->assertSee('Hund, der');
    }

    public function test_learner_sees_no_edit_link(): void
    {
        $session = $this->startStudy();
        $this->post("/learn/sessions/{$session->id}/answer", ['card_id' => $this->card->id, 'answer' => 'Hund']);

        $this->get("/learn/sessions/{$session->id}/result")->assertDontSee('Edit card');
    }

    public function test_edit_link_ignores_another_users_session(): void
    {
        $session = $this->startStudy();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get("/admin/cards/{$this->card->id}/edit?session={$session->id}");
        $this->assertNull(session('cards.list_url'));
    }
}
