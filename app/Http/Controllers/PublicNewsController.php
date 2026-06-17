<?php

namespace App\Http\Controllers;

use App\Models\NewsAttachment;
use App\Models\NewsPost;
use App\Models\NewsSubscriber;
use App\Services\GalleryImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicNewsController extends Controller
{
    public function index(Request $request, GalleryImageService $gallery)
    {
        $query = NewsPost::published()->with(['attachments', 'creator']);

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        if ($request->filled('q')) {
            $search = '%' . trim((string) $request->input('q')) . '%';
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', $search)
                    ->orWhere('excerpt', 'like', $search)
                    ->orWhere('body', 'like', $search);
            });
        }

        $posts = $query->orderByDesc('published_at')->paginate(9)->withQueryString();
        $categories = NewsPost::published()->select('category')->distinct()->orderBy('category')->pluck('category');
        $newsCoverFallbackUrl = $this->newsCoverFallbackUrl($gallery, 'thumb');

        return view('public.news.index', compact('posts', 'categories', 'newsCoverFallbackUrl'));
    }

    public function show(NewsPost $post, GalleryImageService $gallery)
    {
        abort_unless($post->isPublished(), 404);

        $post->load('attachments', 'creator');

        $related = NewsPost::published()
            ->whereKeyNot($post->id)
            ->where('category', $post->category)
            ->orderByDesc('published_at')
            ->limit(4)
            ->get();

        if ($related->count() < 4) {
            $related = $related->merge(
                NewsPost::published()
                    ->whereKeyNot($post->id)
                    ->whereNotIn('id', $related->pluck('id'))
                    ->orderByDesc('published_at')
                    ->limit(4 - $related->count())
                    ->get()
            );
        }

        $newsCoverFallbackUrl = $this->newsCoverFallbackUrl($gallery, 'large');

        return view('public.news.show', compact('post', 'related', 'newsCoverFallbackUrl'));
    }

    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:255',
        ]);

        NewsSubscriber::updateOrCreate(
            ['email' => Str::lower($data['email'])],
            [
                'name' => $data['name'] ?? null,
                'status' => 'active',
                'source' => 'news_page',
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
            ]
        );

        return back()->with('success', 'You are subscribed to ATTP news updates.');
    }

    public function unsubscribe(string $token)
    {
        $subscriber = NewsSubscriber::where('unsubscribe_token', $token)->firstOrFail();

        $subscriber->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);

        return redirect()->route('news.index')->with('success', 'You have been unsubscribed from ATTP news updates.');
    }

    public function download(NewsPost $post, NewsAttachment $attachment)
    {
        abort_unless($post->isPublished(), 404);
        abort_unless($attachment->news_post_id === $post->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        $attachment->increment('download_count');

        return Storage::disk('local')->download($attachment->file_path, $attachment->file_name);
    }

    private function newsCoverFallbackUrl(GalleryImageService $gallery, string $size): string
    {
        return $gallery->fallbackUrl($size) ?? asset('assets/images/au1.jpg');
    }
}
