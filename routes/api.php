<?php

use App\Http\Controllers\Api\ApiSyncController;
use App\Http\Controllers\Api\ApiSyncDocumentController;
use App\Http\Controllers\Api\ApiSyncInvitationController;
use App\Http\Controllers\Api\DiscussionForumController;
use App\Http\Controllers\Api\DiscussionParticipantPasswordController;
use Illuminate\Support\Facades\Route;

if ((bool) config('api_sync.legacy_v1_enabled', false)) {
    Route::prefix('sync/v1')
        ->name('api.sync.v1.')
        ->group(function (): void {
            Route::post('/pairings/claim', [ApiSyncController::class, 'claim'])
                ->middleware('throttle:5,1,api-sync-claim')
                ->name('pairings.claim');

            Route::post('/pairings/abandon', [ApiSyncController::class, 'abandon'])
                ->middleware('throttle:5,1,api-sync-abandon')
                ->name('pairings.abandon');

            Route::middleware(['api.sync', 'throttle:180,1,api-sync-data'])->group(function (): void {
                Route::get('/manifest', [ApiSyncController::class, 'manifest'])->name('manifest');
                Route::get('/datasets/{dataset}', [ApiSyncController::class, 'dataset'])
                    ->where('dataset', '[a-z_]+')
                    ->name('datasets.show');
                Route::post('/pairings/complete', [ApiSyncController::class, 'complete'])
                    ->name('pairings.complete');
            });
        });
}

/*
|--------------------------------------------------------------------------
| AU-PReMIS initiated synchronization (v2)
|--------------------------------------------------------------------------
|
| AU-PReMIS signs an invitation, a local ATTP administrator approves it,
| and the central server proves possession of a separate high-entropy bearer
| before immutable snapshot data becomes readable. Legacy v1 routes are
| registered only when their explicit migration feature flag is enabled.
|
*/
Route::prefix('sync/v2')
    ->name('api.sync.v2.')
    ->group(function (): void {
        Route::post('/invitations', [ApiSyncInvitationController::class, 'store'])
            ->middleware('throttle:30,1,api-sync-v2-invitations')
            ->name('invitations.store');
        Route::post('/invitations/{invitation}/activate', [ApiSyncInvitationController::class, 'activate'])
            ->middleware('throttle:20,1,api-sync-v2-activate')
            ->name('invitations.activate');
        Route::post('/invitations/{invitation}/finalize', [ApiSyncInvitationController::class, 'finalize'])
            ->middleware('throttle:30,1,api-sync-v2-finalize')
            ->name('invitations.finalize');

        Route::middleware(['api.sync.v2', 'throttle:180,1,api-sync-v2-data'])->group(function (): void {
            Route::get('/manifest', [ApiSyncInvitationController::class, 'manifest'])->name('manifest');
            Route::get('/datasets/{dataset}', [ApiSyncInvitationController::class, 'dataset'])
                ->where('dataset', '[a-z_]+')
                ->name('datasets.show');
            Route::get('/documents/inventory', [ApiSyncDocumentController::class, 'inventory'])
                ->name('documents.inventory');
            Route::get('/documents/{transferId}/content', [ApiSyncDocumentController::class, 'content'])
                ->whereUuid('transferId')
                ->name('documents.content');
            Route::post('/complete', [ApiSyncInvitationController::class, 'complete'])->name('complete');
        });
    });

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
