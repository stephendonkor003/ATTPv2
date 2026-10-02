<?php

namespace App\Console\Commands;

use App\Services\Mail\MicrosoftGraphMailException;
use App\Services\Mail\MicrosoftGraphMailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CheckGraphMail extends Command
{
    protected $signature = 'graph-mail:check
        {--authenticate : Request and securely cache an access token without sending email}';

    protected $description = 'Validate Microsoft Graph mail configuration without sending email';

    public function handle(MicrosoftGraphMailService $graph): int
    {
        if (config('mail.default') !== 'graph') {
            $this->error('Graph mail check failed: the default mailer is not graph.');

            return self::FAILURE;
        }

        if (config('mail.mailers.graph.transport') !== 'graph') {
            $this->error('Graph mail check failed: the graph mailer transport is invalid.');

            return self::FAILURE;
        }

        try {
            $graph->validateConfiguration((string) config('mail.from.address'));
            $this->info('Graph mail configuration check passed.');

            if ((bool) $this->option('authenticate')) {
                $graph->authenticate();
                $this->info('Microsoft Graph authentication succeeded. No email was sent.');
            } else {
                $this->line('No network request was made and no email was sent.');
            }
        } catch (MicrosoftGraphMailException $exception) {
            $this->error('Graph mail check failed: '.$exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            Log::error('Unexpected Microsoft Graph mail configuration check failure.', [
                'operation' => 'configuration_check',
                'exception' => $exception::class,
            ]);
            $this->error('Graph mail check failed unexpectedly. Review the sanitized application log.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
