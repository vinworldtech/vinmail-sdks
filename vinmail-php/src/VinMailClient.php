<?php

declare(strict_types=1);

namespace VinWorld\VinMail;

class VinMailClient
{
    public const DEFAULT_BASE_URL = 'https://vinmail-api.vinworlddev.com';
    public const SDK_VERSION = '0.1.0';

    public readonly EmailsResource $emails;
    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly int $timeoutSeconds;
    private readonly int $maxRetries;
    private readonly HttpTransportInterface $transport;

    public function __construct(
        string $apiKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        int $timeoutSeconds = 15,
        int $maxRetries = 2,
        ?HttpTransportInterface $transport = null,
    ) {
        $trimmed = trim($apiKey);
        if ($trimmed === '') {
            throw new VinMailException('A valid VinMail API key (vm_live_...) is required.', 401, 'missing_api_key');
        }

        $this->apiKey = $trimmed;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeoutSeconds = max(1, $timeoutSeconds);
        $this->maxRetries = max(0, $maxRetries);
        $this->transport = $transport ?? new CurlTransport();
        $this->emails = new EmailsResource($this);
    }

    public function emails(): EmailsResource
    {
        return $this->emails;
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<string, string> $extraHeaders
     * @return array<string, mixed>
     */
    public function request(
        string $method,
        string $path,
        ?array $payload = null,
        array $extraHeaders = [],
    ): array {
        $url = $this->baseUrl . $path;
        $headers = array_merge([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => 'vinmail-php/' . self::SDK_VERSION,
        ], $extraHeaders);

        $encodedBody = null;
        if ($payload !== null) {
            $headers['Content-Type'] = 'application/json';
            $encodedBody = json_encode($payload, JSON_THROW_ON_ERROR);
        }

        $attempt = 0;
        while (true) {
            try {
                $response = $this->transport->send($method, $url, $headers, $encodedBody, $this->timeoutSeconds);
            } catch (VinMailException $e) {
                if ($attempt < $this->maxRetries && $e->getStatus() === 0) {
                    $attempt++;
                    usleep(min(300_000 * (1 << ($attempt - 1)), 3_000_000));
                    continue;
                }
                throw $e;
            }

            $status = $response['status'];
            $decoded = null;
            if ($response['body'] !== '') {
                $json = json_decode($response['body'], true);
                if (is_array($json)) {
                    $decoded = $json;
                }
            }

            if ($status >= 200 && $status < 300) {
                return $decoded ?? [];
            }

            $retryAfter = isset($response['headers']['retry-after']) && is_numeric($response['headers']['retry-after'])
                ? (int) $response['headers']['retry-after']
                : null;

            if ($attempt < $this->maxRetries && ($status === 429 || $status >= 500)) {
                $attempt++;
                $delayMicros = $retryAfter !== null
                    ? min($retryAfter * 1_000_000, 5_000_000)
                    : min(300_000 * (1 << ($attempt - 1)), 3_000_000);
                usleep($delayMicros);
                continue;
            }

            $errorCode = is_array($decoded['error'] ?? null) && is_string($decoded['error']['code'] ?? null)
                ? $decoded['error']['code']
                : "http_{$status}";
            $errorMsg = is_array($decoded['error'] ?? null) && is_string($decoded['error']['message'] ?? null)
                ? $decoded['error']['message']
                : ($response['body'] !== '' ? substr($response['body'], 0, 300) : "VinMail request failed with HTTP {$status}.");

            throw new VinMailException($errorMsg, $status, $errorCode, $retryAfter);
        }
    }
}
