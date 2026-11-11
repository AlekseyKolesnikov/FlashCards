<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Language;
use App\Services\Vocabulary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Language list (SPECIFICATION.md §4.2).
 */
class LanguageController extends Controller
{
    /**
     * Languages with their card counts per complexity. A card counts once for each of its two languages.
     */
    public function index(): JsonResponse
    {
        $languages = Language::query()
            ->addSelect(['cards_count' => Card::selectRaw('COUNT(*)')
                ->where(fn ($q) => $q->whereColumn('lang1_id', 'languages.id')->orWhereColumn('lang2_id', 'languages.id'))])
            ->orderBy('name')
            ->get();

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

        return response()->json([
            'languages' => $languages->map(fn (Language $language) => [
                'id' => $language->id,
                'code' => $language->code,
                'name' => $language->name,
                'cards_count' => (int) $language->cards_count,
                // Objects keyed by complexity, also when empty.
                'by_complexity' => (object) ($byLanguage[$language->id] ?? []),
            ]),
            'complexities' => array_keys($totals),
            'totals' => (object) $totals,
        ]);
    }

    public function show(Language $language): JsonResponse
    {
        return response()->json(['language' => $language->only('id', 'code', 'name')]);
    }

    public function store(Request $request): JsonResponse
    {
        $language = Language::create($this->validated($request));

        return response()->json([
            'language' => $language->only('id', 'code', 'name'),
            'message' => "Added {$language->name}.",
        ], 201);
    }

    public function update(Request $request, Language $language): JsonResponse
    {
        $language->update($this->validated($request, $language));

        return response()->json([
            'language' => $language->only('id', 'code', 'name'),
            'message' => "Saved {$language->name}.",
        ]);
    }

    public function destroy(Language $language, Vocabulary $vocabulary): JsonResponse
    {
        if ($language->isInUse()) {
            return response()->json(['message' => "{$language->name} is used by cards and cannot be deleted."], 422);
        }

        $vocabulary->deleteLanguage($language);

        return response()->json(['message' => "Deleted {$language->name}."]);
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
