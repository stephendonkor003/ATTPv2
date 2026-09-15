<?php

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

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

it('validates the graph mail test recipient', function (): void {
    $bootedHere = bootGraphCommandApplication();

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

it('uses the graph mailer and reports acceptance only for HTTP 202', function (): void {
    bootGraphCommandApplication();
    [$certificatePath, $keyPath] = graphCommandCertificatePair(
        tap(sys_get_temp_dir().DIRECTORY_SEPARATOR.'graph-mail-command', fn (string $path) => is_dir($path) || mkdir($path, 0777, true))
    );
    config([
        'mail.from.address' => 'sender@example.test',
        'mail.from.name' => 'Graph Test',
        'services.microsoft_graph.tenant_id' => 'test-tenant',
        'services.microsoft_graph.client_id' => 'test-client',
        'services.microsoft_graph.certificate_path' => $certificatePath,
        'services.microsoft_graph.private_key_path' => $keyPath,
        'services.microsoft_graph.from_address' => 'sender@example.test',
    ]);
    app()->forgetInstance(\App\Services\Mail\MicrosoftGraphMailService::class);
    Mail::purge('graph');
    $http = new Factory;
    $http->fake([
        'login.microsoftonline.com/*' => $http->response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => $http->response('', 202),
    ]);
    app()->instance(Factory::class, $http);

    $exitCode = Artisan::call('graph-mail:test', ['recipient' => 'recipient@example.test']);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Microsoft Graph accepted the test email for recipient@example.test (HTTP 202).');
});

it('reports a safe failure when Graph rejects the test message', function (): void {
    bootGraphCommandApplication();
    [$certificatePath, $keyPath] = graphCommandCertificatePair(
        tap(sys_get_temp_dir().DIRECTORY_SEPARATOR.'graph-mail-command-failure', fn (string $path) => is_dir($path) || mkdir($path, 0777, true))
    );
    config([
        'mail.from.address' => 'sender@example.test',
        'services.microsoft_graph.tenant_id' => 'test-tenant',
        'services.microsoft_graph.client_id' => 'test-client',
        'services.microsoft_graph.certificate_path' => $certificatePath,
        'services.microsoft_graph.private_key_path' => $keyPath,
        'services.microsoft_graph.from_address' => 'sender@example.test',
    ]);
    app('cache.store')->flush();
    app()->forgetInstance(\App\Services\Mail\MicrosoftGraphMailService::class);
    Mail::purge('graph');
    $http = new Factory;
    $http->fake([
        'login.microsoftonline.com/*' => $http->response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => $http->response(['error' => ['code' => 'ErrorAccessDenied']], 403),
    ]);
    app()->instance(Factory::class, $http);

    $exitCode = Artisan::call('graph-mail:test', ['recipient' => 'recipient@example.test']);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Microsoft Graph did not accept the test email.')
        ->not->toContain('test-token');
});
