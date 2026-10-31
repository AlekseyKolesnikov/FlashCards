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
}
