<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DiscussionTopicDocument extends Model
{
    use HasUuids;

    public const PREVIEWABLE_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'text/plain',
        'text/csv',
    ];

    protected $fillable = [
        'topic_id',
        'uploaded_by',
        'title',
        'description',
        'type',
        'file_name',
        'storage_path',
        'mime_type',
        'size_bytes',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'display_order' => 'integer',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(DiscussionTopic::class, 'topic_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function canPreview(): bool
    {
        return in_array(Str::lower($this->mime_type), self::PREVIEWABLE_MIME_TYPES, true);
    }

    public function extension(): string
    {
        return Str::lower((string) pathinfo($this->file_name, PATHINFO_EXTENSION));
    }

    public function humanReadableSize(): string
    {
        $bytes = max(0, (int) $this->size_bytes);

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / (1024 * 1024), 1).' MB';
    }
}
