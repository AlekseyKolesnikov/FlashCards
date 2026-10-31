<?php

namespace App\Services;

use App\Enums\StudySessionStatus;
use App\Models\LearnerCard;
use App\Models\StudySession;
use Illuminate\Support\Facades\DB;

/**
 * Applies an answer to the session queue and the retry counter (SPECIFICATION.md §8.3).
 */
class ProgressService
{
    public function __construct(private AnswerChecker $checker) {}

    /**
     * Answer the card at the head of the queue. $typed is null for "I don't know".
     * Returns null, changing nothing, when the session is finished or the card is
     * not the current one (a refreshed or repeated submission).
     *
     * @return array{correct: bool, cards_left: int}|null
     */
    public function answer(StudySession $session, int $cardId, ?string $typed): ?array
    {
        return DB::transaction(function () use ($session, $cardId, $typed) {
            $session = StudySession::lockForUpdate()->find($session->id);
            $entry = $session?->currentEntry();

            if (! $session?->isOpen() || ! $entry || $entry->card_id !== $cardId) {
                return null;
            }

            $expected = $entry->card->sideField('word', $session->to_lang_id);
            $correct = $typed !== null && $this->checker->matches($typed, $expected);

            $progress = LearnerCard::firstOrNew(
                ['user_id' => $session->user_id, 'card_id' => $cardId, 'from_lang_id' => $session->from_lang_id],
                ['retry' => 1],
            );

            if ($correct) {
                $progress->retry = max(0, $progress->retry - 1);
                if ($progress->retry === 0) {
                    $progress->learned_session = $session->seq;
                }
                $entry->done = true;
            } else {
                $progress->retry++;
                $entry->wrong_count++;
                $entry->position = $session->cards()->max('position') + 1;
            }

            $progress->save();
            $entry->save();

            $left = $session->cardsLeft();
            if ($left === 0) {
                $session->update(['status' => StudySessionStatus::Finished, 'finished_at' => now()]);
            }

            return ['correct' => $correct, 'cards_left' => $left];
        });
    }
}
