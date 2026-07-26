<?php

namespace App\Http\Controllers;

use App\Mail\NewsPublishedNotification;
use App\Models\NewsAttachment;
use App\Models\NewsPost;
use App\Models\NewsSubscriber;
use App\Services\GalleryImageService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class NewsAdminController extends Controller
{
    public function index(Request $request, GalleryImageService $gallery)
    {
        $posts = NewsPost::with(['creator', 'approver'])
            ->withCount('attachments')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(15)
            ->withQueryString();
        $newsCoverFallbackUrl = $this->newsCoverFallbackUrl($gallery, 'thumb');

        return view('system.news.index', compact('posts', 'newsCoverFallbackUrl'));
    }

    public function create(GalleryImageService $gallery)
    {
        return view('system.news.form', [
            'post' => new NewsPost(),
            'newsCoverFallbackUrl' => $this->newsCoverFallbackUrl($gallery, 'thumb'),
            'newsUploadLimits' => $this->newsUploadLimits(),
        ]);
    }

    public function store(Request $request)
    {
        $action = (string) $request->input('action', 'draft');
        $data = $this->validated($request);
        $data['created_by'] = $request->user()?->id;
        $data = array_merge($data, $this->workflowData($request, null, $action));
        $storedPublicPaths = [];
        $storedLocalPaths = [];

        try {
            $post = DB::transaction(function () use ($request, $data, &$storedPublicPaths, &$storedLocalPaths) {
                if ($request->hasFile('cover_image')) {
                    $data['cover_image_path'] = $this->storeUpload(
                        $request->file('cover_image'),
                        'news/covers',
                        'public'
                    );
                    $storedPublicPaths[] = $data['cover_image_path'];
                }

                $post = NewsPost::create($data);
                $this->storeAttachments($request, $post, $storedLocalPaths);

                return $post;
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFiles('public', $storedPublicPaths);
            $this->deleteStoredFiles('local', $storedLocalPaths);
            Log::error('News post could not be saved.', [
                'user_id' => $request->user()?->id,
                'message' => $exception->getMessage(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'The news post could not be saved. Please check the files and try again.');
        }

        $notificationWarning = $this->notifyPublishedPost($post->fresh());

        return redirect()
            ->route('system.news.edit', $post)
            ->with('success', $this->saveSuccessMessage($post->fresh(), $action, $notificationWarning));
    }

    public function edit(NewsPost $post, GalleryImageService $gallery)
    {
        $post->load('attachments');

        return view('system.news.form', [
            'post' => $post,
            'newsCoverFallbackUrl' => $this->newsCoverFallbackUrl($gallery, 'thumb'),
            'newsUploadLimits' => $this->newsUploadLimits(),
        ]);
    }

    public function update(Request $request, NewsPost $post)
    {
        $action = (string) $request->input('action', 'save');
        $data = $this->validated($request);
        $data = array_merge($data, $this->workflowData($request, $post, $action));
        $oldCoverPath = $post->cover_image_path;
        $newCoverPath = null;
        $storedPublicPaths = [];
        $storedLocalPaths = [];

        try {
            if ($request->hasFile('cover_image')) {
                $newCoverPath = $this->storeUpload(
                    $request->file('cover_image'),
                    'news/covers',
                    'public'
                );
                $storedPublicPaths[] = $newCoverPath;
                $data['cover_image_path'] = $newCoverPath;
            }

            DB::transaction(function () use ($request, $post, $data, &$storedLocalPaths) {
                $post->update($data);
                $this->storeAttachments($request, $post, $storedLocalPaths);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFiles('public', $storedPublicPaths);
            $this->deleteStoredFiles('local', $storedLocalPaths);
            Log::error('News post could not be updated.', [
                'news_post_id' => $post->id,
                'user_id' => $request->user()?->id,
                'message' => $exception->getMessage(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'The news post could not be updated. Your existing post was not changed.');
        }

        if ($newCoverPath && $oldCoverPath && $oldCoverPath !== $newCoverPath) {
            Storage::disk('public')->delete($oldCoverPath);
        }

        $post->refresh();
        $notificationWarning = $this->notifyPublishedPost($post);

        return redirect()
            ->route('system.news.edit', $post)
            ->with('success', $this->saveSuccessMessage($post, $action, $notificationWarning));
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

        $post->refresh();
        $notificationWarning = $this->notifyPublishedPost($post);

        if ($post->isPublished()) {
            $message = 'News approved, published, and now visible on the public news page.';
        } elseif ($post->status === 'published' && $post->published_at?->isFuture()) {
            $message = 'News approved and scheduled. It will appear publicly on '.$post->published_at->format('d M Y H:i').'.';
        } else {
            $message = 'News approval saved. It is not public until the decision is set to “Approve and publish.”';
        }

        if ($notificationWarning) {
            $message .= ' '.$notificationWarning;
        }

        return back()->with('success', $message);
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
        $routePost = $request->route('post');
        $post = $routePost instanceof NewsPost ? $routePost : null;
        $requestedSlug = Str::slug((string) $request->input('slug'));
        $slug = filled($requestedSlug)
            ? $requestedSlug
            : NewsPost::generateUniqueSlug((string) $request->input('title'), $post);

        $request->merge(['slug' => $slug]);

        $uniqueSlug = Rule::unique('attp_news_posts', 'slug');
        $uploadLimits = $this->newsUploadLimits();

        if ($post?->exists) {
            $uniqueSlug->ignore($post->getKey(), $post->getKeyName());
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                $uniqueSlug,
            ],
            'category' => 'required|in:policy,research,events,announcement,press',
            'excerpt' => 'nullable|string|max:500',
            'body' => 'required|string|max:500000',
            'tags' => 'nullable|string|max:1000',
            'cover_image' => "nullable|image|max:{$uploadLimits['cover_kb']}",
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => "nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,jpg,jpeg,png|max:{$uploadLimits['attachment_kb']}",
            'action' => 'required|in:save,draft,submit,publish',
        ], [
            'cover_image.max' => "The cover image must not be larger than {$uploadLimits['cover_label']}.",
            'attachments.max' => 'You may upload no more than 10 attachments at a time.',
            'attachments.*.max' => "Each attachment must not be larger than {$uploadLimits['attachment_label']}.",
            'attachments.*.mimes' => 'Attachments must be PDF, Office, ZIP, JPG, JPEG, or PNG files.',
        ]);

        $data['tags'] = collect(explode(',', (string) ($data['tags'] ?? '')))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->values()
            ->all();
        $data['body'] = $this->sanitizeNewsHtml($data['body']);
        $this->validateNewsBody($data['body']);
        $this->validateTotalUploadSize($request, $uploadLimits);

        unset($data['cover_image'], $data['attachments'], $data['action']);

        return $data;
    }

    private function workflowData(Request $request, ?NewsPost $post, string $action): array
    {
        $userId = $request->user()?->id;

        if ($action === 'publish') {
            abort_unless(
                $request->user()?->canAny(['news.approve', 'communications.respond']),
                403,
                'You do not have permission to publish news.'
            );

            return [
                'status' => 'published',
                'submitted_by' => $post?->submitted_by ?: $userId,
                'submitted_at' => $post?->submitted_at ?: now(),
                'approved_by' => $userId,
                'approved_at' => now(),
                'published_at' => $post?->isPublished() ? $post->published_at : now(),
                'review_notes' => null,
            ];
        }

        if ($action === 'submit') {
            return [
                'status' => 'submitted',
                'submitted_by' => $userId,
                'submitted_at' => now(),
                'approved_by' => null,
                'approved_at' => null,
                'published_at' => null,
                'review_notes' => null,
            ];
        }

        if ($action === 'draft' || ! $post?->exists) {
            return [
                'status' => 'draft',
                'submitted_by' => null,
                'submitted_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'published_at' => null,
                'review_notes' => null,
            ];
        }

        return [];
    }

    private function storeAttachments(Request $request, NewsPost $post, array &$storedPaths): void
    {
        foreach ($request->file('attachments', []) as $file) {
            if (! $file) {
                continue;
            }

            $path = $this->storeUpload($file, "news/attachments/{$post->id}", 'local');
            $storedPaths[] = $path;
            [$title, $fileName] = $this->attachmentNames($file);

            $post->attachments()->create([
                'uploaded_by' => $request->user()?->id,
                'title' => $title,
                'file_path' => $path,
                'file_name' => $fileName,
                'mime_type' => Str::limit((string) $file->getMimeType(), 255, ''),
                'file_size_bytes' => $file->getSize(),
            ]);
        }
    }

    private function storeUpload(UploadedFile $file, string $directory, string $disk): string
    {
        $path = $file->store($directory, $disk);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException("The uploaded file could not be written to the {$disk} disk.");
        }

        return $path;
    }

    private function attachmentNames(UploadedFile $file): array
    {
        $originalName = basename(str_replace('\\', '/', trim($file->getClientOriginalName())));
        $extension = Str::limit((string) pathinfo($originalName, PATHINFO_EXTENSION), 20, '');
        $title = trim((string) pathinfo($originalName, PATHINFO_FILENAME));
        $title = Str::limit($title !== '' ? $title : 'Attachment', 255, '');
        $baseLength = max(1, 254 - Str::length($extension));
        $fileName = Str::limit($title, $baseLength, '');

        if ($extension !== '') {
            $fileName .= '.' . $extension;
        }

        return [$title, $fileName];
    }

    private function validateNewsBody(string $body): void
    {
        if (preg_match('/<img\b[^>]*\bsrc\s*=\s*(["\'])\s*data:image\//i', $body)) {
            throw ValidationException::withMessages([
                'body' => 'Images cannot be pasted directly into the article. Please upload the main image under Cover Image.',
            ]);
        }

        $plainText = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], ' ', $body)));
        $plainText = preg_replace('/\x{00A0}/u', ' ', $plainText) ?? '';

        if (trim($plainText) === '') {
            throw ValidationException::withMessages([
                'body' => 'The news body must contain some text.',
            ]);
        }
    }

    private function validateTotalUploadSize(Request $request, array $uploadLimits): void
    {
        $files = collect($request->file('attachments', []));

        if ($request->hasFile('cover_image')) {
            $files->prepend($request->file('cover_image'));
        }

        $totalBytes = $files->sum(fn ($file) => $file instanceof UploadedFile ? (int) $file->getSize() : 0);

        if ($totalBytes > $uploadLimits['combined_bytes']) {
            throw ValidationException::withMessages([
                'attachments' => "The combined upload size must not be larger than {$uploadLimits['combined_label']}.",
            ]);
        }
    }

    private function newsUploadLimits(): array
    {
        $megabyte = 1024 * 1024;
        $phpFileLimit = $this->phpIniBytes((string) ini_get('upload_max_filesize'));
        $phpPostLimit = $this->phpIniBytes((string) ini_get('post_max_size'));
        $fileCeiling = $phpFileLimit > 0 ? $phpFileLimit : PHP_INT_MAX;
        $postCeiling = $phpPostLimit > 0 ? (int) floor($phpPostLimit * 0.85) : PHP_INT_MAX;
        $coverBytes = min(10 * $megabyte, $fileCeiling);
        $attachmentBytes = min(20 * $megabyte, $fileCeiling);
        $combinedBytes = min(50 * $megabyte, $postCeiling);

        return [
            'cover_bytes' => $coverBytes,
            'cover_kb' => max(1, (int) floor($coverBytes / 1024)),
            'cover_label' => $this->formatMegabytes($coverBytes),
            'attachment_bytes' => $attachmentBytes,
            'attachment_kb' => max(1, (int) floor($attachmentBytes / 1024)),
            'attachment_label' => $this->formatMegabytes($attachmentBytes),
            'combined_bytes' => $combinedBytes,
            'combined_label' => $this->formatMegabytes($combinedBytes),
        ];
    }

    private function phpIniBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return match ($unit) {
            'g' => (int) round($number * 1024 * 1024 * 1024),
            'm' => (int) round($number * 1024 * 1024),
            'k' => (int) round($number * 1024),
            default => (int) round($number),
        };
    }

    private function formatMegabytes(int $bytes): string
    {
        $megabytes = $bytes / (1024 * 1024);

        return rtrim(rtrim(number_format($megabytes, 1, '.', ''), '0'), '.') . ' MB';
    }

    private function deleteStoredFiles(string $disk, array $paths): void
    {
        $paths = array_values(array_filter($paths, fn ($path) => is_string($path) && $path !== ''));

        if ($paths !== []) {
            Storage::disk($disk)->delete($paths);
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

    private function notifyPublishedPost(NewsPost $post): ?string
    {
        if (! $post->isPublished()) {
            return null;
        }

        try {
            $this->notifySubscribers($post);
        } catch (Throwable $exception) {
            Log::warning('News subscriber notification failed.', [
                'news_post_id' => $post->id,
                'message' => $exception->getMessage(),
            ]);

            return 'The article is public, but subscriber email notification could not be queued.';
        }

        return null;
    }

    private function saveSuccessMessage(NewsPost $post, string $action, ?string $notificationWarning): string
    {
        $message = match ($action) {
            'publish' => 'News published successfully and is now visible on the public news page.',
            'submit' => 'News submitted for approval. It will become public after an approver publishes it.',
            'draft' => 'News draft saved. Drafts are not shown on the public news page.',
            default => $post->isPublished()
                ? 'Live news article updated successfully.'
                : 'News post updated successfully.',
        };

        if ($notificationWarning) {
            $message .= ' '.$notificationWarning;
        }

        return $message;
    }

    private function sanitizeNewsHtml(string $html): string
    {
        $html = preg_replace('#<(script|iframe|object|embed|form|input|button|textarea|select|style)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = preg_replace('#\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)#is', '', $html) ?? '';
        $html = preg_replace('#(href|src)\s*=\s*("|\')\s*javascript:[^"\']*\2#is', '$1="#"', $html) ?? '';

        return trim($html);
    }

    private function newsCoverFallbackUrl(GalleryImageService $gallery, string $size): string
    {
        return $gallery->fallbackUrl($size) ?? asset('assets/images/au1.jpg');
    }
}
