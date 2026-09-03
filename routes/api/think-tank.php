<?php

use App\Http\Controllers\Api\V1\ThinkTank\AccessLevelController;
use App\Http\Controllers\Api\V1\ThinkTank\AuthenticationController;
use App\Http\Controllers\Api\V1\ThinkTank\MeController;
use App\Http\Controllers\Api\V1\ThinkTank\MfaController;
use App\Http\Controllers\Api\V1\ThinkTank\PasswordController;
use App\Http\Controllers\Api\V1\ThinkTank\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/think-tank')
    ->name('api.v1.think-tank.')
    ->middleware(['think.tank.api.no-store', 'think.tank.api.stateful'])
    ->group(function (): void {
        Route::get('auth/session', [AuthenticationController::class, 'session'])
            ->middleware('throttle:120,1,think-tank-session')
            ->name('auth.session');
        Route::post('auth/login', [AuthenticationController::class, 'login'])
            ->middleware('throttle:10,1,think-tank-login')
            ->name('auth.login');
        Route::post('auth/password/forgot', [PasswordController::class, 'forgot'])
            ->middleware('throttle:5,1,think-tank-password-forgot')
            ->name('auth.password.forgot');
        Route::post('auth/password/reset', [PasswordController::class, 'reset'])
            ->middleware('throttle:10,1,think-tank-password-reset')
            ->name('auth.password.reset');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('auth/logout', [AuthenticationController::class, 'logout'])
                ->middleware('throttle:30,1,think-tank-logout')
                ->name('auth.logout');

            Route::middleware('think.tank.api.account')->group(function (): void {
                Route::put('auth/password', [PasswordController::class, 'update'])
                    ->middleware('throttle:10,1,think-tank-password-change')
                    ->name('auth.password.update');
                Route::post('auth/mfa/resend', [MfaController::class, 'resend'])
                    ->middleware('throttle:5,1,think-tank-mfa-resend')
                    ->name('auth.mfa.resend');
                Route::post('auth/mfa/verify', [MfaController::class, 'verify'])
                    ->middleware('throttle:10,1,think-tank-mfa-verify')
                    ->name('auth.mfa.verify');

                Route::middleware('think.tank.api.ready')->group(function (): void {
                    Route::get('me', MeController::class)->name('me');
                    Route::get('access-levels', AccessLevelController::class)->name('access-levels');

                    Route::middleware('think.tank.api.users.manage')->group(function (): void {
                        Route::get('users', [UserController::class, 'index'])->name('users.index');
                        Route::post('users', [UserController::class, 'store'])
                            ->middleware('throttle:20,1,think-tank-users-create')
                            ->name('users.store');
                        Route::get('users/{user}', [UserController::class, 'show'])
                            ->whereUuid('user')
                            ->name('users.show');
                        Route::patch('users/{user}', [UserController::class, 'update'])
                            ->whereUuid('user')
                            ->middleware('throttle:30,1,think-tank-users-update')
                            ->name('users.update');
                        Route::post('users/{user}/invitation', [UserController::class, 'invitation'])
                            ->whereUuid('user')
                            ->middleware('throttle:5,1,think-tank-users-invitation')
                            ->name('users.invitation');
                    });
                });
            });
        });
    });
