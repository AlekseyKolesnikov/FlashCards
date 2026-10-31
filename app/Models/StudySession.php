<?php

namespace App\Models;

use App\Enums\StudySessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudySession extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'from_lang_id',
        'to_lang_id',
        'seq',
        'status',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StudySessionStatus::class,
            'finished_at' => 'datetime',
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
     * @return BelongsTo<Language, $this>
     */
    public function fromLang(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'from_lang_id');
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function toLang(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'to_lang_id');
    }

    /**
     * @return HasMany<StudySessionCard, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(StudySessionCard::class);
    }
}
