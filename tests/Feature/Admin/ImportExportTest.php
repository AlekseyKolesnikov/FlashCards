<?php

namespace Tests\Feature\Admin;

use App\Models\Card;
use App\Models\Language;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Language $en;

    private Language $de;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->admin = User::factory()->admin()->create();
        $this->en = Language::factory()->create(['code' => 'en']);
        $this->de = Language::factory()->create(['code' => 'de']);
        $this->actingAs($this->admin);
    }

    private function uploadCsv(string $content): void
    {
        $this->post('/admin/import/csv', ['file' => UploadedFile::fake()->createWithContent('cards.csv', $content)])
            ->assertRedirect('/admin/import/preview');
    }

    public function test_csv_import_previews_then_imports(): void
    {
        Card::factory()->create(['lang1_id' => $this->de->id, 'word1' => 'die Katze', 'lang2_id' => $this->en->id, 'word2' => 'cat']);

        // Excel style: BOM, semicolons, columns in another order, a multi-line quoted field.
        $csv = "\xEF\xBB\xBFword1;lang1;word2;lang2;examples2;complexity;comment\r\n"
            ."dog;EN;der Hund;de;\"Der Hund bellt.\r\nGuter Hund!\";2;ignored\r\n"
            ."cat;en;die Katze;de;;;\r\n"          // duplicate of the existing card (other language order)
            ."dog;en;der Hund;de;;;\r\n"           // duplicate of row 2
            ."horse;xx;das Pferd;de;;;\r\n"        // unknown language
            ."bird;en;;de;;11;\r\n"                // missing word, bad complexity
            ."\r\n"
            ."mouse;en;die Maus;de;;;\r\n";

        $this->uploadCsv($csv);

        $this->get('/admin/import/preview')
            ->assertOk()
            ->assertSee('Import 2 card(s)')
            ->assertSee('Unknown language code "xx".')
            ->assertSeeInOrder(['6', 'Word 2 is missing.', 'Complexity must be a whole number from 1 to 10.']);
        $this->assertSame(1, Card::count());

        $this->post('/admin/import')
            ->assertRedirect('/admin/cards')
            ->assertSessionHas('status', 'Imported 2 card(s). Skipped 2 duplicate(s) and 2 row(s) with errors.');

        $dog = Card::where('word1', 'dog')->sole();
        $this->assertSame("Der Hund bellt.\nGuter Hund!", str_replace("\r\n", "\n", $dog->examples2));
        $this->assertSame(2, $dog->complexity);
        $this->assertSame('der hund', $dog->word2_norm);
        $this->assertSame(5, Card::where('word1', 'mouse')->sole()->complexity);
        $this->assertSame([], Storage::disk('local')->allFiles('imports'));
    }

    public function test_import_can_update_existing_cards(): void
    {
        $dog = Card::factory()->create([
            'lang1_id' => $this->de->id, 'word1' => 'der Hund', 'notes1' => 'animal', 'examples1' => 'Der Hund bellt.',
            'lang2_id' => $this->en->id, 'word2' => 'dog', 'notes2' => null, 'complexity' => 5,
        ]);
        $cat = Card::factory()->create([
            'lang1_id' => $this->en->id, 'word1' => 'cat', 'lang2_id' => $this->de->id, 'word2' => 'die Katze', 'complexity' => 7,
        ]);

        // Other language order than the saved dog card, and no examples column.
        $csv = "lang1,word1,notes1,lang2,word2,notes2,complexity\n"
            ."en,Dog,,de,der Hund,noun,2\n"         // complexity 5 → 2, notes1 animal → noun
            ."en,dog,,de,der Hund,other,3\n"        // same card again: a plain duplicate
            ."en,cat,{$cat->notes1},de,die Katze,{$cat->notes2},\n"  // empty complexity keeps 7: no change
            ."en,mouse,,de,die Maus,,\n";

        $this->uploadCsv($csv);
        $this->get('/admin/import/preview')
            ->assertOk()
            ->assertSee('1</strong> existing card(s) differ', false)
            ->assertSeeInOrder(['Changes to existing cards', 'der Hund', 'Dog', 'Complexity: 5 → 2', 'Notes 1: animal → noun'])
            ->assertSee('Import 1 card(s) and update 1 existing card(s)');

        // Plain import leaves existing cards alone.
        $this->post('/admin/import')
            ->assertSessionHas('status', 'Imported 1 card(s). Skipped 3 duplicate(s) and 0 row(s) with errors.');
        $this->assertSame(5, $dog->fresh()->complexity);

        Card::where('word1', 'mouse')->delete();
        $this->uploadCsv($csv);
        $this->post('/admin/import', ['update' => 1])
            ->assertSessionHas('status', 'Imported 1 card(s), updated 1 existing card(s). Skipped 2 duplicate(s) and 0 row(s) with errors.');

        $dog->refresh();
        $this->assertSame([2, 'noun', null, 'Der Hund bellt.', 'der Hund', 'dog'], [$dog->complexity, $dog->notes1, $dog->notes2, $dog->examples1, $dog->word1, $dog->word2]);
        $this->assertSame(7, $cat->fresh()->complexity);
    }

    public function test_line_break_style_is_not_a_change(): void
    {
        Card::factory()->create([
            'lang1_id' => $this->en->id, 'word1' => 'dog', 'examples1' => "One.\r\nTwo.",
            'lang2_id' => $this->de->id, 'word2' => 'der Hund', 'notes1' => null, 'notes2' => null, 'examples2' => null, 'complexity' => 4,
        ]);

        $this->uploadCsv("lang1,word1,examples1,lang2,word2,complexity\nen,dog,\"One.\nTwo.\",de,der Hund,4\n");

        $this->get('/admin/import/preview')
            ->assertDontSee('Changes to existing cards')
            ->assertSee('<strong>1</strong> duplicate(s)', false);
    }

    public function test_csv_import_rejects_bad_files(): void
    {
        $this->uploadCsv("lang1,word1,word2\nen,dog,der Hund\n");
        $this->get('/admin/import/preview')
            ->assertRedirect('/admin/import')
            ->assertSessionHas('error', 'Missing column(s) in the header row: lang2.');

        $this->uploadCsv(mb_convert_encoding("lang1,word1,lang2,word2\nde,Bär,en,bear\n", 'ISO-8859-1', 'UTF-8'));
        $this->get('/admin/import/preview')
            ->assertSessionHas('error', fn ($error) => str_contains($error, 'not UTF-8'));
    }

    public function test_cancelling_removes_the_uploaded_file(): void
    {
        $this->uploadCsv("lang1,word1,lang2,word2\nen,dog,de,der Hund\n");

        $this->delete('/admin/import')->assertRedirect('/admin/import');

        $this->assertSame([], Storage::disk('local')->allFiles('imports'));
        $this->get('/admin/import/preview')->assertRedirect('/admin/import');
    }

    public function test_anki_import_with_column_mapping(): void
    {
        $txt = "#separator:tab\n#html:true\n#columns:Front\tBack\tExtra\n"
            ."der Hund\tdog\tDer Hund <b>bellt</b>.<br>Guter Hund!\n"
            ."\"die Katze\"\t\"cat\"\t\"Die Katze &amp; die Maus\"\n";

        $this->post('/admin/import/anki', [
            'file' => UploadedFile::fake()->createWithContent('deck.txt', $txt),
            'lang1_id' => $this->de->id, 'lang2_id' => $this->en->id, 'complexity' => 4,
        ])->assertRedirect('/admin/import/mapping');

        $this->get('/admin/import/mapping')->assertOk()->assertSee('Front')->assertSee('der Hund');

        // Each field once, word 1 and word 2 required.
        $this->post('/admin/import/mapping', ['mapping' => ['word1', 'word1', 'ignore']])->assertSessionHasErrors('mapping');
        $this->post('/admin/import/mapping', ['mapping' => ['word1', 'ignore', 'ignore']])->assertSessionHasErrors('mapping');

        $this->post('/admin/import/mapping', ['mapping' => ['word1', 'word2', 'examples1']])
            ->assertRedirect('/admin/import/preview');
        $this->get('/admin/import/preview')->assertSee('Import 2 card(s)');
        $this->post('/admin/import')->assertRedirect('/admin/cards');

        $dog = Card::where('word2', 'dog')->sole();
        $this->assertSame($this->de->id, $dog->lang1_id);
        $this->assertSame("Der Hund bellt.\nGuter Hund!", $dog->examples1);
        $this->assertSame(4, $dog->complexity);
        $this->assertSame('Die Katze & die Maus', Card::where('word2', 'cat')->sole()->examples1);
    }

    public function test_csv_export_can_be_imported_again(): void
    {
        Card::factory()->create([
            'lang1_id' => $this->en->id, 'word1' => 'dog', 'notes1' => 'animal',
            'lang2_id' => $this->de->id, 'word2' => 'der Hund', 'examples2' => "Der Hund, \"Bello\", bellt.\nJa.",
            'complexity' => 7,
        ]);
        $before = Card::sole()->only('lang1_id', 'word1', 'notes1', 'examples1', 'lang2_id', 'word2', 'notes2', 'examples2', 'complexity');

        $csv = $this->get('/admin/export?format=csv')->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFlang1,word1,notes1,examples1,lang2,word2,notes2,examples2,complexity", $csv);

        Card::query()->delete();
        $this->uploadCsv($csv);
        $this->post('/admin/import');

        $after = Card::sole()->only(array_keys($before));
        $after['examples2'] = str_replace("\r\n", "\n", $after['examples2']);
        $this->assertSame($before, $after);
    }

    public function test_export_can_be_limited_to_a_language_pair(): void
    {
        $fr = Language::factory()->create(['code' => 'fr']);
        Card::factory()->create(['lang1_id' => $this->en->id, 'word1' => 'dog', 'lang2_id' => $this->de->id]);
        Card::factory()->create(['lang1_id' => $fr->id, 'word1' => 'chien', 'lang2_id' => $this->en->id]);

        $pair = min($this->en->id, $fr->id).'-'.max($this->en->id, $fr->id);
        $csv = $this->get("/admin/export?format=csv&pair={$pair}")->streamedContent();

        $this->assertStringContainsString('chien', $csv);
        $this->assertStringNotContainsString('dog', $csv);
    }

    public function test_anki_export(): void
    {
        Card::factory()->create([
            'lang1_id' => $this->de->id, 'word1' => 'der Hund', 'examples1' => "Er sagt <hallo>.\nJa.",
            'lang2_id' => $this->en->id, 'word2' => 'dog', 'complexity' => 3,
        ]);

        $txt = $this->get('/admin/export?format=anki')->assertOk()->streamedContent();

        $this->assertStringStartsWith("#separator:Tab\n#html:true\n#columns:Word 1\tWord 2\t", $txt);
        $this->assertStringContainsString("der Hund\tdog\t\t\tEr sagt &lt;hallo&gt;.<br>Ja.\t\tcomplexity_3\n", $txt);
    }
}
