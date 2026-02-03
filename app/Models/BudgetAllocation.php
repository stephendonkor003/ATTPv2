<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;

class BudgetAllocation extends BaseModel
{
    use HasFactory;

    /**
     * Allow mass assignment for these columns.
     */
    protected $fillable = [
        'allocatable_id',
        'allocatable_type',
        'year_number',
        'amount',
    ];

    /**
     * Polymorphic relationship.
     * Can belong to Project, Activity, or SubActivity.
     */
    public function allocatable()
    {
        return $this->morphTo();
    }

    /**
     * Scope: Filter allocations by a specific year.
     */
    public function scopeByYear($query, $year)
    {
        return $query->where('year_number', $year);
    }

    /**
     * Accessor: Format amount nicely with 2 decimals.
     */
    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2);
    }

    /**
     * Scope: Get total allocations for an allocatable model.
     */
    public function scopeTotalFor($query, $allocatableId, $allocatableType)
    {
        return $query->where('allocatable_id', $allocatableId)
                     ->where('allocatable_type', $allocatableType)
                     ->sum('amount');
    }

    /**
     * Static helper: Initialize default year slots.
     * e.g., BudgetAllocation::initializeYears($project, 3)
     */
    public static function initializeYears(Model $model, int $durationYears)
    {
        for ($year = 1; $year <= $durationYears; $year++) {
            self::create([
                'allocatable_id' => $model->id,
                'allocatable_type' => get_class($model),
                'year_number' => $year,
                'amount' => 0,
            ]);
        }
    }
}
