<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NewsPost extends BaseModel
{
    protected $table = 'attp_news_posts';

    protected $fillable = [
        'title',
        'slug',
        'category',
        'excerpt',
        'body',
        'cover_image_path',
        'status',
        'tags',
        'created_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'published_at',
        'review_notes',
        'notified_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
        'notified_at' => 'datetime',
    ];

    public function attachments(): HasMany
    {
        return $this->hasMany(NewsAttachment::class, 'news_post_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->whereNotNull('approved_at')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->approved_at !== null && $this->published_at !== null && $this->published_at->lte(now());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        static::saving(function (NewsPost $post): void {
            if (blank($post->slug)) {
                $base = Str::slug($post->title);
                $slug = $base;
                $counter = 1;

                while (self::where('slug', $slug)->when($post->exists, fn ($query) => $query->where('id', '!=', $post->id))->exists()) {
                    $slug = $base . '-' . $counter++;
                }

                $post->slug = $slug;
            }
        });
    }
}
