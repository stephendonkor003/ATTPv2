<?php

use App\Mail\NewsPublishedNotification;
use App\Models\NewsAttachment;
use App\Models\NewsPost;
use App\Models\NewsSubscriber;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithExceptionHandling;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

class NewsPublishingSmoke
{
    use MakesHttpRequests;
    use InteractsWithAuthentication;
    use InteractsWithSession;
    use InteractsWithExceptionHandling;

    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function run(): void
    {
        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');

        Artisan::call('db:seed', ['--class' => PermissionSeeder::class]);

        DB::beginTransaction();

        try {
            $user = $this->communicationsUser();

            $subscriber = NewsSubscriber::create([
                'email' => 'news-subscriber-' . Str::lower(Str::random(6)) . '@example.test',
                'name' => 'News Subscriber',
                'status' => 'active',
                'source' => 'smoke',
            ]);

            $title = 'E2E Approved News ' . Str::upper(Str::random(5));

            $this->actingAs($user)
                ->get(route('system.news.create'))
                ->assertOk()
                ->assertSee('Create News');

            $this->actingAs($user)
                ->postWithCsrf(route('system.news.store'), [
                    'title' => $title,
                    'category' => 'announcement',
                    'excerpt' => 'End to end news excerpt.',
                    'body' => 'End to end approved news body with useful communication details.',
                    'tags' => 'ATTP, Communications, News',
                    'cover_image' => UploadedFile::fake()->image('cover.jpg', 900, 500),
                    'attachments' => [
                        UploadedFile::fake()->create('brief.pdf', 120, 'application/pdf'),
                    ],
                    'action' => 'submit',
                ])
                ->assertRedirect();

            $post = NewsPost::where('title', $title)->first();
            $this->assertTrue((bool) $post, 'News post was not created.');
            $this->assertSame('submitted', $post->status, 'News post was not submitted.');
            $this->assertTrue($post->attachments()->exists(), 'News attachment was not stored.');

            $this->get(route('news.show', $post))->assertNotFound();

            $this->actingAs($user)
                ->postWithCsrf(route('system.news.approve', $post), [
                    'status' => 'published',
                    'review_notes' => 'Approved for publication.',
                ])
                ->assertRedirect();

            $post->refresh();
            $this->assertSame('published', $post->status, 'News post was not published.');
            $this->assertTrue($post->approved_at !== null, 'Published news was not approved.');
            $this->assertTrue($post->notified_at !== null, 'Subscribers were not marked notified.');

            Mail::assertSent(NewsPublishedNotification::class, function (NewsPublishedNotification $mail) use ($subscriber, $post) {
                return $mail->subscriber->email === $subscriber->email && $mail->post->id === $post->id;
            });

            $this->get(route('news.index'))
                ->assertOk()
                ->assertSee($title);

            $this->get(route('news.show', $post))
                ->assertOk()
                ->assertSee($title)
                ->assertSee('Downloads')
                ->assertSee('brief.pdf');

            $attachment = NewsAttachment::where('news_post_id', $post->id)->first();
            $this->get(route('news.attachments.download', [$post, $attachment]))
                ->assertOk();

            $this->postWithCsrf(route('news.subscribe'), [
                'email' => 'new-public-subscriber-' . Str::lower(Str::random(6)) . '@example.test',
            ])->assertRedirect();

            $this->assertTrue(NewsSubscriber::where('source', 'news_page')->active()->exists(), 'Public subscription did not create an active subscriber.');

            echo "NEWS_E2E_OK\n";
        } finally {
            DB::rollBack();
        }
    }

    private function communicationsUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'Communications Officer'], ['description' => 'Communications officer']);
        $permissionIds = Permission::whereIn('name', ['communications.respond', 'communications.view', 'news.manage', 'news.approve'])->pluck('id')->all();
        $role->permissions()->syncWithoutDetaching($permissionIds);

        return User::create([
            'name' => 'E2E Communications Officer',
            'email' => 'e2e-news-' . Str::lower(Str::random(6)) . '@example.test',
            'password' => Hash::make('Password123!'),
            'user_type' => 'admin',
            'role_id' => $role->id,
            'must_change_password' => false,
        ]);
    }

    private function postWithCsrf(string $uri, array $data = [])
    {
        $token = Str::random(40);

        return $this->withSession(['_token' => $token])
            ->post($uri, ['_token' => $token, ...$data]);
    }

    private function assertTrue(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }

    private function assertSame($expected, $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($message);
        }
    }
}

(new NewsPublishingSmoke($app))->run();
