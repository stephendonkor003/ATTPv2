<?php

namespace App\Http\Controllers;

use App\Mail\NewsPublishedNotification;
use App\Models\NewsAttachment;
use App\Models\NewsPost;
use App\Models\NewsSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class NewsAdminController extends Controller
{
    public function index(Request $request)
    {
        $posts = NewsPost::with(['creator', 'approver'])
            ->withCount('attachments')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('system.news.index', compact('posts'));
    }

    public function create()
    {
        return view('system.news.form', ['post' => new NewsPost()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()?->id;
        $data['status'] = $request->input('action') === 'submit' ? 'submitted' : 'draft';
        $data['submitted_by'] = $data['status'] === 'submitted' ? $request->user()?->id : null;
        $data['submitted_at'] = $data['status'] === 'submitted' ? now() : null;

        if ($request->hasFile('cover_image')) {
            $data['cover_image_path'] = $request->file('cover_image')->store('news/covers', 'public');
        }

        $post = NewsPost::create($data);
        $this->storeAttachments($request, $post);

        return redirect()->route('system.news.edit', $post)->with('success', 'News post saved.');
    }

    public function edit(NewsPost $post)
    {
        $post->load('attachments');

        return view('system.news.form', compact('post'));
    }

    public function update(Request $request, NewsPost $post)
    {
        $data = $this->validated($request);

        if ($request->hasFile('cover_image')) {
            if ($post->cover_image_path) {
                Storage::disk('public')->delete($post->cover_image_path);
            }
            $data['cover_image_path'] = $request->file('cover_image')->store('news/covers', 'public');
        }

        if ($request->input('action') === 'submit') {
            $data['status'] = 'submitted';
            $data['submitted_by'] = $request->user()?->id;
            $data['submitted_at'] = now();
        }

        $post->update($data);
        $this->storeAttachments($request, $post);

        return redirect()->route('system.news.edit', $post)->with('success', 'News post updated.');
    }

    public function approve(Request $request, NewsPost $post)
    {
        $data = $request->validate([
            'status' => 'required|in:approved,published,rejected',
            'review_notes' => 'nullable|string',
            'published_at' => 'nullable|date',
        ]);

        if ($data['status'] === 'rejected') {
            $post->update([
                'status' => 'rejected',
                'review_notes' => $data['review_notes'] ?? null,
                'approved_by' => null,
                'approved_at' => null,
                'published_at' => null,
            ]);

            return back()->with('success', 'News post rejected.');
        }

        $post->update([
            'status' => $data['status'],
            'review_notes' => $data['review_notes'] ?? null,
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
            'published_at' => $data['status'] === 'published'
                ? ($data['published_at'] ?? now())
                : null,
        ]);

        if ($post->status === 'published') {
            try {
                $this->notifySubscribers($post->fresh());
            } catch (Throwable $exception) {
                Log::warning('News subscriber notification failed.', [
                    'news_post_id' => $post->id,
                    'message' => $exception->getMessage(),
                ]);

                return back()->with('success', 'News approval saved. Subscriber email notification failed because the mail server connection was closed.');
            }
        }

        return back()->with('success', 'News approval saved.');
    }

    public function destroyAttachment(NewsPost $post, NewsAttachment $attachment)
    {
        abort_unless($attachment->news_post_id === $post->id, 404);
        Storage::disk('local')->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'Attachment removed.');
    }

    private function validated(Request $request): array
    {
        $post = $request->route('post');
        $slug = filled($request->input('slug'))
            ? Str::slug((string) $request->input('slug'))
            : Str::slug((string) $request->input('title'));

        $request->merge(['slug' => $slug]);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('attp_news_posts', 'slug')->when($post?->exists, fn ($rule) => $rule->ignore($post->id)),
            ],
            'category' => 'required|in:policy,research,events,announcement,press',
            'excerpt' => 'nullable|string|max:500',
            'body' => 'required|string',
            'tags' => 'nullable|string|max:1000',
            'cover_image' => 'nullable|image|max:4096',
            'attachments.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,jpg,jpeg,png|max:20480',
        ]);

        $data['tags'] = collect(explode(',', (string) ($data['tags'] ?? '')))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->values()
            ->all();
        $data['body'] = $this->sanitizeNewsHtml($data['body']);
        $data['slug'] = $data['slug'] ?: null;

        unset($data['cover_image'], $data['attachments']);

        return $data;
    }

    private function storeAttachments(Request $request, NewsPost $post): void
    {
        foreach ($request->file('attachments', []) as $file) {
            if (! $file) {
                continue;
            }

            $path = $file->store("news/attachments/{$post->id}", 'local');

            $post->attachments()->create([
                'uploaded_by' => $request->user()?->id,
                'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size_bytes' => $file->getSize(),
            ]);
        }
    }

    private function notifySubscribers(NewsPost $post): void
    {
        if ($post->notified_at) {
            return;
        }

        NewsSubscriber::active()->orderBy('email')->chunk(100, function ($subscribers) use ($post) {
            foreach ($subscribers as $subscriber) {
                Mail::to($subscriber->email)->queue(new NewsPublishedNotification($post, $subscriber));
            }
        });

        $post->update(['notified_at' => now()]);
    }

    private function sanitizeNewsHtml(string $html): string
    {
        $html = preg_replace('#<(script|iframe|object|embed|form|input|button|textarea|select|style)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = preg_replace('#\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)#is', '', $html) ?? '';
        $html = preg_replace('#(href|src)\s*=\s*("|\')\s*javascript:[^"\']*\2#is', '$1="#"', $html) ?? '';

        return trim($html);
    }
}
