<?php

namespace App\Services\ThinkTank;

use App\Exceptions\ThinkTankApiException;

class ThinkTankMailSecurityService
{
    /**
     * OTPs and password-reset links are credentials. Production must never
     * route them to debug transports or silently retain them in memory.
     */
    public function assertCredentialDeliveryIsSecure(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        $mailer = (string) config('mail.default');

        if ($mailer === '' || ! $this->isSecureMailer($mailer, [])) {
            throw new ThinkTankApiException(
                'SECURE_MAIL_CONFIGURATION_REQUIRED',
                'Secure credential email delivery is not configured.',
                503,
            );
        }
    }

    public function assertEncryptedResetQueueIsDurable(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        $connection = (string) config('queue.default');
        $driver = (string) config("queue.connections.{$connection}.driver");

        if (! in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true)) {
            throw new ThinkTankApiException(
                'DURABLE_CREDENTIAL_QUEUE_REQUIRED',
                'Secure password-link delivery requires a durable production queue.',
                503,
            );
        }
    }

    /** @param array<string, true> $visited */
    private function isSecureMailer(string $mailer, array $visited): bool
    {
        if (isset($visited[$mailer])) {
            return false;
        }

        $configuration = config("mail.mailers.{$mailer}");

        if (! is_array($configuration)) {
            return false;
        }

        $transport = mb_strtolower(trim((string) ($configuration['transport'] ?? '')));

        if (in_array($transport, ['log', 'array'], true)) {
            return false;
        }

        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $children = $configuration['mailers'] ?? [];

            if (! is_array($children) || $children === []) {
                return false;
            }

            $visited[$mailer] = true;

            foreach ($children as $child) {
                if (! is_string($child) || ! $this->isSecureMailer($child, $visited)) {
                    return false;
                }
            }

            return true;
        }

        return in_array($transport, [
            'smtp',
            'sendmail',
            'mailgun',
            'ses',
            'ses-v2',
            'postmark',
            'resend',
        ], true);
    }
}
