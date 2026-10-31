<?php

declare(strict_types=1);

namespace Brebo\Office\Communication;

/** Presentation-only decoder for RFC 2047 encoded mail headers. */
final class OfficeCommunicationHeader
{
    public static function decode(?string $header): string
    {
        $value = (string) ($header ?? '');
        if ($value === '' || !str_contains($value, '=?')) {
            return $value;
        }
        if (function_exists('iconv_mime_decode')) {
            $decoded = iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }
        if (function_exists('mb_decode_mimeheader')) {
            $decoded = mb_decode_mimeheader($value);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }
        return $value;
    }
}
