<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscussionReaction extends Model
{
    use HasUuids;

    /**
     * Reaction types supported by the public discussion API.
     *
     * @var list<string>
     */
    public const ALLOWED_TYPES = [
        'like',
        'insightful',
        'agree',
        'support',
        'celebrate',
    ];

    protected $fillable = [
        'post_id',
        'participant_id',
        'type',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(DiscussionPost::class, 'post_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(DiscussionParticipant::class, 'participant_id');
    }
}
