<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * An account must be activated through its email address before it can log in (SPECIFICATION.md §9.1).
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable. Role and activation are set explicitly, never from input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    /**
     * What the React app needs to know about the logged-in user.
     *
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role->value,
            'session_size' => $this->sessionSize(),
            'postpone_sessions' => $this->postponeSessions(),
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
