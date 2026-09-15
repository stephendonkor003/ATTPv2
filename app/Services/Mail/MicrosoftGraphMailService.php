<?php

namespace App\Services\Mail;

use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

final class MicrosoftGraphMailService
{
    private const ASSERTION_LIFETIME_SECONDS = 300;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly CacheRepository $cache,
        private readonly LoggerInterface $logger,
        private readonly array $config,
    ) {
    }

    public function send(Email $email, Envelope $envelope): void
    {
        $sender = $this->requiredConfig('from_address', 'Microsoft Graph sender address');
        $url = rtrim($this->requiredConfig('base_url', 'Microsoft Graph base URL'), '/')
            .'/users/'.rawurlencode($sender).'/sendMail';

        try {
            $response = $this->http
                ->withToken($this->accessToken())
                ->acceptJson()
                ->asJson()
                ->timeout($this->timeout())
                ->post($url, ['message' => $this->messagePayload($email, $envelope), 'saveToSentItems' => true]);
        } catch (ConnectionException $exception) {
            $this->throwConnectionFailure('sendMail', $exception);
        }

        if ($response->status() !== 202) {
            $this->throwHttpFailure('sendMail', $response);
        }
    }

    public function createClientAssertion(?int $now = null): string
    {
        $tenantId = $this->requiredConfig('tenant_id', 'Microsoft tenant ID');
        $clientId = $this->requiredConfig('client_id', 'Microsoft client ID');
        $certificate = $this->loadCertificate();
        $privateKey = $this->loadPrivateKey();

        if (! openssl_x509_check_private_key($certificate, $privateKey)) {
            throw new MicrosoftGraphMailException('The configured Microsoft certificate and private key do not match.');
        }

        $timestamp = $now ?? time();
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

    private function accessToken(): string
    {
        $tenantId = $this->requiredConfig('tenant_id', 'Microsoft tenant ID');
        $clientId = $this->requiredConfig('client_id', 'Microsoft client ID');
        $cacheKey = 'microsoft-graph-mail-token:'.hash('sha256', $tenantId.'|'.$clientId);
        $cached = $this->cache->get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $url = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
        try {
            $response = $this->http
                ->asForm()
                ->acceptJson()
                ->timeout($this->timeout())
                ->post($url, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'scope' => (string) ($this->config['scope'] ?? 'https://graph.microsoft.com/.default'),
                    'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
                    'client_assertion' => $this->createClientAssertion(),
                ]);
        } catch (ConnectionException $exception) {
            $this->throwConnectionFailure('authentication', $exception);
        }

        if (! $response->successful()) {
            $this->throwHttpFailure('authentication', $response);
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new MicrosoftGraphMailException('Microsoft authentication returned no access token.');
        }

        $expiresIn = max(60, (int) $response->json('expires_in', 3600));
        $this->cache->put($cacheKey, $token, max(1, $expiresIn - 120));

        return $token;
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

    private function loadCertificate(): mixed
    {
        $contents = $this->readCredentialFile('certificate_path', 'Microsoft certificate');
        $certificate = openssl_x509_read($contents);

        if ($certificate === false) {
            throw new MicrosoftGraphMailException('The configured Microsoft certificate is invalid.');
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

        return $key;
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
            'request_id' => $response->header('request-id') ?: $response->header('client-request-id'),
            'error_code' => $response->json('error.code'),
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

    private function timeout(): int
    {
        return max(1, (int) ($this->config['timeout'] ?? 30));
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
