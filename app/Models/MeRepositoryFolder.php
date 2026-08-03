<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeRepositoryFolder extends BaseModel
{
    protected $table = 'me_repository_folders';

    protected $fillable = [
        'portfolio_id',
        'name',
        'description',
        'created_by',
        'updated_by',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'portfolio_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MeKnowledgeEvidenceItem::class, 'folder_id');
    }

    public function indicators(): BelongsToMany
    {
        return $this->belongsToMany(
            Indicator::class,
            'me_repository_folder_indicators',
            'folder_id',
            'indicator_id'
        )->withPivot('linked_by')->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
