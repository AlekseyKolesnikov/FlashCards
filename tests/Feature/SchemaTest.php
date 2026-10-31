<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_stores_normalized_words(): void
    {
        $card = Card::factory()->create(['word1' => '  Der  Hund. ', 'word2' => 'The Dog']);

        $this->assertSame('der hund', $card->fresh()->word1_norm);
        $this->assertSame('the dog', $card->fresh()->word2_norm);
    }

    public function test_normalized_words_compare_accents_exactly(): void
    {
        $fr = Language::factory()->create();
        $en = Language::factory()->create();
        Card::factory()->create(['lang1_id' => $fr->id, 'word1' => 'café', 'lang2_id' => $en->id, 'word2' => 'coffee']);

        // The default collation would treat "cafe" and "café" as equal; *_norm must not.
        $this->assertSame(0, Card::where('word1_norm', 'cafe')->count());
        $this->assertSame(1, Card::where('word1_norm', 'café')->count());
    }

    public function test_language_pair_scope_matches_either_order(): void
    {
        [$en, $de, $fr] = Language::factory()->count(3)->create();
        Card::factory()->create(['lang1_id' => $en->id, 'lang2_id' => $de->id]);
        Card::factory()->create(['lang1_id' => $de->id, 'lang2_id' => $en->id]);
        Card::factory()->create(['lang1_id' => $en->id, 'lang2_id' => $fr->id]);

        $this->assertSame(2, Card::forLanguagePair($en->id, $de->id)->count());
        $this->assertTrue($de->isInUse());
    }
}
