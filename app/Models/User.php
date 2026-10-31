<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable. Role is set explicitly, never from input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'password',
        'session_size',
        'postpone_sessions',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isLearner(): bool
    {
        return $this->role === Role::Learner;
    }

    /**
     * Words per session, falling back to the configured default.
     */
    public function sessionSize(): int
    {
        return $this->session_size ?? config('flashcards.session_size.default');
    }

    /**
     * Sessions a learned word is postponed for, falling back to the configured default.
     */
    public function postponeSessions(): int
    {
        return $this->postpone_sessions ?? config('flashcards.postpone_sessions.default');
    }

    /**
     * @return HasMany<LearnerCard, $this>
     */
    public function learnerCards(): HasMany
    {
        return $this->hasMany(LearnerCard::class);
    }

    /**
     * @return HasMany<StudySession, $this>
     */
    public function studySessions(): HasMany
    {
        return $this->hasMany(StudySession::class);
    }
}
