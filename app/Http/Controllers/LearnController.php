<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Language;
use App\Models\LearnerCard;
use App\Models\StudySession;
use App\Models\User;
use App\Services\ProgressService;
use App\Services\SessionBuilder;
use App\Services\Vocabulary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Learn mode (SPECIFICATION.md §6).
 */
class LearnController extends Controller
{
    public function __construct(private SessionBuilder $builder, private ProgressService $progress) {}

    /**
     * Start page: every direction that has cards, with the learner's progress in it.
     */
    public function index(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        $user = $request->user();
        $languages = Language::all()->keyBy('id');
        $directions = [];

        foreach ($vocabulary->languagePairs() as $pair) {
            [$a, $b] = $vocabulary->parsePairKey($pair['key']);
            $total = Card::forLanguagePair($a, $b)->count();

            foreach ([[$a, $b], [$b, $a]] as [$from, $to]) {
                $stats = LearnerCard::where('user_id', $user->id)
                    ->where('from_lang_id', $from)
                    ->whereHas('card', fn (Builder $q) => $q->forLanguagePair($from, $to))
                    ->selectRaw('COALESCE(SUM(retry > 0), 0) AS active, COALESCE(SUM(retry = 0), 0) AS learned')
                    ->toBase()
                    ->first();

                $open = $this->builder->openSession($user, $from, $to);

                $directions[] = [
                    'from' => $this->language($languages[$from]),
                    'to' => $this->language($languages[$to]),
                    'new' => $total - $stats->active - $stats->learned,
                    'active' => (int) $stats->active,
                    'learned' => (int) $stats->learned,
                    'cards_left' => $open?->cardsLeft(),
                ];
            }
        }

        usort($directions, fn ($x, $y) => [$x['from']['name'], $x['to']['name']] <=> [$y['from']['name'], $y['to']['name']]);

        return response()->json(['directions' => $directions]);
    }

    /**
     * Resume the open session of a direction, or build a new one.
     */
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_lang_id' => ['required', 'integer', 'exists:languages,id'],
            'to_lang_id' => ['required', 'integer', 'exists:languages,id', 'different:from_lang_id'],
        ]);

        $session = $this->builder->start($request->user(), (int) $data['from_lang_id'], (int) $data['to_lang_id']);

        if (! $session) {
            throw ValidationException::withMessages([
                'direction' => 'There are no cards to learn in this direction right now.',
            ]);
        }

        return response()->json(['session_id' => $session->id]);
    }

    /**
     * The current question, or the summary once the session is finished.
     */
    public function show(Request $request, StudySession $session): JsonResponse
    {
        $this->authorizeSession($request->user(), $session);
        $session->load('fromLang', 'toLang');

        $data = ['session' => [
            'id' => $session->id,
            'seq' => $session->seq,
            'open' => $session->isOpen(),
            'from' => $this->language($session->fromLang),
            'to' => $this->language($session->toLang),
            'cards_left' => $session->cardsLeft(),
        ]];

        if (! $session->isOpen()) {
            $entries = $session->cards()->with('card')->get();

            $data['summary'] = [
                'total' => $entries->count(),
                'first_try' => $entries->where('wrong_count', 0)->count(),
                'mistakes' => $entries->where('wrong_count', '>', 0)->sortByDesc('wrong_count')->values()
                    ->map(fn ($entry) => [
                        'card_id' => $entry->card_id,
                        'word' => $entry->card->sideField('word', $session->from_lang_id),
                        'answer' => $entry->card->sideField('word', $session->to_lang_id),
                        'wrong_count' => $entry->wrong_count,
                    ]),
            ];

            return response()->json($data);
        }

        $card = $session->currentEntry()->card;
        $from = $session->from_lang_id;

        // Only the "from" side goes to the browser before the answer (§6.2).
        $data['question'] = [
            'card_id' => $card->id,
            'word' => $card->sideField('word', $from),
            'notes' => $card->sideField('notes', $from),
            'examples' => $card->sideField('examples', $from),
        ];

        return response()->json($data);
    }

    public function answer(Request $request, StudySession $session): JsonResponse
    {
        $this->authorizeSession($request->user(), $session);

        $data = $request->validate([
            'card_id' => ['required', 'integer'],
            'answer' => ['nullable', 'string', 'max:255'],
            'dont_know' => ['nullable', 'boolean'],
        ]);

        $typed = $request->boolean('dont_know') ? null : (string) ($data['answer'] ?? '');

        if ($typed === '') {
            throw ValidationException::withMessages(['answer' => 'Type your answer, or choose “I don’t know”.']);
        }

        $result = $this->progress->answer($session, (int) $data['card_id'], $typed);

        // A repeated submission changes nothing (§6.6).
        if ($result === null) {
            return response()->json(['message' => 'This answer was already saved.'], 409);
        }

        $card = Card::find($data['card_id']);

        return response()->json(['result' => [
            'card_id' => $card->id,
            'typed' => $typed,
            'correct' => $result['correct'],
            'cards_left' => $result['cards_left'],
            'prompt' => $card->sideField('word', $session->from_lang_id),
            'expected' => $card->sideField('word', $session->to_lang_id),
            'notes' => $card->sideField('notes', $session->to_lang_id),
            'examples' => $card->sideField('examples', $session->to_lang_id),
        ]]);
    }

    /**
     * Learners only ever see their own sessions.
     */
    private function authorizeSession(User $user, StudySession $session): void
    {
        abort_unless($session->user_id === $user->id, 404);
    }

    /**
     * @return array{id: int, code: string, name: string}
     */
    private function language(Language $language): array
    {
        return $language->only('id', 'code', 'name');
    }
}
