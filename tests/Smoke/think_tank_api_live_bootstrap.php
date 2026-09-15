<?php

/** Isolated local HTTP smoke configuration. Never load the application's .env. */
if (! in_array(PHP_SAPI, ['cli', 'cli-server'], true)) {
    throw new RuntimeException('The live smoke harness is CLI-only.');
}

$runId = (string) getenv('THINK_TANK_LIVE_RUN_ID');
if (! preg_match('/^[a-f0-9]{16,40}$/D', $runId)) {
    throw new RuntimeException('THINK_TANK_LIVE_RUN_ID must be a fresh random hexadecimal identifier.');
}

$root = dirname(__DIR__, 2);
$runDirectory = $root.'/storage/framework/testing/think-tank-api-live-'.$runId;
if (! is_dir($runDirectory)) {
    mkdir($runDirectory, 0700, true);
}
foreach (['views', 'logs'] as $directory) {
    if (! is_dir($runDirectory.'/'.$directory)) {
        mkdir($runDirectory.'/'.$directory, 0700, true);
    }
}
if (! is_file($runDirectory.'/app.key')) {
    file_put_contents($runDirectory.'/app.key', 'base64:'.base64_encode(random_bytes(32)), LOCK_EX);
}

$frontend = (string) (getenv('THINK_TANK_LIVE_FRONTEND') ?: 'http://localhost:3101');
if (! preg_match('#^http://(localhost|127\.0\.0\.1):[0-9]{4,5}$#D', $frontend)) {
    throw new RuntimeException('The smoke frontend must use a loopback HTTP origin.');
}

foreach ([
    'APP_ENV' => 'local',
    'APP_KEY' => trim(file_get_contents($runDirectory.'/app.key')),
    'APP_CONFIG_CACHE' => $runDirectory.'/config.php',
    'APP_ROUTES_CACHE' => $runDirectory.'/routes.php',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $runDirectory.'/database.sqlite',
    'DB_URL' => '',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'database',
    'CACHE_STORE' => 'database',
] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.think-tank-live-not-used');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

config([
    'app.env' => 'local',
    'app.debug' => false,
    'app.url' => 'http://127.0.0.1:3198',
    'database.default' => 'sqlite',
    'database.connections' => ['sqlite' => [
        'driver' => 'sqlite', 'database' => $runDirectory.'/database.sqlite',
        'prefix' => '', 'foreign_key_constraints' => true, 'busy_timeout' => 5000,
    ]],
    'session.driver' => 'database',
    'session.connection' => 'sqlite',
    'session.cookie' => 'think_tank_live_'.$runId,
    'session.domain' => null,
    'session.secure' => false,
    'session.http_only' => true,
    'session.same_site' => 'lax',
    'cache.default' => 'database',
    'cache.limiter' => 'database',
    'cache.stores.database.connection' => 'sqlite',
    'cache.stores.database.lock_connection' => 'sqlite',
    'cache.prefix' => 'think_tank_live_'.$runId,
    'mail.default' => 'array',
    'mail.mailers' => ['array' => ['transport' => 'array']],
    'mail.from' => ['address' => 'harness@example.test', 'name' => 'Local smoke harness'],
    'queue.default' => 'sync',
    'logging.default' => 'single',
    'logging.channels.single.path' => $runDirectory.'/logs/laravel.log',
    'view.compiled' => $runDirectory.'/views',
    'services.ipgeo.enabled' => false,
    'think_tank_portal.require_mfa' => true,
    'think_tank_portal.email_lock_store' => 'database',
    'think_tank_portal.frontend_url' => $frontend,
    'think_tank_portal.allowed_origins' => [$frontend],
    'sanctum.stateful' => [parse_url($frontend, PHP_URL_HOST).':'.parse_url($frontend, PHP_URL_PORT)],
    'cors.allowed_origins' => [$frontend],
]);

// Capture actual rendered mail locally while the real array transport delivers it.
// Do not fake the authentication service or write/read plaintext OTP database rows.
Illuminate\Support\Facades\Event::listen(
    Illuminate\Mail\Events\MessageSending::class,
    static function (Illuminate\Mail\Events\MessageSending $event) use ($runDirectory): void {
        $recipients = array_map(static fn ($address) => $address->getAddress(), $event->message->getTo());
        foreach ($recipients as $recipient) {
            if (! str_ends_with($recipient, '@example.test')) {
                throw new RuntimeException('Smoke mail recipients must use example.test.');
            }
        }
        file_put_contents($runDirectory.'/mail.jsonl', json_encode([
            'to' => $recipients,
            'subject' => $event->message->getSubject(),
            'html' => $event->message->getHtmlBody(),
            'text' => $event->message->getTextBody(),
        ], JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND | LOCK_EX);
    },
);

return [$app, $runDirectory];
