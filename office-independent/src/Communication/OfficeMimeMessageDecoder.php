<?php
declare(strict_types=1);

namespace Brebo\Office\Communication;

use RuntimeException;

/**
 * Framework-free MIME body decoder adapted from the legacy sboffice approach.
 * Attachment parts are explicitly reported, never silently discarded.
 */
final class OfficeMimeMessageDecoder
{
    /** @return array{body:string,attachments:list<array{filename:string,mime:string,bytes:string}>} */
    public function decode(string $raw, string $contentType = 'text/plain', string $encoding = '7bit'): array
    {
        if (strlen($raw) > 25 * 1024 * 1024) {
            throw new RuntimeException('MIME payload too large.');
        }
        if (stripos($contentType, 'multipart/') === 0) {
            throw new RuntimeException('Multipart parser and attachment storage required.');
        }
        if (!preg_match('~^text/(?:plain|html)(?:;|$)~i', $contentType)) {
            throw new RuntimeException('Unsupported MIME content type.');
        }
        $decoded = match (strtolower(trim($encoding))) {
            '7bit', '8bit', 'binary' => $raw,
            'quoted-printable' => quoted_printable_decode($raw),
            'base64' => $this->decodeBase64($raw),
            default => throw new RuntimeException('Unsupported transfer encoding.'),
        };
        if (stripos($contentType, 'text/html') === 0) {
            $decoded = strip_tags($decoded);
        }
        return ['body' => $decoded, 'attachments' => []];
    }

    private function decodeBase64(string $raw): string
    {
        $decoded = base64_decode($raw, true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64 MIME payload.');
        }
        return $decoded;
    }
}
