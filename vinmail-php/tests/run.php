<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/VinMailException.php';
require_once __DIR__ . '/../src/Attachment.php';
require_once __DIR__ . '/../src/HttpTransportInterface.php';
require_once __DIR__ . '/../src/CurlTransport.php';
require_once __DIR__ . '/../src/EmailsResource.php';
require_once __DIR__ . '/../src/VinMailClient.php';

use VinWorld\VinMail\Attachment;
use VinWorld\VinMail\HttpTransportInterface;
use VinWorld\VinMail\VinMailClient;
use VinWorld\VinMail\VinMailException;

final class FakeTransport implements HttpTransportInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $requests = [];
    /** @var array<int, array{status: int, headers: array<string, string>, body: string}> */
    private array $responses;

    /** @param array<int, array{status: int, headers: array<string, string>, body: string}> $responses */
    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function send(string $method, string $url, array $headers, ?string $body = null, int $timeoutSeconds = 15): array
    {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];

        $next = array_shift($this->responses);
        if ($next === null) {
            throw new RuntimeException('No mocked response queued.');
        }

        return $next;
    }
}

function assertSameValue(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL [{$label}]: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

// 1. Send email with idempotency key and attachment
$tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vinmail-php-test-' . uniqid() . '.pdf';
file_put_contents($tmpFile, '%PDF-1.4 sample receipt');

try {
    $transport = new FakeTransport([
        ['status' => 202, 'headers' => [], 'body' => '{"id":"em_php_1","status":"queued"}'],
    ]);
    $client = new VinMailClient('vm_live_php_key', 'https://api.example.test', 15, 2, $transport);
    $res = $client->emails->send([
        'from' => 'Billing <billing@acme.com>',
        'to' => 'buyer@example.com',
        'subject' => 'Receipt #100',
        'text' => 'Attached.',
        'attachments' => [Attachment::fromPath($tmpFile, 'receipt-100.pdf')],
    ], 'receipt-100');

    assertSameValue('em_php_1', $res['id'], 'send id');
    assertSameValue('Bearer vm_live_php_key', $transport->requests[0]['headers']['Authorization'], 'auth header');
    assertSameValue('receipt-100', $transport->requests[0]['headers']['Idempotency-Key'], 'idempotency header');

    $sentJson = json_decode((string) $transport->requests[0]['body'], true, 512, JSON_THROW_ON_ERROR);
    assertSameValue('receipt-100.pdf', $sentJson['attachments'][0]['filename'], 'attachment filename');
    assertSameValue('application/pdf', $sentJson['attachments'][0]['contentType'], 'attachment mime');
    assertSameValue('%PDF-1.4 sample receipt', base64_decode($sentJson['attachments'][0]['content'], true), 'attachment bytes');
} finally {
    @unlink($tmpFile);
}

// 2. Reject non-business attachment extension
$threw = false;
try {
    Attachment::fromContent('malware.exe', 'MZ');
} catch (VinMailException $e) {
    $threw = $e->getErrorCode() === 'validation_error' && str_contains($e->getMessage(), 'not permitted');
}
assertSameValue(true, $threw, 'reject blocked extension');

// 3. Retry 503 then succeed
$retryTransport = new FakeTransport([
    ['status' => 503, 'headers' => [], 'body' => '{"error":{"code":"unavailable","message":"retry"}}'],
    ['status' => 202, 'headers' => [], 'body' => '{"id":"em_retry","status":"queued"}'],
]);
$retryClient = new VinMailClient('vm_live_php_key', 'https://api.example.test', 15, 2, $retryTransport);
$retryRes = $retryClient->emails->send(['to' => 'user@example.com', 'template' => 'otp', 'variables' => ['code' => '111111']]);
assertSameValue('em_retry', $retryRes['id'], 'retry success');
assertSameValue(2, count($retryTransport->requests), 'retry count');

// 4. Get delivery status + parse API 422 error
$statusTransport = new FakeTransport([
    ['status' => 200, 'headers' => [], 'body' => '{"id":"em_7","status":"delivered","events":[]}'],
    ['status' => 422, 'headers' => [], 'body' => '{"error":{"code":"domain_not_verified","message":"Domain not verified"}}'],
]);
$statusClient = new VinMailClient('vm_live_php_key', 'https://api.example.test', 15, 0, $statusTransport);
$details = $statusClient->emails->get('em_7');
assertSameValue('delivered', $details['status'], 'get status');

$got422 = false;
try {
    $statusClient->emails->send(['to' => 'user@example.com', 'subject' => 'Hi', 'text' => 'Hi']);
} catch (VinMailException $e) {
    $got422 = $e->getStatus() === 422 && $e->getErrorCode() === 'domain_not_verified';
}
assertSameValue(true, $got422, '422 error parsed');

echo "All 4 PHP SDK tests passed!\n";
