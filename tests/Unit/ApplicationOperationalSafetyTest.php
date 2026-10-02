<?php

it('does not erase application caches on a recurring schedule', function (): void {
    $bootstrap = file_get_contents(dirname(__DIR__, 2).'/bootstrap/app.php');

    expect($bootstrap)
        ->not->toContain("schedule->command('optimize:clear')")
        ->toContain('dontReportWhen(')
        ->toContain('$exception->status < 500');
});

it('keeps queued Graph mail inside a safe retry timing envelope', function (): void {
    $queue = require dirname(__DIR__, 2).'/config/queue.php';
    $welcome = new ReflectionClass(\App\Mail\ThinkTankPortalWelcome::class);
    $defaults = $welcome->getDefaultProperties();

    expect($queue['connections']['database']['after_commit'])->toBeTrue()
        ->and($queue['connections']['database']['retry_after'])->toBeGreaterThan($defaults['timeout'])
        ->and($defaults['timeout'])->toBeGreaterThan(30)
        ->and($defaults['tries'])->toBeGreaterThan(1);
});

it('fails closed to Graph instead of logging account recovery secrets when the mailer is omitted', function (): void {
    $mailConfiguration = file_get_contents(dirname(__DIR__, 2).'/config/mail.php');

    expect($mailConfiguration)
        ->toContain("'default' => \$resolveMailValue(['MAIL_MAILER'], 'graph')")
        ->not->toContain("'default' => \$resolveMailValue(['MAIL_MAILER'], 'log')");
});
