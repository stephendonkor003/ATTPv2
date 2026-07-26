<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
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

    public function getCoverImageUrlAttribute(): ?string
    {
        if (blank($this->cover_image_path)) {
            return null;
        }

        $path = str_replace('\\', '/', trim((string) $this->cover_image_path));

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        if (! Storage::disk('public')->exists($this->coverImageStoragePath())) {
            return null;
        }

        return route('news.cover', $this);
    }

    public function coverImageStoragePath(): string
    {
        $path = str_replace('\\', '/', trim((string) $this->cover_image_path));
        $path = ltrim($path, '/');

        if (Str::startsWith($path, 'public/')) {
            $path = Str::after($path, 'public/');
        }

        if (Str::startsWith($path, 'storage/')) {
            $path = Str::after($path, 'storage/');
        }

        return $path;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        static::saving(function (NewsPost $post): void {
            if (blank($post->slug)) {
                $post->slug = self::generateUniqueSlug((string) $post->title, $post);
            }
        });
    }

    public static function generateUniqueSlug(string $title, ?self $ignore = null): string
    {
        $base = Str::limit(Str::slug($title), 230, '');

        if ($base === '') {
            $base = 'news-' . Str::lower(Str::random(10));
        }

        $slug = $base;
        $counter = 2;

        while (self::query()
            ->where('slug', $slug)
            ->when($ignore?->exists, fn ($query) => $query->where($ignore->getKeyName(), '!=', $ignore->getKey()))
            ->exists()) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }
}
