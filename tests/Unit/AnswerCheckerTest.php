<?php

namespace Tests\Unit;

use App\Services\AnswerChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AnswerCheckerTest extends TestCase
{
    /**
     * The examples from SPECIFICATION.md §7.2, plus a few Unicode cases from §7.1.
     *
     * @return array<string, array{string, string, bool}>
     */
    public static function answers(): array
    {
        return [
            'case and extra spaces' => ['der Hund', '  Der   Hund ', true],
            'dot dropped' => ['der Hund', 'der Hund.', true],
            'comma dropped' => ['der Hund, die Hunde', 'der Hund die Hunde', true],
            'other punctuation kept' => ['der Hund', 'der Hund!', false],
            'space between words required' => ['der Hund', 'derHund', false],
            'article missing' => ['der Hund', 'Hund', false],
            'accent missing' => ['café', 'cafe', false],
            'sharp s is not ss' => ['Straße', 'strasse', false],
            '"to" missing' => ['to go', 'go', false],
            'spelling variant' => ['colour', 'color', false],
            'apostrophe must match' => ["l'eau", 'leau', false],
            'typographic apostrophe differs' => ['l’eau', "l'eau", false],
            'hyphen must match' => ['e-mail', 'email', false],
            'combining accent equals precomposed' => ["caf\u{00E9}", "cafe\u{0301}", true],
            'unicode upper case' => ['äpfel', 'ÄPFEL', true],
            'capital sharp s' => ['straße', "STRA\u{1E9E}E", true],
            'non-breaking space' => ['der Hund', "der\u{00A0}Hund", true],
            'cyrillic case' => ['собака', 'Собака', true],
        ];
    }

    #[DataProvider('answers')]
    public function test_answer_matching(string $expected, string $typed, bool $correct): void
    {
        $this->assertSame($correct, (new AnswerChecker)->matches($typed, $expected));
    }

    /**
     * Russian ё and е are interchangeable in answers, in both directions (§7.1).
     *
     * @return array<string, array{string, string, ?string, bool}>
     */
    public static function yoAnswers(): array
    {
        return [
            'е typed for stored ё' => ['ёлка', 'елка', 'ru', true],
            'ё typed for stored е' => ['все', 'всё', 'ru', true],
            'capital Ё typed for stored е' => ['ель', 'Ёль', 'ru', true],
            'language code case ignored' => ['ёж', 'еж', 'RU', true],
            'other letters still compared' => ['ёлка', 'ёлки', 'ru', false],
            'not for other languages' => ['ёлка', 'елка', 'uk', false],
            'not without a language' => ['ёлка', 'елка', null, false],
        ];
    }

    #[DataProvider('yoAnswers')]
    public function test_russian_yo(string $expected, string $typed, ?string $lang, bool $correct): void
    {
        $this->assertSame($correct, (new AnswerChecker)->matches($typed, $expected, $lang));
    }

    public function test_yo_still_distinguishes_duplicates(): void
    {
        $checker = new AnswerChecker;

        $this->assertNotSame($checker->normalize('всё'), $checker->normalize('все'));
    }
}
