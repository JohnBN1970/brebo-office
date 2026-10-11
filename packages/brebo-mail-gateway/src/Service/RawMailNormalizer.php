<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Service;

use InvalidArgumentException;

final class RawMailNormalizer {

  /** @return array<string,mixed> */
  public function normalize(string $raw, string $envelopeRecipient): array {
    $envelopeRecipient = mb_strtolower(trim($envelopeRecipient));
    if (filter_var($envelopeRecipient, FILTER_VALIDATE_EMAIL) === FALSE) {
      throw new InvalidArgumentException('Ongeldige envelope recipient.');
    }
    if ($raw === '') {
      throw new InvalidArgumentException('Leeg mailbericht.');
    }

    [$headerBlock, $body] = array_pad(preg_split("/\r?\n\r?\n/", $raw, 2) ?: [], 2, '');
    $headers = $this->headers($headerBlock);
    $from = $this->firstAddress((string) ($headers['from'] ?? ''));
    if ($from === '') {
      throw new InvalidArgumentException('From header bevat geen geldig adres.');
    }

    $subject = $this->decodeHeader((string) ($headers['subject'] ?? ''));
    $text = $this->decodeEntity(
      $body,
      (string) ($headers['content-type'] ?? 'text/plain; charset=UTF-8'),
      (string) ($headers['content-transfer-encoding'] ?? ''),
      (string) ($headers['content-disposition'] ?? ''),
    );

    $messageId = trim((string) ($headers['message-id'] ?? ''), " <>\t\r\n");
    $receivedAt = trim((string) ($headers['date'] ?? ''));
    if ($receivedAt !== '' && strtotime($receivedAt) === FALSE) {
      $receivedAt = '';
    }

    return [
      'source_id' => $messageId !== '' ? 'smtp:' . $messageId : '',
      'from' => $from,
      'to' => $envelopeRecipient,
      'subject' => $subject,
      'text' => $text,
      'received_at' => $receivedAt,
      'raw' => $raw,
    ];
  }

  /** @return array<string,string> */
  private function headers(string $block): array {
    $unfolded = preg_replace("/\r?\n[ \t]+/", ' ', $block) ?? $block;
    $headers = [];
    foreach (preg_split("/\r?\n/", $unfolded) ?: [] as $line) {
      if (!str_contains($line, ':')) {
        continue;
      }
      [$name, $value] = explode(':', $line, 2);
      $name = mb_strtolower(trim($name));
      if ($name !== '' && !isset($headers[$name])) {
        $headers[$name] = trim($value);
      }
    }
    return $headers;
  }

  private function decodeEntity(string $body, string $contentType, string $encoding, string $disposition = ''): string {
    $mediaType = mb_strtolower(trim(strtok($contentType, ';') ?: 'text/plain'));
    if (str_contains(mb_strtolower($disposition), 'attachment')) {
      return '';
    }

    if (str_starts_with($mediaType, 'multipart/')) {
      $boundary = $this->parameter($contentType, 'boundary');
      if ($boundary === '') {
        return '';
      }

      $plain = [];
      $html = [];
      foreach ($this->multipartParts($body, $boundary) as $part) {
        [$partHeadersRaw, $partBody] = array_pad(preg_split("/\r?\n\r?\n/", $part, 2) ?: [], 2, '');
        $partHeaders = $this->headers($partHeadersRaw);
        $partType = (string) ($partHeaders['content-type'] ?? 'text/plain; charset=UTF-8');
        $partMediaType = mb_strtolower(trim(strtok($partType, ';') ?: 'text/plain'));
        $decoded = $this->decodeEntity(
          $partBody,
          $partType,
          (string) ($partHeaders['content-transfer-encoding'] ?? ''),
          (string) ($partHeaders['content-disposition'] ?? ''),
        );
        if ($decoded === '') {
          continue;
        }
        if ($partMediaType === 'text/plain') {
          $plain[] = $decoded;
        }
        elseif ($partMediaType === 'text/html' || str_starts_with($partMediaType, 'multipart/')) {
          $html[] = $decoded;
        }
      }

      return trim(implode("\n\n", $plain !== [] ? $plain : $html));
    }

    $decoded = match (mb_strtolower(trim($encoding))) {
      'base64' => base64_decode(preg_replace('/\s+/', '', $body) ?: '', TRUE),
      'quoted-printable' => quoted_printable_decode($body),
      default => $body,
    };
    if ($decoded === FALSE) {
      return '';
    }

    $charset = $this->parameter($contentType, 'charset');
    if ($charset !== '' && mb_strtoupper($charset) !== 'UTF-8') {
      try {
        $decoded = mb_convert_encoding($decoded, 'UTF-8', $charset);
      }
      catch (\ValueError) {
        return '';
      }
    }
    elseif (!mb_check_encoding($decoded, 'UTF-8')) {
      $decoded = mb_convert_encoding($decoded, 'UTF-8', 'ISO-8859-1');
    }

    if ($mediaType === 'text/html') {
      return trim(html_entity_decode(strip_tags($decoded), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if ($mediaType !== 'text/plain') {
      return '';
    }

    return trim($decoded);
  }

  /** @return string[] */
  private function multipartParts(string $body, string $boundary): array {
    $delimiter = '--' . $boundary;
    $segments = explode($delimiter, $body);
    $parts = [];
    foreach ($segments as $segment) {
      $segment = ltrim($segment, "\r\n");
      $segment = rtrim($segment, "\r\n");
      if ($segment === '' || $segment === '--') {
        continue;
      }
      if (str_ends_with($segment, '--')) {
        $segment = substr($segment, 0, -2);
      }
      $segment = trim($segment, "\r\n");
      if ($segment !== '') {
        $parts[] = $segment;
      }
    }
    return $parts;
  }

  private function parameter(string $header, string $name): string {
    if (preg_match('/(?:^|;)\s*' . preg_quote($name, '/') . '\s*=\s*(?:"([^"]*)"|([^;\s]*))/i', $header, $matches) !== 1) {
      return '';
    }
    return trim((string) ($matches[1] !== '' ? $matches[1] : ($matches[2] ?? '')));
  }

  private function decodeHeader(string $value): string {
    if ($value === '') {
      return '';
    }
    if (function_exists('iconv_mime_decode')) {
      $decoded = iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
      if ($decoded !== FALSE) {
        return trim($decoded);
      }
    }
    return trim($value);
  }

  private function firstAddress(string $value): string {
    preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $match);
    return isset($match[0]) ? mb_strtolower($match[0]) : '';
  }
}
