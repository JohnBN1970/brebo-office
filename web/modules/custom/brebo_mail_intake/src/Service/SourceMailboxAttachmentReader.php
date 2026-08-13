<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

/**
 * Read-only retrieval of immutable attachment bytes from configured IMAP sources.
 *
 * Supports both current source_mailbox keys (`<source_id>#part=<section>`) and
 * BREBO IMAP fallback source IDs. It never changes source flags or messages.
 */
final class SourceMailboxAttachmentReader {

  private const MAX_BYTES = 104857600;

  /**
   * @return array{state:string,content?:string,mime_type?:string,filename?:string,message?:string}
   */
  public function read(string $sourceSystem, string $storageKey): array {
    if (!function_exists('imap_open')) {
      return ['state' => 'imap_unavailable', 'message' => 'PHP IMAP is niet beschikbaar.'];
    }

    $sourceSystem = strtolower(trim($sourceSystem));
    $storageKey = trim($storageKey);
    if ($storageKey === '' || !preg_match('/^(.*)#part=([0-9]+(?:\.[0-9]+)*)$/', $storageKey, $matches)) {
      return ['state' => 'invalid_storage_key', 'message' => 'De bronverwijzing bevat geen geldige MIME-sectie.'];
    }

    $sourceId = trim($matches[1]);
    $section = $matches[2];
    $envPrefix = match ($sourceSystem) {
      'zoho_migration' => 'BREBO_ZOHO_IMAP',
      'imap' => 'BREBO_IMAP',
      default => '',
    };
    if ($envPrefix === '') {
      return ['state' => 'unsupported_source', 'message' => 'Deze bronprovider heeft nog geen attachment-reader.'];
    }

    $host = $this->env($envPrefix, 'HOST');
    $user = $this->env($envPrefix, 'USER');
    $password = $this->env($envPrefix, 'PASSWORD');
    $port = max(1, (int) ($this->env($envPrefix, 'PORT') ?: 993));
    $folder = $this->env($envPrefix, 'FOLDER') ?: 'INBOX';
    $flags = $this->env($envPrefix, 'FLAGS') ?: '/imap/ssl';
    if ($host === '' || $user === '' || $password === '') {
      return ['state' => 'source_not_configured', 'message' => 'De bronmailbox is niet volledig geconfigureerd.'];
    }

    $mailbox = sprintf('{%s:%d%s}%s', $host, $port, $flags, $folder);
    $stream = @imap_open($mailbox, $user, $password, OP_READONLY);
    if ($stream === false) {
      return ['state' => 'source_unavailable', 'message' => 'De bronmailbox kon niet read-only worden geopend.'];
    }

    try {
      $uid = $this->resolveUid($stream, $sourceSystem, $sourceId, $user, $folder);
      if ($uid <= 0) {
        return ['state' => 'message_not_found', 'message' => 'Het oorspronkelijke bronbericht kon niet worden teruggevonden.'];
      }

      $structure = imap_fetchstructure($stream, $uid, FT_UID);
      if (!is_object($structure)) {
        return ['state' => 'message_structure_unavailable', 'message' => 'De MIME-structuur van het bronbericht is niet beschikbaar.'];
      }
      $part = $this->partForSection($structure, $section);
      if (!is_object($part)) {
        return ['state' => 'attachment_not_found', 'message' => 'De oorspronkelijke MIME-bijlage kon niet worden teruggevonden.'];
      }
      $declaredBytes = max(0, (int) ($part->bytes ?? 0));
      if ($declaredBytes > self::MAX_BYTES) {
        return ['state' => 'attachment_too_large', 'message' => 'De bronbijlage is groter dan de veilige uitleeslimiet.'];
      }

      $raw = imap_fetchbody($stream, $uid, $section, FT_UID | FT_PEEK);
      if (!is_string($raw)) {
        return ['state' => 'attachment_unavailable', 'message' => 'De bronbijlage kon niet worden gelezen.'];
      }
      $content = $this->decodeBody($raw, (int) ($part->encoding ?? 0));
      if (strlen($content) > self::MAX_BYTES) {
        return ['state' => 'attachment_too_large', 'message' => 'De bronbijlage is groter dan de veilige uitleeslimiet.'];
      }

      return [
        'state' => 'available',
        'content' => $content,
        'mime_type' => $this->mimeType($part),
        'filename' => $this->attachmentFilename($part),
      ];
    }
    finally {
      imap_close($stream);
    }
  }

  private function resolveUid($stream, string $sourceSystem, string $sourceId, string $user, string $folder): int {
    if (str_starts_with($sourceId, 'message-id:')) {
      $messageId = trim(substr($sourceId, strlen('message-id:')));
      if ($messageId === '') {
        return 0;
      }
      $escaped = addcslashes($messageId, "\\\"");
      $uids = imap_search($stream, 'HEADER Message-ID "' . $escaped . '"', SE_UID) ?: [];
      return isset($uids[0]) ? (int) $uids[0] : 0;
    }

    $prefix = $sourceSystem . ':' . $user . ':' . $folder . ':';
    if (str_starts_with($sourceId, $prefix)) {
      $uid = substr($sourceId, strlen($prefix));
      return ctype_digit($uid) ? (int) $uid : 0;
    }

    // Historical fallback: source IDs may contain a previous user/folder value.
    if (str_starts_with($sourceId, $sourceSystem . ':')) {
      $lastColon = strrpos($sourceId, ':');
      if ($lastColon !== false) {
        $uid = substr($sourceId, $lastColon + 1);
        return ctype_digit($uid) ? (int) $uid : 0;
      }
    }
    return 0;
  }

  private function partForSection(object $structure, string $section): ?object {
    $segments = array_map('intval', explode('.', $section));
    $part = $structure;
    foreach ($segments as $depth => $segment) {
      if ($segment <= 0) {
        return null;
      }
      if ($depth === 0 && empty($part->parts)) {
        return $segment === 1 ? $part : null;
      }
      $children = $part->parts ?? [];
      $index = $segment - 1;
      if (!isset($children[$index]) || !is_object($children[$index])) {
        return null;
      }
      $part = $children[$index];
    }
    return $part;
  }

  private function decodeBody(string $data, int $encoding): string {
    return match ($encoding) {
      3 => base64_decode($data, true) ?: '',
      4 => quoted_printable_decode($data),
      default => $data,
    };
  }

  private function attachmentFilename(object $part): string {
    foreach (array_merge($part->dparameters ?? [], $part->parameters ?? []) as $parameter) {
      if (!is_object($parameter)) {
        continue;
      }
      $attribute = strtolower((string) ($parameter->attribute ?? ''));
      if (($attribute === 'filename' || $attribute === 'name') && trim((string) ($parameter->value ?? '')) !== '') {
        $value = trim((string) $parameter->value);
        $decoded = imap_mime_header_decode($value);
        if (is_array($decoded) && $decoded !== []) {
          return implode('', array_map(static fn($item): string => (string) ($item->text ?? ''), $decoded));
        }
        return $value;
      }
    }
    return '';
  }

  private function mimeType(object $part): string {
    $primary = match ((int) ($part->type ?? 0)) {
      0 => 'text',
      1 => 'multipart',
      2 => 'message',
      3 => 'application',
      4 => 'audio',
      5 => 'image',
      6 => 'video',
      default => 'application',
    };
    $subtype = strtolower(trim((string) ($part->subtype ?? 'octet-stream')));
    return $primary . '/' . ($subtype !== '' ? $subtype : 'octet-stream');
  }

  private function env(string $prefix, string $suffix): string {
    $value = getenv($prefix . '_' . $suffix);
    return is_string($value) ? trim($value) : '';
  }

}
