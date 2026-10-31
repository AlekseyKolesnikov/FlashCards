<?php

namespace App\Models;

use App\Services\AnswerChecker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Card extends Model
{
    /** @use HasFactory<\Database\Factories\CardFactory> */
    use HasFactory;

    /**
     * The *_norm columns are derived on save and are not fillable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'complexity',
        'lang1_id', 'word1', 'notes1', 'examples1',
        'lang2_id', 'word2', 'notes2', 'examples2',
    ];

    protected static function booted(): void
    {
        static::saving(function (Card $card) {
            $checker = app(AnswerChecker::class);
            $card->word1_norm = $checker->normalize($card->word1);
            $card->word2_norm = $checker->normalize($card->word2);
        });
    }

    /**
     * Which side (1 or 2) holds the given language.
     */
    public function sideOf(int $languageId): int
    {
        return (int) $this->lang1_id === $languageId ? 1 : 2;
    }

    /**
     * The word, notes or examples on the side of the given language.
     */
    public function sideField(string $field, int $languageId): ?string
    {
        return $this->{$field.$this->sideOf($languageId)};
    }

    /**
     * Cards with these two languages, stored in either order.
     *
     * @param  Builder<Card>  $query
     */
    public function scopeForLanguagePair(Builder $query, int $langA, int $langB): void
    {
        $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $q) => $q->where('lang1_id', $langA)->where('lang2_id', $langB))
            ->orWhere(fn (Builder $q) => $q->where('lang1_id', $langB)->where('lang2_id', $langA)));
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function lang1(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'lang1_id');
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function lang2(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'lang2_id');
    }
}
