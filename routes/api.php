<?php

use App\Http\Controllers\Api\DiscussionForumController;
use App\Http\Controllers\Api\DiscussionParticipantPasswordController;
use Illuminate\Support\Facades\Route;

Route::prefix('discussions')
    ->name('api.discussions.')
    ->group(function (): void {
        Route::get('/overview', [DiscussionForumController::class, 'overview'])->name('overview');
        Route::get('/themes', [DiscussionForumController::class, 'themes'])->name('themes');
        Route::get('/countries', [DiscussionForumController::class, 'countries'])->name('countries');
        Route::get('/topics', [DiscussionForumController::class, 'topics'])->name('topics.index');
        Route::get('/topics/{topic:slug}/activity', [DiscussionForumController::class, 'topicActivity'])->name('topics.activity');
        Route::get('/topics/{topic:slug}', [DiscussionForumController::class, 'show'])->name('topics.show');

        Route::post('/participants/register', [DiscussionForumController::class, 'register'])
            ->middleware('throttle:5,1,discussion-register')
            ->name('participants.register');
        Route::post('/participants/login', [DiscussionForumController::class, 'login'])
            ->middleware('throttle:10,1,discussion-login')
            ->name('participants.login');
        Route::post('/participants/password/forgot', [DiscussionParticipantPasswordController::class, 'forgot'])
            ->middleware('throttle:5,1,discussion-password-forgot')
            ->name('participants.password.forgot');
        Route::post('/participants/password/reset', [DiscussionParticipantPasswordController::class, 'reset'])
            ->middleware('throttle:10,1,discussion-password-reset')
            ->name('participants.password.reset');
        // Logout intentionally remains callable with an expired/revoked device
        // credential so the browser can always discard its HttpOnly cookie.
        Route::post('/participants/logout', [DiscussionForumController::class, 'logout'])
            ->middleware('throttle:30,1,discussion-logout')
            ->name('participants.logout');

        Route::middleware(['discussion.participant', 'throttle:30,1,discussion-participant'])->group(function (): void {
            Route::get('/participants/me', [DiscussionForumController::class, 'me'])->name('participants.me');
            Route::post('/topics/{topic:slug}/presence', [DiscussionForumController::class, 'joinTopic'])->name('topics.presence');
            Route::post('/topics/{topic:slug}/posts', [DiscussionForumController::class, 'storePost'])->name('posts.store');
            Route::post('/posts/{post}/reaction', [DiscussionForumController::class, 'toggleReaction'])->name('posts.reaction');
        });
    });
