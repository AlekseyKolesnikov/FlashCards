<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CardRequest;
use App\Models\Card;
use App\Models\Language;
use App\Models\StudySession;
use App\Services\Vocabulary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Vocabulary management (SPECIFICATION.md §5.1–5.3).
 */
class CardController extends Controller
{
    private const SORTS = ['id', 'lang1', 'word1', 'lang2', 'word2', 'complexity'];

    public function __construct(private Vocabulary $vocabulary) {}

    public function index(Request $request): View
    {
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'id';
        $direction = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        $pair = $this->vocabulary->parsePairKey($request->query('pair'));
        $complexity = $request->integer('complexity') ?: null;
        $search = trim((string) $request->query('q'));
        // Notes often hold alternative translations, so they can be searched too.
        $searchNotes = $request->boolean('notes');
        $searchColumns = $searchNotes ? ['word1', 'word2', 'notes1', 'notes2'] : ['word1', 'word2'];

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

        // Edit and delete return to this list with its filters.
        $request->session()->put('cards.list_url', $request->fullUrl());

        return view('admin.cards.index', [
            'cards' => $query->orderBy('id', $direction)->paginate(50)->withQueryString(),
            'pairs' => $this->vocabulary->languagePairs(),
            'filters' => ['pair' => $request->query('pair'), 'complexity' => $complexity, 'q' => $search, 'notes' => $searchNotes],
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function create(Request $request): View
    {
        // "Save and add another" passes the previous card's languages and complexity.
        $card = new Card([
            'lang1_id' => $request->integer('lang1_id') ?: null,
            'lang2_id' => $request->integer('lang2_id') ?: null,
            'complexity' => $request->integer('complexity') ?: config('flashcards.complexity.default'),
        ]);

        return view('admin.cards.form', ['card' => $card, 'languages' => Language::orderBy('name')->get()]);
    }

    public function store(CardRequest $request): RedirectResponse
    {
        $card = Card::create($request->validated());

        $this->flashSharedWordWarning($card);

        if ($request->boolean('another')) {
            return redirect()
                ->route('admin.cards.create', $card->only('lang1_id', 'lang2_id', 'complexity'))
                ->with('status', "Saved “{$card->word1}” – “{$card->word2}”.");
        }

        return $this->backToList()->with('status', 'Card saved.');
    }

    public function edit(Request $request, Card $card): View
    {
        // Opened from the admin's own learn result screen: save and cancel return there.
        $session = StudySession::find($request->integer('session'));
        if ($session?->user_id === $request->user()->id) {
            $request->session()->put('cards.list_url', route('learn.result', $session));
        }

        return view('admin.cards.form', ['card' => $card, 'languages' => Language::orderBy('name')->get()]);
    }

    public function update(CardRequest $request, Card $card): RedirectResponse
    {
        $this->vocabulary->update($card, $request->validated());

        $this->flashSharedWordWarning($card);

        return $this->backToList()->with('status', 'Card saved.');
    }

    public function destroy(Card $card): RedirectResponse
    {
        $this->vocabulary->delete([$card->id]);

        return $this->backToList()->with('status', "Deleted “{$card->word1}” – “{$card->word2}”.");
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ])['ids'];

        $deleted = $this->vocabulary->delete($ids);

        return $this->backToList()->with('status', "Deleted {$deleted} card(s).");
    }

    public function updateMany(Request $request): RedirectResponse
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

        return $this->backToList()->with('status', "Set complexity {$data['complexity']} for {$count} card(s).");
    }

    private function backToList(): RedirectResponse
    {
        return redirect(session('cards.list_url', route('admin.cards.index')));
    }

    /**
     * Warn when learners would see this card's prompt with a different expected answer (§4.3 rule 2).
     */
    private function flashSharedWordWarning(Card $card): void
    {
        $others = $this->vocabulary->cardsSharingAWord($card);

        if ($others->isEmpty()) {
            return;
        }

        $list = $others->map(fn (Card $other) => "“{$other->word1}” – “{$other->word2}”")->implode(', ');

        session()->flash('warning', "These cards share a word with this one: {$list}. "
            .'Learners will see the same word with a different expected answer, '
            .'so use the notes or examples to tell them apart.');
    }
}
