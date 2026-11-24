<?php

declare(strict_types=1);

namespace VinWorld\VinMail;

final class CurlTransport implements HttpTransportInterface
{
    public function send(
        string $method,
        string $url,
        array $headers,
        ?string $body = null,
        int $timeoutSeconds = 15,
    ): array {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new VinMailException('Failed to initialize cURL session.', 0, 'network_error');
        }

        $responseHeaders = [];
        $formattedHeaders = [];
        foreach ($headers as $name => $value) {
            $formattedHeaders[] = "{$name}: {$value}";
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $formattedHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeoutSeconds),
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $len = strlen($line);
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $len;
            },
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $rawBody = curl_exec($ch);
        if ($rawBody === false) {
            $errNo = curl_errno($ch);
            $errMsg = curl_error($ch);
            curl_close($ch);
            $isTimeout = $errNo === CURLE_OPERATION_TIMEDOUT;
            throw new VinMailException(
                $isTimeout ? "VinMail request timed out after {$timeoutSeconds}s." : "cURL error: {$errMsg}",
                0,
                $isTimeout ? 'timeout' : 'network_error',
            );
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => (string) $rawBody,
        ];
    }
}
