<?php

namespace App\Mail\Transport;

use App\Services\Mail\MicrosoftGraphMailException;
use App\Services\Mail\MicrosoftGraphMailService;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;

final class MicrosoftGraphTransport extends AbstractTransport
{
    public function __construct(private readonly MicrosoftGraphMailService $graph)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'graph';
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();
        if (! $email instanceof Email) {
            throw new MicrosoftGraphMailException('Microsoft Graph mail transport requires a Symfony Email message.');
        }

        $this->graph->send($email, $message->getEnvelope());
    }
}
