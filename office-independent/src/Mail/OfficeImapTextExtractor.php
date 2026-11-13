<?php
declare(strict_types=1);

namespace Brebo\Office\Mail;

/** Conservative plain-text MIME extractor; HTML is deliberately never persisted as rendered markup. */
final class OfficeImapTextExtractor {
  public static function extract($stream, int $messageNumber, int $maxBytes = 2097152): ?string {
    $structure = imap_fetchstructure($stream, $messageNumber);
    if (!$structure) throw new \RuntimeException('IMAP MIME structure unavailable.');
    return self::walk($stream, $messageNumber, $structure, '', $maxBytes);
  }

  private static function walk($stream, int $number, object $part, string $section, int $limit): ?string {
    if (isset($part->parts) && is_array($part->parts)) {
      foreach ($part->parts as $index => $child) {
        $next = $section === '' ? (string) ($index + 1) : $section . '.' . ($index + 1);
        $text = self::walk($stream, $number, $child, $next, $limit);
        if ($text !== null) return $text;
      }
      return null;
    }
    if ((int) ($part->type ?? -1) !== 0 || strtoupper((string) ($part->subtype ?? '')) !== 'PLAIN') return null;
    if (!empty($part->ifdisposition) && strtoupper((string) ($part->disposition ?? '')) === 'ATTACHMENT') return null;
    if ((int) ($part->bytes ?? 0) > $limit * 2) return null;
    $raw = $section === '' ? imap_body($stream, $number, FT_PEEK)
      : imap_fetchbody($stream, $number, $section, FT_PEEK);
    if (!is_string($raw)) throw new \RuntimeException('IMAP message body fetch failed.');
    if (strlen($raw) > $limit * 2) return null;
    $decoded = match ((int) ($part->encoding ?? 0)) {
      3 => base64_decode($raw, true),
      4 => quoted_printable_decode($raw),
      default => $raw,
    };
    if (!is_string($decoded) || strlen($decoded) > $limit) return null;
    $charset = 'UTF-8';
    foreach (($part->parameters ?? []) as $parameter) {
      if (strtolower((string) ($parameter->attribute ?? '')) === 'charset') {
        $charset = (string) ($parameter->value ?? 'UTF-8');
        break;
      }
    }
    if (strcasecmp($charset, 'UTF-8') !== 0) {
      if (!function_exists('iconv')) return null;
      $converted = @iconv($charset, 'UTF-8//IGNORE', $decoded);
      if (!is_string($converted)) return null;
      $decoded = $converted;
    }
    if (!preg_match('//u', $decoded)) return null;
    return str_replace("\0", '', $decoded);
  }
}
