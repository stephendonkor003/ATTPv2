<?php

use App\Services\Mail\MicrosoftGraphMailService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Psr\Log\NullLogger;

function bootGraphCommandApplication(): bool
{
    if (app() instanceof \Illuminate\Foundation\Application) {
        return false;
    }

    $application = require dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';
    $application->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    return true;
}

function graphCommandCertificatePair(string $directory): array
{
    $opensslConfig = $directory.DIRECTORY_SEPARATOR.'openssl.cnf';
    file_put_contents($opensslConfig, "[req]\ndistinguished_name=req_dn\nprompt=no\n[req_dn]\nCN=Graph Command Test\n");
    $options = ['config' => $opensslConfig, 'digest_alg' => 'sha256'];
    $key = openssl_pkey_new($options + ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr = openssl_csr_new(['commonName' => 'Graph Command Test'], $key, $options);
    $certificate = openssl_csr_sign($csr, null, $key, 1, $options);
    openssl_pkey_export($key, $privatePem, null, $options);
    openssl_x509_export($certificate, $certificatePem);
    $certificatePath = $directory.DIRECTORY_SEPARATOR.'certificate.pem';
    $keyPath = $directory.DIRECTORY_SEPARATOR.'private.pem';
    file_put_contents($certificatePath, $certificatePem);
    file_put_contents($keyPath, $privatePem);

    return [$certificatePath, $keyPath];
}

function bindIsolatedGraphCommandService(string $suffix): array
{
    bootGraphCommandApplication();
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'graph-mail-command-'.$suffix.'-'.bin2hex(random_bytes(4));
    is_dir($directory) || mkdir($directory, 0777, true);
    [$certificatePath, $keyPath] = graphCommandCertificatePair($directory);
    config([
        'mail.default' => 'graph',
        'mail.mailers.graph.transport' => 'graph',
        'mail.from.address' => 'sender@example.test',
        'mail.from.name' => 'Graph Test',
        'services.microsoft_graph' => [
            'tenant_id' => 'test-tenant',
            'client_id' => 'test-client',
            'certificate_path' => $certificatePath,
            'private_key_path' => $keyPath,
            'from_address' => 'sender@example.test',
            'scope' => 'https://graph.microsoft.com/.default',
            'base_url' => 'https://graph.microsoft.com/v1.0',
            'timeout' => 5,
            'connect_timeout' => 2,
            'retry_attempts' => 0,
            'retry_max_delay_ms' => 0,
        ],
    ]);

    $http = new Factory;
    $http->preventStrayRequests();
    $cache = new Repository(new ArrayStore);
    $service = new MicrosoftGraphMailService(
        $http,
        $cache,
        new NullLogger,
        new Encrypter(str_repeat('c', 32), 'AES-256-CBC'),
        (array) config('services.microsoft_graph'),
        static function (int $milliseconds): void {},
    );

    app()->instance(MicrosoftGraphMailService::class, $service);
    Mail::purge('graph');

    return [$http, $cache, $directory];
}

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'graph-mail-command-*'.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
        @unlink($file);
    }
    foreach (glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'graph-mail-command-*') ?: [] as $directory) {
        @rmdir($directory);
    }
});

it('validates the graph mail test recipient', function (): void {
    $bootedHere = bootGraphCommandApplication();
    config(['mail.default' => 'graph']);

    try {
        $exitCode = Artisan::call('graph-mail:test', ['recipient' => 'not-an-email']);

        expect($exitCode)->toBe(2)
            ->and(Artisan::output())->toContain('The recipient must be a valid email address.');
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('uses the graph mailer and reports HTTP 202 as acceptance only', function (): void {
    [$http] = bindIsolatedGraphCommandService('success');
    $http->fake([
        'login.microsoftonline.com/*' => $http->response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => $http->response('', 202),
    ]);

    $exitCode = Artisan::call('graph-mail:test', ['recipient' => 'recipient@example.test']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Microsoft Graph accepted the test email request (HTTP 202).')
        ->toContain('does not confirm inbox delivery')
        ->not->toContain('recipient@example.test');
});

it('reports a safe failure when Graph rejects the test message', function (): void {
    [$http] = bindIsolatedGraphCommandService('failure');
    $http->fake([
        'login.microsoftonline.com/*' => $http->response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => $http->response(['error' => ['code' => 'ErrorAccessDenied']], 403),
    ]);

    $exitCode = Artisan::call('graph-mail:test', ['recipient' => 'recipient@example.test']);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Microsoft Graph did not accept the test email.')
        ->not->toContain('test-token')
        ->not->toContain('recipient@example.test');
});

it('refuses a Graph test when Graph is not the default mailer', function (): void {
    [$http] = bindIsolatedGraphCommandService('wrong-default');
    config(['mail.default' => 'log']);

    $exitCode = Artisan::call('graph-mail:test', ['recipient' => 'recipient@example.test']);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('default mailer is not configured as graph')
        ->toContain('No email was sent.')
        ->not->toContain('recipient@example.test');
    $http->assertNothingSent();
});

it('checks Graph configuration without a network request or sensitive output', function (): void {
    [$http] = bindIsolatedGraphCommandService('check');

    $exitCode = Artisan::call('graph-mail:check');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Graph mail configuration check passed.')
        ->toContain('No network request was made and no email was sent.')
        ->not->toContain('sender@example.test')
        ->not->toContain('test-tenant')
        ->not->toContain('test-client');
    $http->assertNothingSent();
});

it('can authenticate during a Graph check without sending mail or printing secrets', function (): void {
    [$http] = bindIsolatedGraphCommandService('authenticate');
    $http->fake(function (Request $request) use ($http) {
        if (str_contains($request->url(), '/oauth2/v2.0/token')) {
            return $http->response(['access_token' => 'test-token', 'expires_in' => 3600]);
        }

        return $http->response(['error' => ['code' => 'UnexpectedRequest']], 500);
    });

    $exitCode = Artisan::call('graph-mail:check', ['--authenticate' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Microsoft Graph authentication succeeded. No email was sent.')
        ->not->toContain('test-token')
        ->not->toContain('sender@example.test')
        ->not->toContain('test-tenant')
        ->not->toContain('test-client');
    $http->assertSentCount(1);
    $http->assertSent(fn (Request $request): bool => str_contains($request->url(), '/oauth2/v2.0/token'));
});
