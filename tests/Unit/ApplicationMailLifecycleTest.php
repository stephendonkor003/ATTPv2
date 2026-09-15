<?php

use App\Models\User;
use App\Notifications\ApplicationPasswordResetNotification;
use App\Notifications\ApplicationVerifyEmailNotification;
use App\Notifications\ThinkTankPortalPasswordResetNotification;
use App\Services\ThinkTank\ThinkTankMailSecurityService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;

it('enables Laravel email verification on application users', function (): void {
    $user = new User;
    $user->forceFill(['email' => 'person@example.test', 'email_verified_at' => null]);

    expect($user)->toBeInstanceOf(MustVerifyEmail::class)
        ->and(method_exists($user, 'sendEmailVerificationNotification'))->toBeTrue()
        ->and(method_exists($user, 'markEmailAsVerified'))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and((new ApplicationVerifyEmailNotification)->via($user))->toBe(['mail']);
});

it('keeps standard and Think Tank resets on the Laravel notification mail channel', function (): void {
    $user = (new User)->forceFill(['email' => 'person@example.test']);
    $standard = new ApplicationPasswordResetNotification('synthetic-reset-token');
    $thinkTank = new ThinkTankPortalPasswordResetNotification('synthetic-reset-token');

    expect($standard->via($user))->toBe(['mail'])
        ->and($thinkTank->via($user))->toBe(['mail'])
        ->and($thinkTank)->toBeInstanceOf(ShouldBeEncrypted::class);
});

it('keeps public and administrator reset flows normalized and free of plaintext passwords', function (): void {
    $root = dirname(__DIR__, 2);
    $requestController = file_get_contents($root.'/app/Http/Controllers/Auth/PasswordResetLinkController.php');
    $resetController = file_get_contents($root.'/app/Http/Controllers/Auth/NewPasswordController.php');
    $adminController = file_get_contents($root.'/app/Http/Controllers/System/UserAccessController.php');

    expect($requestController)->toContain("\$request->merge(['email' => \$normalizedEmail])")
        ->and($resetController)->toContain("\$request->merge(['email' => \$email])")
        ->and($adminController)
        ->toContain('ApplicationPasswordResetNotification')
        ->toContain('The current password remains unchanged')
        ->not->toContain('new UserPasswordReset');
});

it('provides the branded application mail header body and footer', function (): void {
    $root = dirname(__DIR__, 2);
    $header = file_get_contents($root.'/resources/views/vendor/mail/html/header.blade.php');
    $footer = file_get_contents($root.'/resources/views/vendor/mail/html/footer.blade.php');
    $theme = file_get_contents($root.'/resources/views/vendor/mail/html/themes/default.css');

    expect($header)->toContain('Africa Think Tank Platform')
        ->and($footer)->toContain('Never share your password or verification code')
        ->and($theme)
        ->toContain('.brand-header')
        ->toContain('.button-primary');
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
