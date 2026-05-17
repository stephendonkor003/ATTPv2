<?php

namespace App\Models;

use Illuminate\Support\Str;

class NewsSubscriber extends BaseModel
{
    protected $table = 'attp_news_subscribers';

    protected $fillable = [
        'email',
        'name',
        'status',
        'source',
        'subscribed_at',
        'unsubscribed_at',
        'unsubscribe_token',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    protected static function booted(): void
    {
        static::creating(function (NewsSubscriber $subscriber): void {
            $subscriber->unsubscribe_token ??= Str::random(48);
            $subscriber->subscribed_at ??= now();
        });
    }
}
