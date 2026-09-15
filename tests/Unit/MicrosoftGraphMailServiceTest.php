<?php

use App\Services\Mail\MicrosoftGraphMailException;
use App\Services\Mail\MicrosoftGraphMailService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

function graphCertificatePair(string $directory, string $prefix = 'graph'): array
{
    $opensslConfig = $directory.DIRECTORY_SEPARATOR.'openssl.cnf';
    file_put_contents($opensslConfig, "[req]\ndistinguished_name=req_dn\nprompt=no\n[req_dn]\nCN=Graph Mail Test\n");
    $options = ['config' => $opensslConfig, 'digest_alg' => 'sha256'];
    $key = openssl_pkey_new($options + ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr = openssl_csr_new(['commonName' => 'Graph Mail Test'], $key, $options);
    $certificate = openssl_csr_sign($csr, null, $key, 1, $options);
    openssl_pkey_export($key, $privatePem, null, $options);
    openssl_x509_export($certificate, $certificatePem);

    $certificatePath = $directory.DIRECTORY_SEPARATOR.$prefix.'-certificate.pem';
    $keyPath = $directory.DIRECTORY_SEPARATOR.$prefix.'-private.pem';
    file_put_contents($certificatePath, $certificatePem);
    file_put_contents($keyPath, $privatePem);

    return [$certificatePath, $keyPath];
}

function graphService(array $overrides = []): array
{
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'graph-mail-'.bin2hex(random_bytes(4));
    mkdir($directory, 0777, true);
    [$certificatePath, $keyPath] = graphCertificatePair($directory);
    $http = new Factory;
    $cache = new Repository(new ArrayStore);
    $config = array_merge([
        'tenant_id' => 'test-tenant',
        'client_id' => 'test-client',
        'certificate_path' => $certificatePath,
        'private_key_path' => $keyPath,
        'from_address' => 'sender@example.test',
        'scope' => 'https://graph.microsoft.com/.default',
        'base_url' => 'https://graph.microsoft.com/v1.0',
        'timeout' => 5,
    ], $overrides);

    return [new MicrosoftGraphMailService($http, $cache, new NullLogger, $config), $http, $directory];
}

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'graph-mail-*'.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
        @unlink($file);
    }
    foreach (glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'graph-mail-*') ?: [] as $directory) {
        @rmdir($directory);
    }
});

it('creates a short lived RS256 client assertion with the certificate thumbprint', function (): void {
    [$service] = graphService();
    $jwt = $service->createClientAssertion(1_700_000_000);
    [$encodedHeader, $encodedClaims, $encodedSignature] = explode('.', $jwt);
    $decode = fn (string $part): array => json_decode(base64_decode(strtr($part, '-_', '+/')), true, flags: JSON_THROW_ON_ERROR);
    $header = $decode($encodedHeader);
    $claims = $decode($encodedClaims);

    expect($header)->toMatchArray(['alg' => 'RS256', 'typ' => 'JWT'])
        ->and($header['x5t'])->not->toBeEmpty()
        ->and($claims)->toMatchArray([
            'aud' => 'https://login.microsoftonline.com/test-tenant/oauth2/v2.0/token',
            'iss' => 'test-client',
            'sub' => 'test-client',
            'iat' => 1_700_000_000,
            'exp' => 1_700_000_300,
        ])
        ->and($encodedSignature)->not->toBeEmpty();
});

it('fails safely for missing Graph configuration', function (string $key, string $message): void {
    [$service] = graphService([$key => '']);

    expect(fn () => $service->createClientAssertion())->toThrow(MicrosoftGraphMailException::class, $message);
})->with([
    ['tenant_id', 'Microsoft tenant ID is not configured.'],
    ['client_id', 'Microsoft client ID is not configured.'],
    ['certificate_path', 'Microsoft certificate path is not configured.'],
    ['private_key_path', 'Microsoft private key path is not configured.'],
]);

it('rejects missing credential files', function (string $key, string $label): void {
    [$service] = graphService([$key => sys_get_temp_dir().DIRECTORY_SEPARATOR.'does-not-exist.pem']);

    expect(fn () => $service->createClientAssertion())
        ->toThrow(MicrosoftGraphMailException::class, "The configured {$label} file does not exist.");
})->with([
    ['certificate_path', 'Microsoft certificate'],
    ['private_key_path', 'Microsoft private key'],
]);

it('rejects a certificate and private key mismatch', function (): void {
    [$service, , $directory] = graphService();
    [, $otherKey] = graphCertificatePair($directory, 'other');
    [$mismatched] = graphService(['private_key_path' => $otherKey]);

    expect(fn () => $mismatched->createClientAssertion())
        ->toThrow(MicrosoftGraphMailException::class, 'certificate and private key do not match');
});

it('uses a client assertion without a client secret and sends Graph mail with all supported fields', function (): void {
    [$service, $http] = graphService();
    $http->fake([
        'login.microsoftonline.com/*' => $http->response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => $http->response('', 202),
    ]);
    $email = (new Email)
        ->from('ignored-from@example.test')
        ->to(new Address('to@example.test', 'To Person'))
        ->cc('cc@example.test')
        ->bcc('bcc@example.test')
        ->replyTo('reply@example.test')
        ->subject('Graph integration')
        ->text('Plain fallback')
        ->html('<p>HTML body</p>')
        ->attach('attachment contents', 'report.txt', 'text/plain');

    $service->send($email, Envelope::create($email));

    $http->assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), '/oauth2/v2.0/token')) {
            return true;
        }

        return $request['grant_type'] === 'client_credentials'
            && $request['client_assertion_type'] === 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer'
            && is_string($request['client_assertion'])
            && ! isset($request['client_secret']);
    });
    $http->assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/users/sender%40example.test/sendMail')
        && $request->hasHeader('Authorization', 'Bearer test-token')
        && $request['message']['body']['contentType'] === 'HTML'
        && $request['message']['toRecipients'][0]['emailAddress']['name'] === 'To Person'
        && $request['message']['ccRecipients'][0]['emailAddress']['address'] === 'cc@example.test'
        && $request['message']['bccRecipients'][0]['emailAddress']['address'] === 'bcc@example.test'
        && $request['message']['replyTo'][0]['emailAddress']['address'] === 'reply@example.test'
        && $request['message']['attachments'][0]['contentBytes'] === base64_encode('attachment contents'));
});

it('reports Microsoft authentication failures without exposing response secrets', function (): void {
    [$service, $http] = graphService();
    $http->fake(['login.microsoftonline.com/*' => $http->response([
        'error' => ['code' => 'invalid_client', 'message' => 'secret server detail'],
    ], 401)]);
    $email = (new Email)->from('from@example.test')->to('to@example.test')->subject('Test')->text('Body');

    expect(fn () => $service->send($email, Envelope::create($email)))
        ->toThrow(MicrosoftGraphMailException::class, 'authentication failed with HTTP 401');
});

it('requires Graph HTTP 202 for successful delivery', function (int $status): void {
    [$service, $http] = graphService();
    $http->fake([
        'login.microsoftonline.com/*' => $http->response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => $http->response(['error' => ['code' => 'ErrorAccessDenied']], $status),
    ]);
    $email = (new Email)->from('from@example.test')->to('to@example.test')->subject('Test')->text('Body');

    expect(fn () => $service->send($email, Envelope::create($email)))
        ->toThrow(MicrosoftGraphMailException::class, "sendMail failed with HTTP {$status}");
})->with([400, 401, 403, 500]);
