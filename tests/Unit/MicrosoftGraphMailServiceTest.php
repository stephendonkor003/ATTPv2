<?php

use App\Services\Mail\MicrosoftGraphMailException;
use App\Services\Mail\MicrosoftGraphMailService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

function graphCertificatePair(string $directory, string $prefix = 'graph', int $bits = 2048): array
{
    $opensslConfig = $directory.DIRECTORY_SEPARATOR.'openssl.cnf';
    file_put_contents($opensslConfig, "[req]\ndistinguished_name=req_dn\nprompt=no\n[req_dn]\nCN=Graph Mail Test\n");
    $options = ['config' => $opensslConfig, 'digest_alg' => 'sha256'];
    $key = openssl_pkey_new($options + ['private_key_bits' => $bits, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
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

function graphService(
    array $overrides = [],
    ?Repository $cache = null,
    ?Closure $sleep = null,
    ?Closure $clock = null,
    ?LoggerInterface $logger = null,
): array {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'graph-mail-'.bin2hex(random_bytes(4));
    mkdir($directory, 0777, true);
    [$certificatePath, $keyPath] = graphCertificatePair($directory);
    $http = new Factory;
    $http->preventStrayRequests();
    $cache ??= new Repository(new ArrayStore);
    $encrypter = new Encrypter(str_repeat('g', 32), 'AES-256-CBC');
    $config = array_merge([
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
        'retry_max_delay_ms' => 25,
    ], $overrides);

    return [
        new MicrosoftGraphMailService(
            $http,
            $cache,
            $logger ?? new NullLogger,
            $encrypter,
            $config,
            $sleep,
            $clock,
        ),
        $http,
        $directory,
        $cache,
        $encrypter,
    ];
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
    $now = time();
    $jwt = $service->createClientAssertion($now);
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
            'iat' => $now,
            'exp' => $now + 300,
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

it('rejects an unreadable configured Graph CA bundle before authentication', function (): void {
    [$service] = graphService([
        'ca_bundle' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'missing-graph-ca-bundle.pem',
    ]);

    expect(fn () => $service->validateConfiguration('sender@example.test'))
        ->toThrow(MicrosoftGraphMailException::class, 'CA bundle is not a readable file');
});

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

it('encrypts cached access tokens and keys them by certificate fingerprint', function (): void {
    $cache = new Repository(new ArrayStore);
    [$first, $firstHttp] = graphService(cache: $cache);
    [$second, $secondHttp] = graphService(cache: $cache);

    foreach ([[$first, $firstHttp, 'first-token'], [$second, $secondHttp, 'second-token']] as [$service, $http, $token]) {
        $http->fake([
            'login.microsoftonline.com/*' => $http->response(['access_token' => $token, 'expires_in' => 3600]),
            'graph.microsoft.com/*' => $http->response('', 202),
        ]);
        $email = (new Email)->from('sender@example.test')->to('recipient@example.test')->subject('Test')->text('Body');
        $service->send($email, Envelope::create($email));
    }

    $stored = $cache->getStore()->all();
    $values = array_column(array_values($stored), 'value');

    expect($stored)->toHaveCount(2)
        ->and(implode('|', $values))->not->toContain('first-token')
        ->not->toContain('second-token');
});

it('evicts an unreadable cached token and authenticates again safely', function (): void {
    [$service, $http, , $cache, $encrypter] = graphService();
    $authenticationAttempts = 0;

    $http->fake(function (Request $request) use ($http, &$authenticationAttempts) {
        if (str_contains($request->url(), '/oauth2/v2.0/token')) {
            $authenticationAttempts++;

            return $http->response(['access_token' => 'token-'.$authenticationAttempts, 'expires_in' => 3600]);
        }

        return $http->response('', 202);
    });

    $email = (new Email)->from('sender@example.test')->to('recipient@example.test')->subject('Test')->text('Body');
    $service->send($email, Envelope::create($email));
    $cacheKey = array_key_first($cache->getStore()->all());
    $cache->put($cacheKey, 'not-an-encrypted-token', 3600);
    $service->send($email, Envelope::create($email));

    expect($authenticationAttempts)->toBe(2)
        ->and($encrypter->decryptString((string) $cache->get($cacheKey)))->toBe('token-2');
});

it('refreshes the token once after a Graph 401 and retries the message once', function (): void {
    [$service, $http] = graphService();
    $authenticationAttempts = 0;
    $sendAttempts = 0;
    $authorizationHeaders = [];

    $http->fake(function (Request $request) use ($http, &$authenticationAttempts, &$sendAttempts, &$authorizationHeaders) {
        if (str_contains($request->url(), '/oauth2/v2.0/token')) {
            $authenticationAttempts++;

            return $http->response(['access_token' => 'token-'.$authenticationAttempts, 'expires_in' => 3600]);
        }

        $sendAttempts++;
        $authorizationHeaders[] = $request->header('Authorization')[0] ?? null;

        return $http->response('', $sendAttempts === 1 ? 401 : 202);
    });

    $email = (new Email)->from('sender@example.test')->to('recipient@example.test')->subject('Test')->text('Body');
    $service->send($email, Envelope::create($email));

    expect($authenticationAttempts)->toBe(2)
        ->and($sendAttempts)->toBe(2)
        ->and($authorizationHeaders)->toBe(['Bearer token-1', 'Bearer token-2']);
});

it('does not retry a second Graph 401', function (): void {
    [$service, $http] = graphService();
    $authenticationAttempts = 0;
    $sendAttempts = 0;

    $http->fake(function (Request $request) use ($http, &$authenticationAttempts, &$sendAttempts) {
        if (str_contains($request->url(), '/oauth2/v2.0/token')) {
            $authenticationAttempts++;

            return $http->response(['access_token' => 'token-'.$authenticationAttempts, 'expires_in' => 3600]);
        }

        $sendAttempts++;

        return $http->response('', 401);
    });

    $email = (new Email)->from('sender@example.test')->to('recipient@example.test')->subject('Test')->text('Body');

    expect(fn () => $service->send($email, Envelope::create($email)))
        ->toThrow(MicrosoftGraphMailException::class, 'sendMail failed with HTTP 401');
    expect($authenticationAttempts)->toBe(2)
        ->and($sendAttempts)->toBe(2);
});

it('retries only an explicit Graph throttle response and caps Retry-After', function (): void {
    $delays = [];
    [$service, $http] = graphService(
        ['retry_attempts' => 2, 'retry_max_delay_ms' => 25],
        sleep: function (int $milliseconds) use (&$delays): void {
            $delays[] = $milliseconds;
        },
    );
    $sendAttempts = 0;

    $http->fake(function (Request $request) use ($http, &$sendAttempts) {
        if (str_contains($request->url(), '/oauth2/v2.0/token')) {
            return $http->response(['access_token' => 'test-token', 'expires_in' => 3600]);
        }

        $sendAttempts++;

        return $sendAttempts === 1
            ? $http->response(['error' => ['code' => 'TooManyRequests']], 429, ['Retry-After' => '60'])
            : $http->response('', 202);
    });

    $email = (new Email)->from('sender@example.test')->to('recipient@example.test')->subject('Test')->text('Body');
    $service->send($email, Envelope::create($email));

    expect($sendAttempts)->toBe(2)
        ->and($delays)->toBe([25]);
});

it('does not retry a non-idempotent sendMail request after a server error', function (): void {
    [$service, $http] = graphService(['retry_attempts' => 3]);
    $sendAttempts = 0;

    $http->fake(function (Request $request) use ($http, &$sendAttempts) {
        if (str_contains($request->url(), '/oauth2/v2.0/token')) {
            return $http->response(['access_token' => 'test-token', 'expires_in' => 3600]);
        }

        $sendAttempts++;

        return $http->response(['error' => ['code' => 'ServiceUnavailable']], 503);
    });

    $email = (new Email)->from('sender@example.test')->to('recipient@example.test')->subject('Test')->text('Body');

    expect(fn () => $service->send($email, Envelope::create($email)))
        ->toThrow(MicrosoftGraphMailException::class, 'sendMail failed with HTTP 503');
    expect($sendAttempts)->toBe(1);
});

it('rejects invalid sender addresses before making a network request', function (): void {
    [$service, $http] = graphService(['from_address' => 'not-an-email']);
    $email = (new Email)->from('sender@example.test')->to('recipient@example.test')->subject('Test')->text('Body');

    expect(fn () => $service->send($email, Envelope::create($email)))
        ->toThrow(MicrosoftGraphMailException::class, 'sender address is invalid');
    $http->assertNothingSent();
});

it('rejects a global sender that does not match the Graph sender', function (): void {
    [$service, $http] = graphService();

    expect(fn () => $service->validateConfiguration('different@example.test'))
        ->toThrow(MicrosoftGraphMailException::class, 'does not match the Microsoft Graph sender address');
    $http->assertNothingSent();
});

it('rejects certificates outside their validity period', function (string $boundary, string $message): void {
    $clock = time();
    [$service, , $directory] = graphService(clock: function () use (&$clock): int {
        return $clock;
    });
    $certificate = openssl_x509_read((string) file_get_contents($directory.DIRECTORY_SEPARATOR.'graph-certificate.pem'));
    $metadata = openssl_x509_parse($certificate);
    $clock = $boundary === 'before'
        ? (int) $metadata['validFrom_time_t'] - 1
        : (int) $metadata['validTo_time_t'] + 1;

    expect(fn () => $service->createClientAssertion())
        ->toThrow(MicrosoftGraphMailException::class, $message);
})->with([
    ['before', 'certificate is not yet valid'],
    ['after', 'certificate has expired'],
]);

it('rejects RSA keys below 2048 bits', function (): void {
    [, , $directory] = graphService();
    [$certificatePath, $keyPath] = graphCertificatePair($directory, 'weak', 1024);
    [$service] = graphService([
        'certificate_path' => $certificatePath,
        'private_key_path' => $keyPath,
    ]);

    expect(fn () => $service->createClientAssertion())
        ->toThrow(MicrosoftGraphMailException::class, 'at least 2048 bits');
});

it('rejects JSON mail requests larger than four megabytes before authentication', function (): void {
    [$service, $http] = graphService();
    $email = (new Email)
        ->from('sender@example.test')
        ->to('recipient@example.test')
        ->subject('Large request')
        ->text(str_repeat('x', (4 * 1024 * 1024) + 1));

    expect(fn () => $service->send($email, Envelope::create($email)))
        ->toThrow(MicrosoftGraphMailException::class, 'exceeds the 4 MB JSON limit');
    $http->assertNothingSent();
});

it('logs only sanitized Graph failure diagnostics', function (): void {
    $logger = new class extends AbstractLogger
    {
        public array $records = [];

        public function log($level, string|\Stringable $message, array $context = []): void
        {
            $this->records[] = compact('level', 'message', 'context');
        }
    };
    [$service, $http] = graphService(logger: $logger);
    $http->fake([
        'login.microsoftonline.com/*' => $http->response(['access_token' => 'sensitive-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => $http->response([
            'error' => ['code' => 'ErrorAccessDenied', 'message' => 'sensitive server detail'],
        ], 403, ['request-id' => 'sensitive-request-id']),
    ]);
    $email = (new Email)->from('sender@example.test')->to('recipient@example.test')->subject('Test')->text('Body');

    try {
        $service->send($email, Envelope::create($email));
    } catch (MicrosoftGraphMailException) {
        // Expected.
    }

    $logged = json_encode($logger->records, JSON_THROW_ON_ERROR);
    expect($logged)->toContain('ErrorAccessDenied')
        ->not->toContain('sensitive-token')
        ->not->toContain('sensitive server detail')
        ->not->toContain('sensitive-request-id')
        ->not->toContain('sender@example.test')
        ->not->toContain('recipient@example.test');
});
