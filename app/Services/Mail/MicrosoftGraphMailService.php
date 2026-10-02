<?php

namespace App\Services\Mail;

use Closure;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Throwable;

final class MicrosoftGraphMailService
{
    private const ASSERTION_LIFETIME_SECONDS = 300;

    private const MAX_JSON_REQUEST_BYTES = 4 * 1024 * 1024;

    private const MINIMUM_RSA_BITS = 2048;

    private const MAX_TRANSIENT_RETRIES = 3;

    private const MAX_RETRY_DELAY_MILLISECONDS = 5_000;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly CacheRepository $cache,
        private readonly LoggerInterface $logger,
        private readonly Encrypter $encrypter,
        private readonly array $config,
        private readonly ?Closure $sleep = null,
        private readonly ?Closure $clock = null,
    ) {}

    public function send(Email $email, Envelope $envelope): void
    {
        $sender = $this->senderAddress();
        $url = rtrim($this->requiredConfig('base_url', 'Microsoft Graph base URL'), '/')
            .'/users/'.rawurlencode($sender).'/sendMail';
        $payload = ['message' => $this->messagePayload($email, $envelope), 'saveToSentItems' => true];
        $this->assertRequestSize($payload);
        $cacheKey = $this->tokenCacheKey();
        $response = $this->sendMailRequest($url, $payload, $this->accessToken($cacheKey));

        if ($response->status() === 401) {
            $this->cache->forget($cacheKey);
            $this->logger->warning('Microsoft Graph rejected the mail access token; refreshing once.', [
                'operation' => 'sendMail',
                'status' => 401,
            ]);
            $response = $this->sendMailRequest($url, $payload, $this->accessToken($cacheKey));
        }

        if ($response->status() !== 202) {
            $this->throwHttpFailure('sendMail', $response);
        }
    }

    public function validateConfiguration(?string $mailFromAddress = null): void
    {
        $sender = $this->senderAddress();
        $this->requiredConfig('tenant_id', 'Microsoft tenant ID');
        $this->requiredConfig('client_id', 'Microsoft client ID');
        $this->requiredConfig('base_url', 'Microsoft Graph base URL');

        if ($mailFromAddress !== null) {
            $mailFromAddress = trim($mailFromAddress);

            if (filter_var($mailFromAddress, FILTER_VALIDATE_EMAIL) === false) {
                throw new MicrosoftGraphMailException('The global mail sender address is invalid.');
            }

            if (strcasecmp($sender, $mailFromAddress) !== 0) {
                throw new MicrosoftGraphMailException('The global mail sender address does not match the Microsoft Graph sender address.');
            }
        }

        $certificate = $this->loadCertificate();
        $privateKey = $this->loadPrivateKey();
        $this->assertCertificateMatchesPrivateKey($certificate, $privateKey);
        $this->caBundlePath();
    }

    public function authenticate(): void
    {
        $cacheKey = $this->tokenCacheKey();
        $this->accessToken($cacheKey, false);
    }

    public function createClientAssertion(?int $now = null): string
    {
        $tenantId = $this->requiredConfig('tenant_id', 'Microsoft tenant ID');
        $clientId = $this->requiredConfig('client_id', 'Microsoft client ID');
        $timestamp = $now ?? $this->now();
        $certificate = $this->loadCertificate($timestamp);
        $privateKey = $this->loadPrivateKey();
        $this->assertCertificateMatchesPrivateKey($certificate, $privateKey);
        $audience = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
        $header = ['alg' => 'RS256', 'typ' => 'JWT', 'x5t' => $this->certificateThumbprint($certificate)];
        $claims = [
            'aud' => $audience,
            'iss' => $clientId,
            'sub' => $clientId,
            'jti' => bin2hex(random_bytes(16)),
            'iat' => $timestamp,
            'nbf' => $timestamp - 5,
            'exp' => $timestamp + self::ASSERTION_LIFETIME_SECONDS,
        ];
        $unsigned = $this->base64Url((string) json_encode($header, JSON_THROW_ON_ERROR))
            .'.'.$this->base64Url((string) json_encode($claims, JSON_THROW_ON_ERROR));

        if (! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new MicrosoftGraphMailException('Unable to sign the Microsoft client assertion.');
        }

        return $unsigned.'.'.$this->base64Url($signature);
    }

    private function accessToken(string $cacheKey, bool $useCachedToken = true): string
    {
        $tenantId = $this->requiredConfig('tenant_id', 'Microsoft tenant ID');
        $clientId = $this->requiredConfig('client_id', 'Microsoft client ID');
        $cached = $this->cache->get($cacheKey);

        if ($useCachedToken && is_string($cached) && $cached !== '') {
            try {
                $token = $this->encrypter->decryptString($cached);

                if ($token !== '') {
                    return $token;
                }
            } catch (Throwable $exception) {
                $this->logger->warning('Discarded an unreadable Microsoft Graph mail token cache entry.', [
                    'operation' => 'token_cache',
                    'exception' => $exception::class,
                ]);
            }

            $this->cache->forget($cacheKey);
        }

        $url = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
        $response = $this->requestWithTransientRetries('authentication', function () use ($url, $clientId): Response {
            return $this->http
                ->asForm()
                ->acceptJson()
                ->timeout($this->timeout())
                ->connectTimeout($this->connectTimeout())
                ->withOptions($this->httpOptions())
                ->post($url, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'scope' => (string) ($this->config['scope'] ?? 'https://graph.microsoft.com/.default'),
                    'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
                    'client_assertion' => $this->createClientAssertion(),
                ]);
        });

        if (! $response->successful()) {
            $this->throwHttpFailure('authentication', $response);
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new MicrosoftGraphMailException('Microsoft authentication returned no access token.');
        }

        $expiresIn = max(60, (int) $response->json('expires_in', 3600));

        try {
            $encryptedToken = $this->encrypter->encryptString($token);
        } catch (Throwable $exception) {
            $this->logger->error('Microsoft Graph mail token encryption failed.', [
                'operation' => 'token_cache',
                'exception' => $exception::class,
            ]);

            throw new MicrosoftGraphMailException('Unable to protect the Microsoft Graph access token cache.', previous: $exception);
        }

        $this->cache->put($cacheKey, $encryptedToken, max(1, $expiresIn - 120));

        return $token;
    }

    private function sendMailRequest(string $url, array $payload, string $token): Response
    {
        $maximumRetries = $this->transientRetries();

        for ($attempt = 0; ; $attempt++) {
            try {
                $response = $this->http
                    ->withToken($token)
                    ->acceptJson()
                    ->asJson()
                    ->timeout($this->timeout())
                    ->connectTimeout($this->connectTimeout())
                    ->withOptions($this->httpOptions())
                    ->post($url, $payload);
            } catch (ConnectionException $exception) {
                // sendMail is not idempotent. A lost response may mean Graph
                // accepted the message, so an automatic retry could deliver it
                // twice. Surface the uncertain result for an explicit decision.
                $this->throwConnectionFailure('sendMail', $exception);
            }

            if ($response->status() !== 429 || $attempt >= $maximumRetries) {
                return $response;
            }

            // A concrete 429 response means Graph rejected the request before
            // accepting the message, making this the only safe automatic POST retry.
            $delay = $this->retryDelayMilliseconds($response, $attempt + 1);
            $this->logRetry('sendMail', $response, $attempt + 1, $delay);
            $this->pause($delay);
        }
    }

    private function requestWithTransientRetries(string $operation, Closure $request): Response
    {
        $maximumRetries = $this->transientRetries();

        for ($attempt = 0; ; $attempt++) {
            try {
                $response = $request();
            } catch (ConnectionException $exception) {
                if ($attempt >= $maximumRetries) {
                    $this->throwConnectionFailure($operation, $exception);
                }

                $delay = $this->fallbackRetryDelayMilliseconds($attempt + 1);
                $this->logRetry($operation, null, $attempt + 1, $delay);
                $this->pause($delay);

                continue;
            }

            if (! $this->isTransientStatus($response->status()) || $attempt >= $maximumRetries) {
                return $response;
            }

            $delay = $this->retryDelayMilliseconds($response, $attempt + 1);
            $this->logRetry($operation, $response, $attempt + 1, $delay);
            $this->pause($delay);
        }
    }

    private function messagePayload(Email $email, Envelope $envelope): array
    {
        $to = $email->getTo();
        if ($to === [] && $email->getCc() === [] && $email->getBcc() === []) {
            $to = $envelope->getRecipients();
        }

        $html = $email->getHtmlBody();
        $text = $email->getTextBody();
        $isHtml = is_string($html) && $html !== '';

        return array_filter([
            'subject' => (string) ($email->getSubject() ?? ''),
            'body' => [
                'contentType' => $isHtml ? 'HTML' : 'Text',
                'content' => $this->bodyToString($isHtml ? $html : $text),
            ],
            'toRecipients' => $this->recipients($to),
            'ccRecipients' => $this->recipients($email->getCc()),
            'bccRecipients' => $this->recipients($email->getBcc()),
            'replyTo' => $this->recipients($email->getReplyTo()),
            'attachments' => $this->attachments($email->getAttachments()),
        ], static fn (mixed $value): bool => $value !== []);
    }

    /** @param Address[] $addresses */
    private function recipients(array $addresses): array
    {
        return array_map(static fn (Address $address): array => [
            'emailAddress' => array_filter([
                'address' => $address->getAddress(),
                'name' => $address->getName(),
            ], static fn (string $value): bool => $value !== ''),
        ], $addresses);
    }

    /** @param DataPart[] $attachments */
    private function attachments(array $attachments): array
    {
        return array_map(function (DataPart $attachment): array {
            $inline = $attachment->getDisposition() === 'inline';

            return array_filter([
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $attachment->getFilename() ?? 'attachment',
                'contentType' => $attachment->getContentType(),
                'contentBytes' => base64_encode($this->bodyToString($attachment->getBody())),
                'isInline' => $inline,
                'contentId' => $inline ? $attachment->getContentId() : null,
            ], static fn (mixed $value): bool => $value !== null);
        }, $attachments);
    }

    private function loadCertificate(?int $now = null): mixed
    {
        $contents = $this->readCredentialFile('certificate_path', 'Microsoft certificate');
        $certificate = openssl_x509_read($contents);

        if ($certificate === false) {
            throw new MicrosoftGraphMailException('The configured Microsoft certificate is invalid.');
        }

        $metadata = openssl_x509_parse($certificate);
        if (! is_array($metadata)) {
            throw new MicrosoftGraphMailException('The configured Microsoft certificate metadata is invalid.');
        }

        $timestamp = $now ?? $this->now();
        $validFrom = (int) ($metadata['validFrom_time_t'] ?? 0);
        $validUntil = (int) ($metadata['validTo_time_t'] ?? 0);

        if ($validFrom <= 0 || $timestamp < $validFrom) {
            throw new MicrosoftGraphMailException('The configured Microsoft certificate is not yet valid.');
        }

        if ($validUntil <= 0 || $timestamp >= $validUntil) {
            throw new MicrosoftGraphMailException('The configured Microsoft certificate has expired.');
        }

        $publicKey = openssl_pkey_get_public($certificate);
        $details = $publicKey === false ? false : openssl_pkey_get_details($publicKey);

        if (! is_array($details)
            || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA
            || (int) ($details['bits'] ?? 0) < self::MINIMUM_RSA_BITS) {
            throw new MicrosoftGraphMailException('The configured Microsoft certificate must use an RSA key of at least 2048 bits.');
        }

        return $certificate;
    }

    private function loadPrivateKey(): mixed
    {
        $contents = $this->readCredentialFile('private_key_path', 'Microsoft private key');
        $key = openssl_pkey_get_private($contents);

        if ($key === false) {
            throw new MicrosoftGraphMailException('The configured Microsoft private key is invalid or encrypted with an unsupported password.');
        }

        $details = openssl_pkey_get_details($key);
        if (! is_array($details)
            || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA
            || (int) ($details['bits'] ?? 0) < self::MINIMUM_RSA_BITS) {
            throw new MicrosoftGraphMailException('The configured Microsoft private key must use RSA with at least 2048 bits.');
        }

        return $key;
    }

    private function assertCertificateMatchesPrivateKey(mixed $certificate, mixed $privateKey): void
    {
        if (! openssl_x509_check_private_key($certificate, $privateKey)) {
            throw new MicrosoftGraphMailException('The configured Microsoft certificate and private key do not match.');
        }
    }

    private function readCredentialFile(string $key, string $label): string
    {
        $path = $this->requiredConfig($key, $label.' path');
        if (! is_file($path)) {
            throw new MicrosoftGraphMailException("The configured {$label} file does not exist.");
        }
        if (! is_readable($path)) {
            throw new MicrosoftGraphMailException("The configured {$label} file is not readable.");
        }

        $contents = file_get_contents($path);
        if (! is_string($contents) || $contents === '') {
            throw new MicrosoftGraphMailException("The configured {$label} file could not be read.");
        }

        return $contents;
    }

    private function certificateThumbprint(mixed $certificate): string
    {
        if (! openssl_x509_export($certificate, $pem)) {
            throw new MicrosoftGraphMailException('Unable to read the Microsoft certificate thumbprint.');
        }

        $der = base64_decode((string) preg_replace('/-----[^-]+-----|\s+/', '', $pem), true);
        if ($der === false) {
            throw new MicrosoftGraphMailException('Unable to decode the Microsoft certificate.');
        }

        return $this->base64Url(hash('sha1', $der, true));
    }

    private function throwHttpFailure(string $operation, Response $response): never
    {
        $context = [
            'operation' => $operation,
            'status' => $response->status(),
            'error_code' => $this->safeDiagnosticValue($response->json('error.code')),
        ];
        $this->logger->error('Microsoft Graph mail request failed.', array_filter($context));

        throw new MicrosoftGraphMailException(
            "Microsoft Graph {$operation} failed with HTTP {$response->status()}. Check the application log for sanitized diagnostics."
        );
    }

    private function throwConnectionFailure(string $operation, ConnectionException $exception): never
    {
        $this->logger->error('Microsoft Graph mail connection failed.', [
            'operation' => $operation,
            'exception' => $exception::class,
        ]);

        throw new MicrosoftGraphMailException(
            "Microsoft Graph {$operation} could not connect. Check the application log for sanitized diagnostics.",
            previous: $exception,
        );
    }

    private function requiredConfig(string $key, string $label): string
    {
        $value = trim((string) ($this->config[$key] ?? ''));
        if ($value === '') {
            throw new MicrosoftGraphMailException("{$label} is not configured.");
        }

        return $value;
    }

    private function senderAddress(): string
    {
        $sender = $this->requiredConfig('from_address', 'Microsoft Graph sender address');

        if (filter_var($sender, FILTER_VALIDATE_EMAIL) === false) {
            throw new MicrosoftGraphMailException('The configured Microsoft Graph sender address is invalid.');
        }

        return $sender;
    }

    private function tokenCacheKey(): string
    {
        $tenantId = $this->requiredConfig('tenant_id', 'Microsoft tenant ID');
        $clientId = $this->requiredConfig('client_id', 'Microsoft client ID');
        $fingerprint = $this->certificateThumbprint($this->loadCertificate());

        return 'microsoft-graph-mail-token:'.hash('sha256', $tenantId.'|'.$clientId.'|'.$fingerprint);
    }

    private function assertRequestSize(array $payload): void
    {
        try {
            $bytes = strlen((string) json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (Throwable $exception) {
            throw new MicrosoftGraphMailException('The Microsoft Graph mail request could not be encoded safely.', previous: $exception);
        }

        if ($bytes <= self::MAX_JSON_REQUEST_BYTES) {
            return;
        }

        $this->logger->warning('Microsoft Graph mail request exceeded the JSON size limit.', [
            'operation' => 'sendMail',
            'request_bytes' => $bytes,
            'maximum_bytes' => self::MAX_JSON_REQUEST_BYTES,
        ]);

        throw new MicrosoftGraphMailException('The Microsoft Graph mail request exceeds the 4 MB JSON limit.');
    }

    private function isTransientStatus(int $status): bool
    {
        return $status === 429 || $status >= 500;
    }

    private function retryDelayMilliseconds(Response $response, int $attempt): int
    {
        $header = trim((string) $response->header('Retry-After'));

        if ($header !== '') {
            if (ctype_digit($header)) {
                return min($this->maximumRetryDelayMilliseconds(), (int) $header * 1_000);
            }

            $retryAt = strtotime($header);
            if ($retryAt !== false) {
                return min(
                    $this->maximumRetryDelayMilliseconds(),
                    max(0, $retryAt - $this->now()) * 1_000,
                );
            }
        }

        return $this->fallbackRetryDelayMilliseconds($attempt);
    }

    private function fallbackRetryDelayMilliseconds(int $attempt): int
    {
        return min($this->maximumRetryDelayMilliseconds(), 250 * (2 ** max(0, $attempt - 1)));
    }

    private function logRetry(string $operation, ?Response $response, int $attempt, int $delay): void
    {
        $this->logger->warning('Retrying a transient Microsoft Graph mail request.', array_filter([
            'operation' => $operation,
            'status' => $response?->status(),
            'error_code' => $response ? $this->safeDiagnosticValue($response->json('error.code')) : null,
            'retry_attempt' => $attempt,
            'delay_ms' => $delay,
        ], static fn (mixed $value): bool => $value !== null));
    }

    private function pause(int $milliseconds): void
    {
        if ($milliseconds <= 0) {
            return;
        }

        if ($this->sleep instanceof Closure) {
            ($this->sleep)($milliseconds);

            return;
        }

        usleep($milliseconds * 1_000);
    }

    private function safeDiagnosticValue(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $safe = preg_replace('/[^A-Za-z0-9._-]/', '', substr((string) $value, 0, 100));

        return is_string($safe) && $safe !== '' ? $safe : null;
    }

    private function timeout(): int
    {
        return max(1, (int) ($this->config['timeout'] ?? 30));
    }

    private function connectTimeout(): int
    {
        return max(1, min($this->timeout(), (int) ($this->config['connect_timeout'] ?? 10)));
    }

    /** @return array{verify: string}|array{} */
    private function httpOptions(): array
    {
        $caBundle = $this->caBundlePath();

        return $caBundle === null ? [] : ['verify' => $caBundle];
    }

    private function caBundlePath(): ?string
    {
        $path = trim((string) ($this->config['ca_bundle'] ?? ''));
        if ($path === '') {
            return null;
        }

        if (! is_file($path) || ! is_readable($path)) {
            throw new MicrosoftGraphMailException('The configured Microsoft Graph CA bundle is not a readable file.');
        }

        return $path;
    }

    private function transientRetries(): int
    {
        return min(self::MAX_TRANSIENT_RETRIES, max(0, (int) ($this->config['retry_attempts'] ?? 1)));
    }

    private function maximumRetryDelayMilliseconds(): int
    {
        return min(
            self::MAX_RETRY_DELAY_MILLISECONDS,
            max(0, (int) ($this->config['retry_max_delay_ms'] ?? 2_000)),
        );
    }

    private function now(): int
    {
        return $this->clock instanceof Closure ? (int) ($this->clock)() : time();
    }

    private function bodyToString(mixed $body): string
    {
        if (is_resource($body)) {
            $contents = stream_get_contents($body);

            return is_string($contents) ? $contents : '';
        }

        return (string) ($body ?? '');
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
