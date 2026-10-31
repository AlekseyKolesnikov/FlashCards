<?php

namespace Tests\Feature\Learn;

use App\Enums\StudySessionStatus;
use App\Models\Card;
use App\Models\Language;
use App\Models\LearnerCard;
use App\Models\StudySession;
use App\Models\User;
use App\Services\ProgressService;
use App\Services\SessionBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Session building and the retry counter (SPECIFICATION.md §8).
 */
class SessionRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $learner;

    private Language $en;

    private Language $de;

    protected function setUp(): void
    {
        parent::setUp();

        $this->learner = User::factory()->create(['session_size' => 5, 'postpone_sessions' => 2]);
        $this->en = Language::factory()->create(['code' => 'en']);
        $this->de = Language::factory()->create(['code' => 'de']);
    }

    private function card(string $word, int $complexity = 1): Card
    {
        return Card::factory()->create([
            'lang1_id' => $this->en->id, 'word1' => $word,
            'lang2_id' => $this->de->id, 'word2' => "{$word}-de",
            'complexity' => $complexity,
        ]);
    }

    private function start(): ?StudySession
    {
        return app(SessionBuilder::class)->start($this->learner, $this->en->id, $this->de->id);
    }

    /**
     * Answer the whole session. $answers maps a card ID to its answers in order
     * (true = correct, false = wrong); cards not listed are answered correctly.
     *
     * @param  array<int, list<bool>>  $answers
     */
    private function play(StudySession $session, array $answers = []): void
    {
        $progress = app(ProgressService::class);

        while ($entry = $session->currentEntry()) {
            $card = $entry->card;
            $correct = isset($answers[$card->id]) ? (array_shift($answers[$card->id]) ?? true) : true;
            $progress->answer($session, $card->id, $correct ? $card->word2 : 'wrong');
        }
    }

    private function retry(Card $card): ?int
    {
        return LearnerCard::where('card_id', $card->id)->where('from_lang_id', $this->en->id)->value('retry');
    }

    /**
     * @return list<int>
     */
    private function cardIds(StudySession $session): array
    {
        return $session->cards()->orderBy('card_id')->pluck('card_id')->all();
    }

    public function test_new_cards_come_lowest_complexity_first(): void
    {
        $hard = collect(range(1, 5))->map(fn ($i) => $this->card("hard{$i}", 5));
        $easy = collect(range(1, 4))->map(fn ($i) => $this->card("easy{$i}", 1));
        $this->card('medium', 3);

        $session = $this->start();

        $this->assertSame(1, $session->seq);
        $this->assertCount(5, $session->cards);
        $this->assertSame(
            [...$easy->pluck('id')->all(), Card::where('word1', 'medium')->value('id')],
            $this->cardIds($session),
        );
        // Every card in the session gets a progress record with retry 1.
        $this->assertSame(5, LearnerCard::where('retry', 1)->count());
        $this->assertEqualsCanonicalizing([1, 2, 3, 4, 5], $session->cards()->pluck('position')->all());
        $this->assertNotContains($hard[0]->id, $this->cardIds($session));
    }

    public function test_resumes_the_open_session(): void
    {
        $this->card('dog');

        $first = $this->start();

        $this->assertTrue($first->is($this->start()));
        $this->assertSame(1, StudySession::count());
    }

    public function test_wrong_answer_moves_the_card_to_the_end_and_raises_retry(): void
    {
        $this->learner->update(['session_size' => 2]);
        $this->card('dog');
        $this->card('cat');
        $session = $this->start();
        $first = $session->currentEntry()->card;
        $other = Card::whereKeyNot($first->id)->sole();

        $result = app(ProgressService::class)->answer($session, $first->id, 'nonsense');

        $this->assertSame(['correct' => false, 'cards_left' => 2], $result);
        $this->assertSame($other->id, $session->currentEntry()->card_id);
        $this->assertSame(2, $this->retry($first));
    }

    public function test_only_the_current_card_can_be_answered(): void
    {
        $this->learner->update(['session_size' => 2]);
        $this->card('dog');
        $this->card('cat');
        $session = $this->start();
        $current = $session->currentEntry()->card;
        $other = Card::whereKeyNot($current->id)->sole();

        $this->assertNull(app(ProgressService::class)->answer($session, $other->id, $other->word2));

        $this->assertSame(1, $this->retry($other));
        $this->assertSame(2, $session->cardsLeft());
    }

    public function test_dont_know_counts_as_wrong(): void
    {
        $card = $this->card('dog');
        $session = $this->start();

        $result = app(ProgressService::class)->answer($session, $card->id, null);

        $this->assertFalse($result['correct']);
        $this->assertSame(2, $this->retry($card));
    }

    public function test_session_finishes_when_every_card_is_answered_correctly(): void
    {
        $dog = $this->card('dog');
        $session = $this->start();

        $this->play($session, [$dog->id => [false, false, true]]);

        $session->refresh();
        $this->assertSame(StudySessionStatus::Finished, $session->status);
        $this->assertNotNull($session->finished_at);
        $this->assertSame(2, $session->cards()->sole()->wrong_count);
        $this->assertSame(2, $this->retry($dog));
    }

    /**
     * The worked example from §8.4, with a session size of 1 and P = 2.
     * Two filler cards keep sessions 5 and 6 from being empty.
     */
    public function test_worked_example(): void
    {
        $this->learner->update(['session_size' => 1]);
        $card = $this->card('dog', 1);
        $this->card('filler1', 2);
        $this->card('filler2', 3);

        $expectations = [
            // session => [card shown?, answers, retry after, learned_session after]
            1 => [true, [false, false, true], 2, null],
            2 => [true, [true], 1, null],
            3 => [true, [false, true], 1, null],
            4 => [true, [true], 0, 4],
            5 => [false, [], 0, 4],
            6 => [false, [], 0, 4],
            7 => [true, [true], 0, 7],
        ];

        foreach ($expectations as $seq => [$shown, $answers, $retry, $learnedSession]) {
            $session = $this->start();
            $this->assertSame($seq, $session->seq);
            $this->assertSame($shown, in_array($card->id, $this->cardIds($session), true), "session {$seq}");

            $this->play($session, [$card->id => $answers]);

            $progress = LearnerCard::where('card_id', $card->id)->sole();
            $this->assertSame($retry, $progress->retry, "retry after session {$seq}");
            $this->assertSame($learnedSession, $progress->learned_session, "learned_session after session {$seq}");
        }
    }

    public function test_active_cards_are_all_included_even_above_the_session_size(): void
    {
        $cards = collect(range(1, 5))->map(fn ($i) => $this->card("word{$i}"));
        $this->play($this->start(), $cards->mapWithKeys(fn ($card) => [$card->id => [false, true]])->all());

        $this->learner->update(['session_size' => 3]);
        $this->card('new');

        $this->assertCount(5, $this->cardIds($this->start()));
    }

    public function test_directions_have_separate_progress(): void
    {
        $dog = $this->card('dog');
        $this->play($this->start(), [$dog->id => [true]]);

        $reverse = app(SessionBuilder::class)->start($this->learner, $this->de->id, $this->en->id);

        $this->assertSame(1, $reverse->seq);
        $this->assertSame([$dog->id], $this->cardIds($reverse));
        $this->assertSame(2, LearnerCard::count());
    }

    public function test_reverse_direction_asks_for_the_other_side(): void
    {
        $dog = $this->card('dog');
        $session = app(SessionBuilder::class)->start($this->learner, $this->de->id, $this->en->id);

        $this->assertFalse(app(ProgressService::class)->answer($session, $dog->id, 'dog-de')['correct']);
        $this->assertTrue(app(ProgressService::class)->answer($session, $dog->id, 'DOG')['correct']);
    }

    public function test_no_session_without_cards(): void
    {
        $this->assertNull($this->start());
        $this->assertSame(0, StudySession::count());
    }

    public function test_when_everything_is_postponed_the_cards_closest_to_release_come_back(): void
    {
        $dog = $this->card('dog');
        $cat = $this->card('cat');
        $this->learner->update(['session_size' => 1]);

        $this->play($this->start(), [$dog->id => [true], $cat->id => [true]]);   // session 1: learns one card
        $this->play($this->start());                                              // session 2: learns the other
        $firstLearned = LearnerCard::where('learned_session', 1)->value('card_id');

        // Both are postponed (P = 2), so session 3 would be empty.
        $session = $this->start();

        $this->assertSame(3, $session->seq);
        $this->assertSame([$firstLearned], $this->cardIds($session));
        $this->assertSame(1, $this->retry(Card::find($firstLearned)));
    }
}
