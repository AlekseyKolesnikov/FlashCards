<?php

namespace Tests\Feature\Learn;

use App\Mail\CardReported;
use App\Models\Card;
use App\Models\Language;
use App\Models\StudySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * Reporting a wrong card from the result screen (SPECIFICATION.md §6.3).
 */
class ReportCardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $learner;

    private Card $card;

    private StudySession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['email' => 'first-admin@example.com']);
        User::factory()->admin()->create(['email' => 'second-admin@example.com']);
        $this->learner = User::factory()->create(['username' => 'anna', 'email' => 'anna@example.com']);

        $en = Language::factory()->create(['code' => 'en', 'name' => 'English']);
        $de = Language::factory()->create(['code' => 'de', 'name' => 'German']);
        $this->card = Card::factory()->create(['lang1_id' => $en->id, 'word1' => 'dog', 'lang2_id' => $de->id, 'word2' => 'die Hund']);

        $this->actingAs($this->learner);
        $this->postJson('/api/learn', ['from_lang_id' => $de->id, 'to_lang_id' => $en->id])->assertOk();
        $this->session = StudySession::sole();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function report(array $data): TestResponse
    {
        return $this->postJson("/api/learn/sessions/{$this->session->id}/report", ['card_id' => $this->card->id, ...$data]);
    }

    public function test_report_is_emailed_to_the_first_admin(): void
    {
        Mail::fake();

        $this->report(['correction' => 'der Hund', 'comment' => 'Hund is masculine & so is the article.'])
            ->assertOk()
            ->assertJsonPath('message', 'Thank you. The report was sent to the admin.');

        Mail::assertSent(CardReported::class, 1);
        Mail::assertSent(CardReported::class, function (CardReported $mail) {
            $text = $mail->render();

            return $mail->hasTo('first-admin@example.com')
                && $mail->hasReplyTo('anna@example.com')
                && $mail->hasSubject('Card reported: die Hund')
                && str_contains($text, 'anna reported a mistake in a card while learning German → English.')
                && str_contains($text, url("/app/admin/cards/{$this->card->id}"))
                && str_contains($text, 'Suggested translation: der Hund')
                // Plain text: nothing is HTML-escaped.
                && str_contains($text, 'Hund is masculine & so is the article.');
        });
    }

    public function test_either_a_correction_or_a_comment_is_enough(): void
    {
        Mail::fake();

        $this->report(['correction' => 'der Hund'])->assertOk();
        $this->report(['comment' => 'Wrong article.'])->assertOk();

        Mail::assertSent(CardReported::class, fn (CardReported $mail) => $mail->correction === 'der Hund' && $mail->comment === null);
        Mail::assertSent(CardReported::class, fn (CardReported $mail) => $mail->correction === null && $mail->comment === 'Wrong article.');
    }

    public function test_correction_or_comment_is_required(): void
    {
        Mail::fake();

        $this->report(['correction' => '', 'comment' => ' '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['correction' => 'Give the correct translation, a comment, or both.']);

        Mail::assertNothingSent();
    }

    public function test_only_cards_of_the_own_session_can_be_reported(): void
    {
        Mail::fake();
        $other = Card::factory()->create();

        $this->report(['card_id' => $other->id, 'comment' => 'x'])->assertNotFound();

        $this->actingAs(User::factory()->create());
        $this->report(['comment' => 'x'])->assertNotFound();

        Mail::assertNothingSent();
    }

    public function test_admin_without_email_is_skipped(): void
    {
        Mail::fake();
        $this->admin->forceFill(['email' => null])->save();

        $this->report(['comment' => 'x'])->assertOk();

        Mail::assertSent(CardReported::class, fn (CardReported $mail) => $mail->hasTo('second-admin@example.com'));
    }

    public function test_failed_email_gives_a_message(): void
    {
        Mail::shouldReceive('to->send')->andThrow(new RuntimeException('SMTP down'));

        $this->report(['comment' => 'x'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'The report could not be sent. Try again later.');
    }

    public function test_guests_cannot_report(): void
    {
        auth()->logout();

        $this->report(['comment' => 'x'])->assertUnauthorized();
    }
}
