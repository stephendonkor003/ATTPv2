<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscussionTopicParticipant extends Model
{
    use HasUuids;

    protected $fillable = [
        'topic_id',
        'participant_id',
        'joined_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(DiscussionTopic::class, 'topic_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(DiscussionParticipant::class, 'participant_id');
    }
}
