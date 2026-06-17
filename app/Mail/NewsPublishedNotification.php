<?php

namespace App\Mail;

use App\Models\NewsPost;
use App\Models\NewsSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class NewsPublishedNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public NewsPost $post,
        public NewsSubscriber $subscriber
    ) {
        $this->onQueue('mail');
    }

    public function build()
    {
        return $this->subject('New ATTP news: ' . Str::limit($this->post->title, 70))
            ->view('emails.news.published')
            ->with([
                'post' => $this->post,
                'subscriber' => $this->subscriber,
                'newsUrl' => route('news.show', $this->post),
                'unsubscribeUrl' => route('news.unsubscribe', $this->subscriber->unsubscribe_token),
            ]);
    }
}
