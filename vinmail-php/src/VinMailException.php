<?php

declare(strict_types=1);

namespace VinWorld\VinMail;

use RuntimeException;

class VinMailException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $status = 0,
        private readonly string $errorCode = 'vinmail_error',
        private readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $status);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
