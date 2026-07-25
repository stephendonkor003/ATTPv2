<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeReportingNotificationLog extends BaseModel
{
    protected $table = 'me_reporting_notification_logs';

    protected $fillable = [
        'user_id', 'event_key', 'subject_type', 'subject_id', 'notification_date',
    ];

    protected $casts = ['notification_date' => 'date'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
