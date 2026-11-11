<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CardRequest;
use App\Models\Card;
use App\Models\Language;
use App\Services\Vocabulary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vocabulary management (SPECIFICATION.md §5.1–5.3).
 */
class CardController extends Controller
{
    private const SORTS = ['id', 'lang1', 'word1', 'lang2', 'word2', 'complexity'];

    public function __construct(private Vocabulary $vocabulary) {}

    public function index(Request $request): JsonResponse
    {
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'id';
        $direction = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        $pair = $this->vocabulary->parsePairKey($request->query('pair'));
        $complexity = $request->integer('complexity') ?: null;
        $search = trim((string) $request->query('q'));
        // Notes often hold alternative translations, so they can be searched too.
        $searchColumns = $request->boolean('notes') ? ['word1', 'word2', 'notes1', 'notes2'] : ['word1', 'word2'];

        $query = Card::with('lang1', 'lang2')
            ->when($pair, fn (Builder $q) => $q->forLanguagePair(...$pair))
            ->when($complexity, fn (Builder $q) => $q->where('complexity', $complexity))
            ->when($search !== '', function (Builder $q) use ($search, $searchColumns) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->whereAny($searchColumns, 'like', $like);
            });

        if (in_array($sort, ['lang1', 'lang2'], true)) {
            $query->orderBy(Language::select('code')->whereColumn('languages.id', "cards.{$sort}_id"), $direction);
        } else {
            $query->orderBy($sort, $direction);
        }

        $cards = $query->orderBy('id', $direction)->paginate(50);

        return response()->json([
            'cards' => $cards->getCollection()->map(fn (Card $card) => [
                'id' => $card->id,
                'lang1' => $card->lang1->only('code', 'name'),
                'word1' => $card->word1,
                'lang2' => $card->lang2->only('code', 'name'),
                'word2' => $card->word2,
                'complexity' => $card->complexity,
            ]),
            'total' => $cards->total(),
            'page' => $cards->currentPage(),
            'last_page' => $cards->lastPage(),
            'pairs' => $this->vocabulary->languagePairs(),
        ]);
    }

    public function show(Card $card): JsonResponse
    {
        return response()->json(['card' => $card->toApi()]);
    }

    public function store(CardRequest $request): JsonResponse
    {
        $card = Card::create($request->validated());

        return response()->json([
            'card' => $card->toApi(),
            'message' => "Saved “{$card->word1}” – “{$card->word2}”.",
            'warning' => $this->sharedWordWarning($card),
        ], 201);
    }

    public function update(CardRequest $request, Card $card): JsonResponse
    {
        $this->vocabulary->update($card, $request->validated());

        return response()->json([
            'card' => $card->toApi(),
            'message' => 'Card saved.',
            'warning' => $this->sharedWordWarning($card),
        ]);
    }

    public function destroy(Card $card): JsonResponse
    {
        $this->vocabulary->delete([$card->id]);

        return response()->json(['message' => "Deleted “{$card->word1}” – “{$card->word2}”."]);
    }

    public function destroyMany(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ])['ids'];

        $deleted = $this->vocabulary->delete($ids);

        return response()->json(['message' => "Deleted {$deleted} card(s)."]);
    }

    public function updateMany(Request $request): JsonResponse
    {
        $range = config('flashcards.complexity');
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'complexity' => ['required', 'integer', "between:{$range['min']},{$range['max']}"],
        ]);

        // Learners keep their progress when complexity changes (§4.3 rule 3).
        $cards = Card::whereKey($data['ids']);
        $count = $cards->count();
        $cards->update(['complexity' => $data['complexity']]);

        return response()->json(['message' => "Set complexity {$data['complexity']} for {$count} card(s)."]);
    }

    /**
     * Warn when learners would see this card's prompt with a different expected answer (§4.3 rule 2).
     */
    private function sharedWordWarning(Card $card): ?string
    {
        $others = $this->vocabulary->cardsSharingAWord($card);

        if ($others->isEmpty()) {
            return null;
        }

        $list = $others->map(fn (Card $other) => "“{$other->word1}” – “{$other->word2}”")->implode(', ');

        return "These cards share a word with this one: {$list}. "
            .'Learners will see the same word with a different expected answer, '
            .'so use the notes or examples to tell them apart.';
    }
}
