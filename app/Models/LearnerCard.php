<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A learner's progress for one card in one direction (SPECIFICATION.md §8.1).
 */
class LearnerCard extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'card_id',
        'from_lang_id',
        'retry',
        'learned_session',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'retry' => 'integer',
            'learned_session' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Card, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function fromLang(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'from_lang_id');
    }
}
