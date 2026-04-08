<?php

$readEnvironmentFileValue = static function (string $key): mixed {
    foreach (['.env', 'env'] as $environmentFile) {
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . $environmentFile;
        if (! is_file($path)) {
            continue;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = array_pad(explode('=', $line, 2), 2, null);
            if (trim((string) $name) !== $key) {
                continue;
            }

            $value = trim((string) $value);
            if ($value === '') {
                return null;
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                return substr($value, 1, -1);
            }

            return $value;
        }
    }

    return null;
};

$resolveMailValue = static function (array $keys, mixed $default = null) use ($readEnvironmentFileValue): mixed {
    foreach ($keys as $key) {
        $value = env($key);
        if ($value !== null && $value !== '') {
            return $value;
        }

        $runtimeValue = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($runtimeValue !== false && $runtimeValue !== null && $runtimeValue !== '') {
            return $runtimeValue;
        }

        $fileValue = $readEnvironmentFileValue($key);
        if ($fileValue !== null && $fileValue !== '') {
            return $fileValue;
        }
    }

    return $default;
};

$resolveMailScheme = static function () use ($resolveMailValue): ?string {
    $scheme = $resolveMailValue(['MAIL_SCHEME']);

    if (is_string($scheme)) {
        $scheme = strtolower(trim($scheme));

        return match ($scheme) {
            '', 'null', '(null)' => null,
            'ssl' => 'smtps',
            'tls' => 'smtp',
            default => $scheme,
        };
    }

    $legacyEncryption = $resolveMailValue(['MAIL_ENCRYPTION']);

    if (! is_string($legacyEncryption)) {
        return null;
    }

    $legacyEncryption = strtolower(trim($legacyEncryption));

    return match ($legacyEncryption) {
        '', 'null', '(null)' => null,
        'ssl' => 'smtps',
        'tls' => 'smtp',
        default => null,
    };
};

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => $resolveMailValue(['MAIL_MAILER'], 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => $resolveMailScheme(),
            'url' => $resolveMailValue(['MAIL_URL']),
            'host' => $resolveMailValue(['MAIL_HOST'], '127.0.0.1'),
            'port' => $resolveMailValue(['MAIL_PORT'], 2525),
            'username' => $resolveMailValue(['MAIL_USERNAME']),
            'password' => $resolveMailValue(['MAIL_PASSWORD']),
            'timeout' => null,
            'local_domain' => $resolveMailValue(
                ['MAIL_EHLO_DOMAIN'],
                parse_url((string) $resolveMailValue(['APP_URL'], 'http://localhost'), PHP_URL_HOST)
            ),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => $resolveMailValue(['MAIL_SENDMAIL_PATH'], '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => $resolveMailValue(['MAIL_LOG_CHANNEL']),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => $resolveMailValue(['MAIL_FROM_ADDRESS'], 'hello@example.com'),
        'name' => $resolveMailValue(['MAIL_FROM_NAME'], 'Example'),
    ],

];
