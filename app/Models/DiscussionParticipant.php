<?php

namespace App\Models;

use App\Support\DiscussionAccountEmailPolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;

class DiscussionParticipant extends Model
{
    use HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'display_name',
        'email',
        'password',
        'country',
        'organization',
        'bio',
        'status',
        'terms_accepted_at',
        'last_login_at',
        'last_seen_at',
        'blocked_at',
        'blocked_reason',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'terms_accepted_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'blocked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (DiscussionParticipant $participant): void {
            if (! $participant->isDirty('email')) {
                return;
            }

            $participant->email = DiscussionAccountEmailPolicy::normalize($participant->email);

            if (DiscussionAccountEmailPolicy::unavailableForParticipant(
                $participant->email,
                $participant->exists ? (string) $participant->getKey() : null
            )) {
                throw ValidationException::withMessages([
                    'email' => [DiscussionAccountEmailPolicy::UNAVAILABLE_MESSAGE],
                ]);
            }
        });
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(DiscussionParticipantToken::class, 'participant_id');
    }

    public function passwordReset(): HasOne
    {
        return $this->hasOne(DiscussionParticipantPasswordReset::class, 'participant_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(DiscussionPost::class, 'participant_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(DiscussionReaction::class, 'participant_id');
    }

    public function topicParticipations(): HasMany
    {
        return $this->hasMany(DiscussionTopicParticipant::class, 'participant_id');
    }

    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }
}
