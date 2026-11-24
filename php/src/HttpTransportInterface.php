<?php

declare(strict_types=1);

namespace VinWorld\VinMail;

interface HttpTransportInterface
{
    /**
     * @param array<string, string> $headers
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function send(string $method, string $url, array $headers, ?string $body = null, int $timeoutSeconds = 15): array;
}
