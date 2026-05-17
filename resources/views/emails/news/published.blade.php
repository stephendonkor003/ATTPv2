<!doctype html>
<html>
<body style="font-family: Arial, sans-serif; color: #172033; line-height: 1.6;">
    <h2 style="color:#006B3F;">{{ $post->title }}</h2>
    <p>{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body), 180) }}</p>
    <p>
        <a href="{{ $newsUrl }}" style="display:inline-block;background:#006B3F;color:#ffffff;padding:10px 14px;border-radius:6px;text-decoration:none;">
            Read the full update
        </a>
    </p>
    <p style="font-size:12px;color:#64748b;">
        You are receiving this because you subscribed to ATTP news updates.
        <a href="{{ $unsubscribeUrl }}">Unsubscribe</a>
    </p>
</body>
</html>
