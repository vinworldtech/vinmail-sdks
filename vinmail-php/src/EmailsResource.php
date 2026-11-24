<?php

declare(strict_types=1);

namespace VinWorld\VinMail;

final class EmailsResource
{
    public function __construct(private readonly VinMailClient $client)
    {
    }

    /**
     * Queue a transactional email for immediate delivery (`POST /v1/emails`).
     *
     * @param array{
     *   from?: string,
     *   to: string|array<int, string>,
     *   replyTo?: string,
     *   subject?: string,
     *   html?: string,
     *   text?: string,
     *   template?: string,
     *   variables?: array<string, mixed>,
     *   tags?: array<string, string>,
     *   attachments?: array<int, Attachment|array<string, mixed>>,
     *   idempotencyKey?: string
     * } $params
     * @return array{id: string, status: string, suppressed?: array<int, string>}
     */
    public function send(array $params, ?string $idempotencyKey = null): array
    {
        $key = trim((string) ($idempotencyKey ?? ($params['idempotencyKey'] ?? '')));
        unset($params['idempotencyKey']);

        if (isset($params['attachments']) && is_array($params['attachments'])) {
            $prepared = Attachment::prepareAll($params['attachments']);
            if (count($prepared) > 0) {
                $params['attachments'] = $prepared;
            } else {
                unset($params['attachments']);
            }
        }

        $headers = [];
        if ($key !== '') {
            $headers['Idempotency-Key'] = $key;
        }

        /** @var array{id: string, status: string, suppressed?: array<int, string>} */
        return $this->client->request('POST', '/v1/emails', $params, $headers);
    }

    /**
     * Fetch delivery status and event timeline for an email (`GET /v1/emails/:id`).
     *
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        $cleanId = trim($id);
        if ($cleanId === '') {
            throw new VinMailException('Email id is required.', 422, 'validation_error');
        }

        return $this->client->request('GET', '/v1/emails/' . rawurlencode($cleanId));
    }
}
