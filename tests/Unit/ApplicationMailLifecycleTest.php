<?php

use App\Models\User;
use App\Notifications\ThinkTankPortalPasswordResetNotification;
use App\Services\ThinkTank\ThinkTankMailSecurityService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

it('enables Laravel email verification on application users', function (): void {
    $user = new User;
    $user->forceFill(['email' => 'person@example.test', 'email_verified_at' => null]);

    expect($user)->toBeInstanceOf(MustVerifyEmail::class)
        ->and(method_exists($user, 'sendEmailVerificationNotification'))->toBeTrue()
        ->and(method_exists($user, 'markEmailAsVerified'))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and((new VerifyEmail)->via($user))->toBe(['mail']);
});

it('keeps standard and Think Tank resets on the Laravel notification mail channel', function (): void {
    $user = (new User)->forceFill(['email' => 'person@example.test']);
    $standard = new ResetPassword('synthetic-reset-token');
    $thinkTank = new ThinkTankPortalPasswordResetNotification('synthetic-reset-token');

    expect($standard->via($user))->toBe(['mail'])
        ->and($thinkTank->via($user))->toBe(['mail'])
        ->and($thinkTank)->toBeInstanceOf(ShouldQueueAfterCommit::class)
        ->and($thinkTank)->toBeInstanceOf(ShouldBeEncrypted::class);
});

it('recognizes Graph as secure credential delivery and rejects debug transports by source contract', function (): void {
    $security = file_get_contents(dirname(__DIR__, 2).'/app/Services/ThinkTank/ThinkTankMailSecurityService.php');
    $mail = file_get_contents(dirname(__DIR__, 2).'/config/mail.php');
    $provider = file_get_contents(dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php');

    expect($security)
        ->toContain("'graph'")
        ->toContain("['log', 'array']")
        ->and($mail)
        ->toContain("'graph' => [")
        ->toContain("'transport' => 'graph'")
        ->and($provider)
        ->toContain("Mail::extend('graph'")
        ->and((new ReflectionClass(ThinkTankMailSecurityService::class))->isInstantiable())->toBeTrue();
});
