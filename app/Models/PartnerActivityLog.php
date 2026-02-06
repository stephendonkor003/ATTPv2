<?php

namespace App\Models;

use App\Models\BaseModel;

class PartnerActivityLog extends BaseModel
{
    protected $table = 'myb_partner_activity_log';

    /**
     * Disable automatic timestamps since we only use created_at
     */
    public $timestamps = false;

    protected $fillable = [
        'funder_id',
        'user_id',
        'action',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /* ==========================
     * RELATIONSHIPS
     * ========================== */

    public function funder()
    {
        return $this->belongsTo(Funder::class, 'funder_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /* ==========================
     * HELPER METHODS
     * ========================== */

    /**
     * Get a human-readable action description
     */
    public function getActionDescription(): string
    {
        return match($this->action) {
            'account_created' => 'Account created',
            'login' => 'Logged in',
            'view_dashboard' => 'Viewed dashboard',
            'view_programs' => 'Viewed programs list',
            'view_program' => 'Viewed program details',
            'request_info' => 'Created information request',
            'download_document' => 'Downloaded document',
            'view_request' => 'Viewed information request',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }

    /**
     * Create a new activity log entry
     */
    public static function logActivity(
        string $funderId,
        string $userId,
        string $action,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?array $metadata = null
    ): self {
        return static::create([
            'funder_id' => $funderId,
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => $ipAddress ?? request()->ip(),
            'user_agent' => $userAgent ?? request()->userAgent(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
