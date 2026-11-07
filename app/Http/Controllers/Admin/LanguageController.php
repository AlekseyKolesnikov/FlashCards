<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Language;
use App\Services\Vocabulary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Language list (SPECIFICATION.md §4.2).
 */
class LanguageController extends Controller
{
    public function index(): View
    {
        $languages = Language::query()
            ->addSelect(['cards_count' => Card::selectRaw('COUNT(*)')
                ->where(fn ($q) => $q->whereColumn('lang1_id', 'languages.id')->orWhereColumn('lang2_id', 'languages.id'))])
            ->orderBy('name')
            ->get();

        return view('admin.languages.index', ['languages' => $languages] + $this->complexityStats());
    }

    /**
     * Card counts per language and complexity. A card counts once for each of its two languages.
     *
     * @return array{complexities: list<int>, byLanguage: array<int, array<int, int>>, totals: array<int, int>}
     */
    private function complexityStats(): array
    {
        $side = fn (string $column) => Card::query()
            ->selectRaw("{$column} as language_id, complexity, COUNT(*) as n")
            ->groupBy($column, 'complexity');

        $byLanguage = [];
        foreach ($side('lang1_id')->unionAll($side('lang2_id'))->get() as $row) {
            $byLanguage[$row->language_id][$row->complexity] = ($byLanguage[$row->language_id][$row->complexity] ?? 0) + $row->n;
        }

        $totals = Card::query()
            ->selectRaw('complexity, COUNT(*) as n')
            ->groupBy('complexity')
            ->orderBy('complexity')
            ->pluck('n', 'complexity')
            ->map(fn ($n) => (int) $n)
            ->all();

        return ['complexities' => array_keys($totals), 'byLanguage' => $byLanguage, 'totals' => $totals];
    }

    public function store(Request $request): RedirectResponse
    {
        $language = Language::create($this->validated($request));

        return redirect()->route('admin.languages.index')->with('status', "Added {$language->name}.");
    }

    public function edit(Language $language): View
    {
        return view('admin.languages.edit', ['language' => $language]);
    }

    public function update(Request $request, Language $language): RedirectResponse
    {
        $language->update($this->validated($request, $language));

        return redirect()->route('admin.languages.index')->with('status', "Saved {$language->name}.");
    }

    public function destroy(Language $language, Vocabulary $vocabulary): RedirectResponse
    {
        if ($language->isInUse()) {
            return back()->with('error', "{$language->name} is used by cards and cannot be deleted.");
        }

        $vocabulary->deleteLanguage($language);

        return redirect()->route('admin.languages.index')->with('status', "Deleted {$language->name}.");
    }

    /**
     * @return array{code: string, name: string}
     */
    private function validated(Request $request, ?Language $language = null): array
    {
        $request->merge(['code' => mb_strtolower(trim((string) $request->input('code')))]);

        return $request->validate([
            'code' => ['required', 'string', 'max:10', 'alpha_dash', Rule::unique('languages', 'code')->ignore($language)],
            'name' => ['required', 'string', 'max:64'],
        ]);
    }
}
