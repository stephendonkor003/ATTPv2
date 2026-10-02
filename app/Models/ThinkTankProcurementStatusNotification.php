<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThinkTankProcurementStatusNotification extends BaseModel
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $table = 'attp_think_tank_procurement_status_notifications';

    protected $fillable = [
        'event_id', 'recipient_user_id', 'recipient_email', 'recipient_name',
        'recipient_scope', 'audiences', 'heading', 'message', 'status',
        'attempts', 'sent_at', 'failed_at', 'failure_code',
    ];

    protected $casts = [
        'audiences' => 'array',
        'attempts' => 'integer',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ThinkTankProcurementEvent::class, 'event_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
