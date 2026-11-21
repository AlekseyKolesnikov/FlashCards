<?php

namespace App\Mail;

use App\Models\Card;
use App\Models\Language;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A learner's report of a wrong card, sent to the first admin (SPECIFICATION.md §6.3).
 */
class CardReported extends Mailable
{
    public function __construct(
        public User $reporter,
        public Card $card,
        public Language $fromLang,
        public Language $toLang,
        public ?string $correction,
        public ?string $comment,
        public string $cardUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Card reported: {$this->card->sideField('word', $this->fromLang->id)}",
            // The admin can answer the learner directly.
            replyTo: $this->reporter->email ? [new Address($this->reporter->email, $this->reporter->username)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.card-reported');
    }
}
