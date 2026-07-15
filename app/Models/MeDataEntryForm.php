<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MeDataEntryForm extends BaseModel
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    protected $table = 'me_data_entry_forms';

    protected $fillable = [
        'portfolio_id',
        'indicator_id',
        'title',
        'description',
        'instructions',
        'responsible_user_id',
        'version',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (MeDataEntryForm $form): void {
            $form->code = static::generateCode();
        });

        static::updating(function (MeDataEntryForm $form): void {
            if ($form->isDirty('code')) {
                $form->code = $form->getOriginal('code');
            }
        });
    }

    public static function generateCode(): string
    {
        do {
            $code = 'MEF-'.now()->format('Y').'-'.Str::upper(Str::random(8));
        } while (static::query()->where('code', $code)->exists());

        return $code;
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'portfolio_id');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class, 'indicator_id');
    }

    public function responsiblePerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(MeDataEntryFormField::class, 'form_id')
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(MeDataEntryFormSection::class, 'form_id')
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(MeDataCollection::class, 'form_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }
}
