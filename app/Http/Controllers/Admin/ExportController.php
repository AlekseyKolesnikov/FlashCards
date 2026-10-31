<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Language;
use App\Services\Import\CsvReader;
use App\Services\Vocabulary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV and Anki export (SPECIFICATION.md §5.4–5.5).
 */
class ExportController extends Controller
{
    public function __invoke(Request $request, Vocabulary $vocabulary): StreamedResponse
    {
        $format = $request->validate(['format' => ['required', 'in:csv,anki']])['format'];
        $pair = $vocabulary->parsePairKey($request->query('pair'));

        $codes = Language::pluck('code', 'id');
        $cards = Card::query()
            ->when($pair, fn (Builder $q) => $q->forLanguagePair(...$pair))
            ->lazyById(500);

        $name = 'flashcards-'.($pair ? $codes[$pair[0]].'-'.$codes[$pair[1]].'-' : '').now()->format('Y-m-d');

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($cards, $codes) {
                $out = fopen('php://output', 'w');
                // BOM, so Excel shows non-Latin text correctly.
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, CsvReader::COLUMNS, ',', '"', '', "\r\n");

                foreach ($cards as $card) {
                    fputcsv($out, [
                        $codes[$card->lang1_id], $card->word1, $card->notes1, $card->examples1,
                        $codes[$card->lang2_id], $card->word2, $card->notes2, $card->examples2,
                        $card->complexity,
                    ], ',', '"', '', "\r\n");
                }

                fclose($out);
            }, "{$name}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($cards) {
            $out = fopen('php://output', 'w');
            fwrite($out, "#separator:Tab\n#html:true\n");
            fwrite($out, "#columns:Word 1\tWord 2\tNotes 1\tNotes 2\tExamples 1\tExamples 2\tTags\n");
            fwrite($out, "#tags column:7\n");

            // Escaped HTML has no quotes or line breaks left, so fields need no quoting.
            $html = fn (?string $text) => str_replace(["\r\n", "\n", "\t"], ['<br>', '<br>', ' '], e((string) $text));

            foreach ($cards as $card) {
                fwrite($out, implode("\t", [
                    $html($card->word1), $html($card->word2),
                    $html($card->notes1), $html($card->notes2),
                    $html($card->examples1), $html($card->examples2),
                    "complexity_{$card->complexity}",
                ])."\n");
            }

            fclose($out);
        }, "{$name}.txt", ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
