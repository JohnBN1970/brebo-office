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
    if (trim($raw) === '') {
      throw new InvalidArgumentException('Leeg mailbericht.');
    }

    [$headerBlock, $body] = array_pad(preg_split("/\r?\n\r?\n/", $raw, 2) ?: [], 2, '');
    $headers = $this->headers($headerBlock);
    $from = $this->firstAddress((string) ($headers['from'] ?? ''));
    $subject = trim((string) ($headers['subject'] ?? ''));
    if ($from === '') {
      throw new InvalidArgumentException('From header bevat geen geldig adres.');
    }

    $contentType = mb_strtolower((string) ($headers['content-type'] ?? 'text/plain'));
    $transferEncoding = mb_strtolower(trim((string) ($headers['content-transfer-encoding'] ?? '')));
    $text = $this->decodeReadableBody($body, $contentType, $transferEncoding);

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

  private function decodeReadableBody(string $body, string $contentType, string $encoding): string {
    if (str_contains($contentType, 'multipart/')) {
      return '';
    }

    $decoded = match ($encoding) {
      'base64' => base64_decode(preg_replace('/\s+/', '', $body) ?: '', TRUE),
      'quoted-printable' => quoted_printable_decode($body),
      default => $body,
    };
    if ($decoded === FALSE) {
      return '';
    }

    if (str_contains($contentType, 'text/html')) {
      return trim(html_entity_decode(strip_tags($decoded), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if (!str_contains($contentType, 'text/plain') && !str_contains($contentType, 'text/html')) {
      return '';
    }

    return trim($decoded);
  }

  private function firstAddress(string $value): string {
    preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $match);
    return isset($match[0]) ? mb_strtolower($match[0]) : '';
  }
}
