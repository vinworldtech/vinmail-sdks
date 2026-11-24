<?php

declare(strict_types=1);

namespace VinWorld\VinMail\Laravel;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;
use VinWorld\VinMail\Attachment;
use VinWorld\VinMail\VinMailClient;

/**
 * Laravel / Symfony Mailer transport so developers can set `MAIL_MAILER=vinmail`
 * and continue using standard `Mail::to($user)->send(new WelcomeMail())`.
 */
class VinMailTransport extends AbstractTransport
{
    public function __construct(private readonly VinMailClient $client)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $fromList = $email->getFrom();
        $from = count($fromList) > 0 ? $this->formatAddress($fromList[0]) : null;

        $toAddresses = array_values(array_map(
            static fn (Address $addr): string => $addr->getAddress(),
            $email->getTo(),
        ));

        $replyToList = $email->getReplyTo();
        $replyTo = count($replyToList) > 0 ? $this->formatAddress($replyToList[0]) : null;

        $attachments = [];
        foreach ($email->getAttachments() as $part) {
            $name = $part->getFilename() ?? 'attachment.bin';
            $attachments[] = Attachment::fromContent($name, $part->getBody());
        }

        $payload = [
            'to' => $toAddresses,
            'subject' => $email->getSubject() ?? '',
        ];

        if ($from !== null) {
            $payload['from'] = $from;
        }
        if ($replyTo !== null) {
            $payload['replyTo'] = $replyTo;
        }
        if ($email->getHtmlBody() !== null) {
            $payload['html'] = (string) $email->getHtmlBody();
        }
        if ($email->getTextBody() !== null) {
            $payload['text'] = (string) $email->getTextBody();
        }
        if (count($attachments) > 0) {
            $payload['attachments'] = $attachments;
        }

        $this->client->emails->send($payload);
    }

    private function formatAddress(Address $address): string
    {
        $name = trim($address->getName());
        return $name !== '' ? "{$name} <{$address->getAddress()}>" : $address->getAddress();
    }

    public function __toString(): string
    {
        return 'vinmail';
    }
}
