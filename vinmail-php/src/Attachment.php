<?php

declare(strict_types=1);

namespace VinWorld\VinMail;

final class Attachment
{
    public const MAX_FILES = 5;
    public const MAX_SINGLE_BYTES = 5_242_880; // 5 MB
    public const MAX_TOTAL_BYTES = 7_864_320; // 7.5 MB

    /** @var array<string, string> */
    public const ALLOWED_BUSINESS_EXTENSIONS = [
        '.pdf' => 'application/pdf',
        '.doc' => 'application/msword',
        '.docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        '.xls' => 'application/vnd.ms-excel',
        '.xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        '.csv' => 'text/csv',
        '.ppt' => 'application/vnd.ms-powerpoint',
        '.pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        '.txt' => 'text/plain',
        '.rtf' => 'application/rtf',
        '.png' => 'image/png',
        '.jpg' => 'image/jpeg',
        '.jpeg' => 'image/jpeg',
        '.webp' => 'image/webp',
        '.gif' => 'image/gif',
    ];

    public function __construct(
        public readonly string $filename,
        public readonly string $base64Content,
        public readonly string $contentType,
        public readonly int $sizeBytes,
    ) {
    }

    public static function fromPath(string $path, ?string $filename = null): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new VinMailException(
                "Attachment file not found or not readable: {$path}",
                422,
                'validation_error',
            );
        }

        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new VinMailException("Could not read attachment file: {$path}", 422, 'validation_error');
        }

        return self::fromContent($filename ?? basename($path), $bytes);
    }

    public static function fromContent(string $filename, string $rawBytes): self
    {
        $cleanName = trim($filename);
        $mime = self::validateFilenameAndGetMime($cleanName);
        $size = strlen($rawBytes);
        self::validateSingleSize($cleanName, $size);

        return new self($cleanName, base64_encode($rawBytes), $mime, $size);
    }

    public static function fromBase64(string $filename, string $base64): self
    {
        $cleanName = trim($filename);
        $mime = self::validateFilenameAndGetMime($cleanName);

        $trimmed = trim($base64);
        if (preg_match('/^data:[^;]+;base64,(.+)$/is', $trimmed, $m) === 1) {
            $trimmed = $m[1];
        }
        $cleanB64 = preg_replace('/\s+/', '', $trimmed) ?? '';
        $decoded = base64_decode($cleanB64, true);
        if ($decoded === false) {
            throw new VinMailException(
                "Attachment \"{$cleanName}\" contains invalid base64 content.",
                422,
                'validation_error',
            );
        }

        $size = strlen($decoded);
        self::validateSingleSize($cleanName, $size);

        return new self($cleanName, $cleanB64, $mime, $size);
    }

    /**
     * Normalises an array of Attachment objects or associative arrays into VinMail API payloads.
     *
     * @param array<int, self|array<string, mixed>> $items
     * @return array<int, array{filename: string, content: string, contentType: string}>
     */
    public static function prepareAll(array $items): array
    {
        if (count($items) === 0) {
            return [];
        }
        if (count($items) > self::MAX_FILES) {
            throw new VinMailException(
                'At most ' . self::MAX_FILES . ' file attachments are allowed per email.',
                422,
                'validation_error',
            );
        }

        $totalBytes = 0;
        $payload = [];

        foreach ($items as $item) {
            if ($item instanceof self) {
                $att = $item;
            } elseif (is_array($item)) {
                if (isset($item['path']) && is_string($item['path'])) {
                    $name = isset($item['filename']) && is_string($item['filename']) ? $item['filename'] : null;
                    $att = self::fromPath($item['path'], $name);
                } elseif (isset($item['filename'], $item['content']) && is_string($item['filename']) && is_string($item['content'])) {
                    $att = self::fromBase64($item['filename'], $item['content']);
                } else {
                    throw new VinMailException(
                        'Each attachment must be an Attachment instance or array with "path" or ("filename" + "content").',
                        422,
                        'validation_error',
                    );
                }
            } else {
                throw new VinMailException('Invalid attachment entry.', 422, 'validation_error');
            }

            $totalBytes += $att->sizeBytes;
            if ($totalBytes > self::MAX_TOTAL_BYTES) {
                throw new VinMailException(
                    'Combined attachment size exceeds the 7.5 MB limit per email.',
                    422,
                    'validation_error',
                );
            }

            $payload[] = [
                'filename' => $att->filename,
                'content' => $att->base64Content,
                'contentType' => $att->contentType,
            ];
        }

        return $payload;
    }

    private static function validateFilenameAndGetMime(string $filename): string
    {
        if ($filename === '') {
            throw new VinMailException('Each attachment must include a valid filename.', 422, 'validation_error');
        }

        $pos = strrpos($filename, '.');
        $ext = ($pos === false || $pos === 0 || $pos === strlen($filename) - 1)
            ? ''
            : strtolower(substr($filename, $pos));

        if (!isset(self::ALLOWED_BUSINESS_EXTENSIONS[$ext])) {
            throw new VinMailException(
                "File \"{$filename}\" is not permitted. Only business documents and images are allowed (PDF, DOC/DOCX, XLS/XLSX, CSV, PPT/PPTX, TXT, RTF, PNG, JPG, WEBP, GIF).",
                422,
                'validation_error',
            );
        }

        return self::ALLOWED_BUSINESS_EXTENSIONS[$ext];
    }

    private static function validateSingleSize(string $filename, int $size): void
    {
        if ($size <= 0) {
            throw new VinMailException("Attachment \"{$filename}\" cannot be empty.", 422, 'validation_error');
        }
        if ($size > self::MAX_SINGLE_BYTES) {
            throw new VinMailException(
                "Attachment \"{$filename}\" exceeds the 5 MB per-file size limit.",
                422,
                'validation_error',
            );
        }
    }
}
