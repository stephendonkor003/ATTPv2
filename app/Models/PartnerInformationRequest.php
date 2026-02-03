<?php

namespace App\Models;

use App\Models\BaseModel;

class PartnerInformationRequest extends BaseModel
{
    protected $table = 'myb_partner_information_requests';

    protected $fillable = [
        'funder_id',
        'program_funding_id',
        'requested_by',
        'request_type',
        'subject',
        'message',
        'status',
        'response',
        'responded_by',
        'responded_at',
        'priority',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    /* ==========================
     * RELATIONSHIPS
     * ========================== */

    public function funder()
    {
        return $this->belongsTo(Funder::class, 'funder_id');
    }

    public function programFunding()
    {
        return $this->belongsTo(ProgramFunding::class, 'program_funding_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function responder()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    /* ==========================
     * HELPER METHODS
     * ========================== */

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function hasResponse(): bool
    {
        return !empty($this->response);
    }

    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            'pending' => 'bg-warning text-dark',
            'in_progress' => 'bg-info',
            'completed' => 'bg-success',
            'rejected' => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    public function getPriorityBadgeClass(): string
    {
        return match($this->priority) {
            'urgent' => 'bg-danger',
            'high' => 'bg-warning text-dark',
            'normal' => 'bg-info',
            'low' => 'bg-secondary',
            default => 'bg-secondary',
        };
    }
}
