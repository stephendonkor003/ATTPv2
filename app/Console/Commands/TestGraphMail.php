<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class TestGraphMail extends Command
{
    protected $signature = 'graph-mail:test {recipient : Email address that should receive the test message}';

    protected $description = 'Send a test email through the Microsoft Graph mail transport';

    public function handle(): int
    {
        $recipient = (string) $this->argument('recipient');
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('The recipient must be a valid email address.');

            return self::INVALID;
        }

        try {
            Mail::mailer('graph')->raw(
                'Microsoft Graph mail delivery is configured for '.config('app.name').'.',
                fn ($message) => $message
                    ->to($recipient)
                    ->subject(config('app.name').' Microsoft Graph mail test')
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Microsoft Graph did not accept the test email. Check the application log for sanitized diagnostics.');

            return self::FAILURE;
        }

        $this->info("Microsoft Graph accepted the test email for {$recipient} (HTTP 202).");

        return self::SUCCESS;
    }
}
