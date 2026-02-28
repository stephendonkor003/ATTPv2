<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberStateCommodityTrend extends BaseModel
{
    use HasFactory;

    protected $table = 'myb_member_state_commodity_trends';

    protected $fillable = [
        'member_state_id',
        'commodity_id',
        'recorded_on',
        'production_volume',
        'export_volume',
        'export_value_usd',
        'growth_rate_pct',
        'trend_summary',
        'impact_notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'recorded_on' => 'date',
        'production_volume' => 'decimal:3',
        'export_volume' => 'decimal:3',
        'export_value_usd' => 'decimal:2',
        'growth_rate_pct' => 'decimal:3',
    ];

    public function memberState(): BelongsTo
    {
        return $this->belongsTo(AuMemberState::class, 'member_state_id');
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class, 'commodity_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
