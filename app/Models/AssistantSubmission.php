<?php

namespace App\Models;

class AssistantSubmission extends BaseModel
{
    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array', 'summary' => 'array', 'documents' => 'array',
        'published_ids' => 'array', 'notification_recipients' => 'array', 'reviewed_at' => 'datetime',
    ];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
