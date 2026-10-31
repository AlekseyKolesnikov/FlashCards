<?php

namespace App\Services;

use App\Enums\StudySessionStatus;
use App\Models\Card;
use App\Models\LearnerCard;
use App\Models\StudySession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Builds study sessions (SPECIFICATION.md §8.2).
 */
class SessionBuilder
{
    /**
     * The open session for this direction, or a new one. Null when there is nothing to learn.
     */
    public function start(User $user, int $fromId, int $toId): ?StudySession
    {
        $open = $this->openSession($user, $fromId, $toId);
        if ($open) {
            return $open;
        }

        try {
            return $this->build($user, $fromId, $toId);
        } catch (UniqueConstraintViolationException) {
            // A parallel request (e.g. a double click) built it first.
            return $this->openSession($user, $fromId, $toId);
        }
    }

    public function openSession(User $user, int $fromId, int $toId): ?StudySession
    {
        return StudySession::where('user_id', $user->id)
            ->where('from_lang_id', $fromId)
            ->where('to_lang_id', $toId)
            ->where('status', StudySessionStatus::Open)
            ->first();
    }

    private function build(User $user, int $fromId, int $toId): ?StudySession
    {
        return DB::transaction(function () use ($user, $fromId, $toId) {
            $seq = (int) StudySession::where('user_id', $user->id)
                ->where('from_lang_id', $fromId)
                ->where('to_lang_id', $toId)
                ->max('seq') + 1;
            $size = $user->sessionSize();

            // 1. All active cards, even if there are more than the session size.
            $active = LearnerCard::where('user_id', $user->id)
                ->where('from_lang_id', $fromId)
                ->where('retry', '>', 0)
                ->whereHas('card', fn (Builder $q) => $q->forLanguagePair($fromId, $toId))
                ->pluck('card_id')
                ->all();

            // 2. Fill up from the pool: new cards and released cards, lowest complexity first.
            $pool = [];
            $free = $size - count($active);

            if ($free > 0) {
                $releasedBefore = $seq - $user->postponeSessions();

                $pool = $this->candidates($user, $fromId, $toId)
                    ->where(fn (Builder $q) => $q
                        ->whereNull('lc.id')
                        ->orWhere(fn (Builder $q) => $q
                            ->where('lc.retry', 0)
                            ->where(fn (Builder $q) => $q
                                ->whereNull('lc.learned_session')
                                ->orWhere('lc.learned_session', '<', $releasedBefore))))
                    ->orderBy('cards.complexity')
                    ->inRandomOrder()
                    ->limit($free)
                    ->pluck('cards.id')
                    ->all();
            }

            // 3. Rather than no session at all (which would also stop postponed cards from
            //    ever being released), bring back the learned cards closest to release.
            if ($active === [] && $pool === []) {
                $pool = $this->candidates($user, $fromId, $toId)
                    ->where('lc.retry', 0)
                    ->orderBy('lc.learned_session')
                    ->orderBy('cards.complexity')
                    ->inRandomOrder()
                    ->limit($size)
                    ->pluck('cards.id')
                    ->all();
            }

            if ($active === [] && $pool === []) {
                return null;
            }

            foreach ($pool as $cardId) {
                LearnerCard::updateOrCreate(
                    ['user_id' => $user->id, 'card_id' => $cardId, 'from_lang_id' => $fromId],
                    ['retry' => 1, 'learned_session' => null],
                );
            }

            $session = StudySession::create([
                'user_id' => $user->id,
                'from_lang_id' => $fromId,
                'to_lang_id' => $toId,
                'seq' => $seq,
                'status' => StudySessionStatus::Open,
            ]);

            // 4. Shuffle to form the queue.
            $cardIds = [...$active, ...$pool];
            shuffle($cardIds);

            $session->cards()->createMany(array_map(
                fn (int $cardId, int $index) => ['card_id' => $cardId, 'position' => $index + 1],
                $cardIds,
                array_keys($cardIds),
            ));

            return $session;
        });
    }

    /**
     * Cards of the language pair joined with this learner's progress for the direction (alias "lc").
     *
     * @return Builder<Card>
     */
    private function candidates(User $user, int $fromId, int $toId): Builder
    {
        return Card::query()
            ->forLanguagePair($fromId, $toId)
            ->leftJoin('learner_cards as lc', function (JoinClause $join) use ($user, $fromId) {
                $join->on('lc.card_id', '=', 'cards.id')
                    ->where('lc.user_id', '=', $user->id)
                    ->where('lc.from_lang_id', '=', $fromId);
            });
    }
}
