<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DiscussionTopic extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'theme_id',
        'created_by',
        'title',
        'slug',
        'summary',
        'body',
        'related_links',
        'materials',
        'documents',
        'status',
        'is_featured',
        'requires_moderation',
        'allow_replies',
        'starts_at',
        'closes_at',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'requires_moderation' => 'boolean',
            'allow_replies' => 'boolean',
            'starts_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(DiscussionTheme::class, 'theme_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(DiscussionPost::class, 'topic_id');
    }

    public function participations(): HasMany
    {
        return $this->hasMany(DiscussionTopicParticipant::class, 'topic_id');
    }

    public function uploadedDocuments(): HasMany
    {
        return $this->hasMany(DiscussionTopicDocument::class, 'topic_id')
            ->orderBy('display_order')
            ->orderBy('created_at');
    }

    /**
     * Return the three public resource collections in their canonical API shape.
     *
     * @return array{related_links: array<int, array<string, string|null>>, materials: array<int, array<string, string|null>>, documents: array<int, array<string, string|null>>}
     */
    public function resourceCollections(): array
    {
        return [
            'related_links' => $this->related_links,
            'materials' => $this->materials,
            'documents' => $this->documents,
        ];
    }

    /**
     * Normalize administrator-managed resources before storage and again on read.
     *
     * @return array<int, array{title: string, url: string, description: string|null, type: string}>
     */
    public static function normalizeResourceItems(mixed $items, string $collection): array
    {
        if (! is_array($items)) {
            return [];
        }

        $defaultType = match ($collection) {
            'related_links' => 'link',
            'materials' => 'material',
            'documents' => 'document',
            default => 'resource',
        };

        return collect($items)
            ->take(20)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item) use ($defaultType): ?array {
                $title = self::cleanResourceText($item['title'] ?? $item['label'] ?? null, 180);
                $url = is_scalar($item['url'] ?? null) ? trim((string) $item['url']) : '';

                if (! $title || ! self::isSafeResourceUrl($url)) {
                    return null;
                }

                $description = self::cleanResourceText($item['description'] ?? null, 500);
                $type = self::cleanResourceText($item['type'] ?? null, 40);
                $type = $type ? Str::slug($type) : $defaultType;

                return [
                    'title' => $title,
                    'url' => $url,
                    'description' => $description,
                    'type' => $type ?: $defaultType,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public static function isSafeResourceUrl(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $url = trim($value);
        if ($url === '' || mb_strlen($url) > 2048 || preg_match('/[\x00-\x1F\x7F]/u', $url)) {
            return false;
        }

        if (Str::startsWith($url, '/')) {
            if (Str::startsWith($url, '//') || str_contains($url, '\\')) {
                return false;
            }

            $parts = parse_url($url);
            $path = is_array($parts) ? ($parts['path'] ?? '') : '';
            $decodedPath = rawurldecode($path);

            return $path !== ''
                && ! isset($parts['scheme'])
                && ! isset($parts['host'])
                && ! isset($parts['user'])
                && ! isset($parts['pass'])
                && ! str_contains($decodedPath, '\\')
                && ! in_array('..', explode('/', $decodedPath), true);
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && in_array(Str::lower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && filled($parts['host'] ?? null)
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }

    protected function relatedLinks(): Attribute
    {
        return $this->resourceCollectionAttribute('related_links');
    }

    protected function materials(): Attribute
    {
        return $this->resourceCollectionAttribute('materials');
    }

    protected function documents(): Attribute
    {
        return $this->resourceCollectionAttribute('documents');
    }

    protected function resourceCollectionAttribute(string $collection): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): array => self::normalizeResourceItems(
                is_string($value) ? json_decode($value, true) : $value,
                $collection
            ),
            set: fn (mixed $value): string => json_encode(
                self::normalizeResourceItems($value, $collection),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
        );
    }

    private static function cleanResourceText(mixed $value, int $limit): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $decoded = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = Str::squish(strip_tags($decoded));

        return $text === '' ? null : Str::limit($text, $limit, '');
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->whereIn('status', ['open', 'closed'])
            ->where(function (Builder $builder): void {
                $builder->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            });
    }

    public function acceptsPosts(): bool
    {
        if ($this->status !== 'open' || ! $this->allow_replies) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        return ! $this->closes_at || $this->closes_at->isFuture();
    }
}
