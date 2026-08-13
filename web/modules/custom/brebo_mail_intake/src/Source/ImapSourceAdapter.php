<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Source;

use Drupal\Core\State\StateInterface;

/**
 * Provider-neutral read-only IMAP source for BREBO Mail Intake.
 *
 * One class is used for the permanent mailbox and staged migration sources.
 * It never deletes, moves, flags or marks source mail as read.
 */
final class ImapSourceAdapter implements MailSourceAdapterInterface {

  public function __construct(
    private readonly StateInterface $state,
    private readonly string $envPrefix = 'BREBO_IMAP',
    private readonly string $stateKey = 'imap',
  ) {}

  public function isConfigured(): bool {
    foreach (['HOST', 'USER', 'PASSWORD'] as $suffix) {
      if ($this->env($suffix) === '') {
        return FALSE;
      }
    }
    return (int) getenv('BREBO_MAIL_INTAKE_UID') > 0 && function_exists('imap_open');
  }

  /** {@inheritdoc} */
  public function messages(): iterable {
    if (!$this->isConfigured()) {
      return;
    }

    $host = $this->env('HOST');
    $port = max(1, (int) ($this->env('PORT') ?: 993));
    $folder = $this->env('FOLDER') ?: 'INBOX';
    $flags = $this->env('FLAGS') ?: '/imap/ssl';
    $mailbox = sprintf('{%s:%d%s}%s', $host, $port, $flags, $folder);

    $stream = @imap_open($mailbox, $this->env('USER'), $this->env('PASSWORD'), OP_READONLY);
    if ($stream === FALSE) {
      throw new \RuntimeException(sprintf('IMAP-bron %s kon niet read-only worden geopend.', $this->stateKey));
    }

    try {
      if ($this->stateKey === 'zoho_migration') {
        yield from $this->migrationMessages($stream, $folder);
        return;
      }

      yield from $this->incrementalMessages($stream, $folder);
    }
    finally {
      imap_close($stream);
    }
  }

  /**
   * Normal live IMAP polling: oldest unseen UID first, moving forward.
   *
   * @return iterable<array<string, mixed>>
   */
  private function incrementalMessages($stream, string $folder): iterable {
    $stateName = 'brebo_mail_intake.' . $this->stateKey . '_last_uid';
    $lastUid = (int) $this->state->get($stateName, 0);

    $uids = imap_search($stream, 'ALL', SE_UID) ?: [];
    $uids = array_values(array_filter($uids, static fn($uid): bool => (int) $uid > $lastUid));
    sort($uids, SORT_NUMERIC);

    $limit = max(1, min(500, (int) ($this->env('BATCH_LIMIT') ?: 100)));
    if (count($uids) > $limit) {
      $uids = array_slice($uids, 0, $limit);
    }

    $maxUid = $lastUid;
    foreach ($uids as $uid) {
      $uid = (int) $uid;
      if ($uid <= 0) {
        continue;
      }
      $maxUid = max($maxUid, $uid);
      yield $this->normalize($stream, $uid, $folder);
    }

    if ($maxUid > $lastUid) {
      $this->state->set($stateName, $maxUid);
    }
  }

  /**
   * Historical Zoho migration: newest first and progressively backwards.
   *
   * The cursor stores the smallest UID from the last completed batch. The next
   * run only considers older UIDs. Source-id/hash deduplication in the ingestor
   * makes restarting the migration safe, including mail already imported by an
   * earlier migration implementation.
   *
   * @return iterable<array<string, mixed>>
   */
  private function migrationMessages($stream, string $folder): iterable {
    $cursorState = 'brebo_mail_intake.' . $this->stateKey . '_before_uid';
    $completeState = 'brebo_mail_intake.' . $this->stateKey . '_complete';
    if ((bool) $this->state->get($completeState, FALSE)) {
      return;
    }

    $uids = array_values(array_map('intval', imap_search($stream, 'ALL', SE_UID) ?: []));
    $uids = array_values(array_filter($uids, static fn(int $uid): bool => $uid > 0));
    rsort($uids, SORT_NUMERIC);

    $beforeUid = (int) $this->state->get($cursorState, 0);
    if ($beforeUid > 0) {
      $uids = array_values(array_filter($uids, static fn(int $uid): bool => $uid < $beforeUid));
    }

    if ($uids === []) {
      $this->state->set($completeState, TRUE);
      return;
    }

    $limit = max(1, min(500, (int) ($this->env('BATCH_LIMIT') ?: 100)));
    $batch = array_slice($uids, 0, $limit);
    $minUid = NULL;

    foreach ($batch as $uid) {
      $minUid = $minUid === NULL ? $uid : min($minUid, $uid);
      yield $this->normalize($stream, $uid, $folder);
    }

    if ($minUid !== NULL) {
      $this->state->set($cursorState, $minUid);
    }

    if (count($uids) <= count($batch)) {
      $this->state->set($completeState, TRUE);
    }
  }

  /** @return array<string, mixed> */
  private function normalize($stream, int $uid, string $folder): array {
    $overviewRows = imap_fetch_overview($stream, (string) $uid, FT_UID);
    $overview = $overviewRows[0] ?? NULL;
    $header = imap_headerinfo($stream, imap_msgno($stream, $uid));

    $subject = $overview && isset($overview->subject) ? $this->decodeHeader((string) $overview->subject) : '(zonder onderwerp)';
    $messageId = $overview && isset($overview->message_id) ? trim((string) $overview->message_id) : '';
    $from = $header && isset($header->from) ? $this->addresses($header->from) : '';
    $to = $header && isset($header->to) ? $this->addresses($header->to) : '';
    $cc = $header && isset($header->cc) ? $this->addresses($header->cc) : '';
    $date = $overview && isset($overview->date) ? (string) $overview->date : '';
    $timestamp = $date !== '' ? strtotime($date) : FALSE;

    $body = $this->fetchReadableBody($stream, $uid);
    if ($body === '') {
      $body = '[Geen leesbare tekstinhoud; bronbericht blijft herleidbaar via IMAP UID.]';
    }

    $sourceId = $messageId !== ''
      ? 'message-id:' . $messageId
      : sprintf('%s:%s:%s:%d', $this->stateKey, $this->env('USER'), $folder, $uid);

    return [
      'source_id' => $sourceId,
      'source_hash' => hash('sha256', implode("\n", [$sourceId, $subject, $body, $from, $date])),
      'thread_id' => '',
      'subject' => $subject,
      'body' => $body,
      'from' => $from,
      'to' => implode("\n", array_filter([$to, $cc])),
      'received_at' => $timestamp !== FALSE ? gmdate(DATE_ATOM, $timestamp) : gmdate(DATE_ATOM),
      'direction' => $this->isOwnAddress($from) ? 'Uitgaand' : 'Inkomend',
    ];
  }

  private function fetchReadableBody($stream, int $uid): string {
    $structure = imap_fetchstructure($stream, $uid, FT_UID);
    if (!$structure) {
      return trim($this->ensureUtf8((string) imap_body($stream, $uid, FT_UID | FT_PEEK)));
    }

    $plain = $this->findPart($stream, $uid, $structure, '1', 'text/plain');
    if ($plain !== '') {
      return trim($plain);
    }
    $html = $this->findPart($stream, $uid, $structure, '1', 'text/html');
    return $html !== ''
      ? trim(strip_tags($html))
      : trim($this->ensureUtf8((string) imap_body($stream, $uid, FT_UID | FT_PEEK)));
  }

  private function findPart($stream, int $uid, object $part, string $partNumber, string $targetMime): string {
    $mime = strtolower(($part->type === 0 ? 'text' : 'application') . '/' . ($part->subtype ?? 'plain'));
    if ($mime === $targetMime) {
      $data = (string) imap_fetchbody($stream, $uid, $partNumber, FT_UID | FT_PEEK);
      $decoded = $this->decodeBody($data, (int) ($part->encoding ?? 0));
      return $this->ensureUtf8($decoded, $this->partCharset($part));
    }
    foreach ($part->parts ?? [] as $index => $child) {
      if (!is_object($child)) {
        continue;
      }
      $number = $partNumber . '.' . ($index + 1);
      $found = $this->findPart($stream, $uid, $child, $number, $targetMime);
      if ($found !== '') {
        return $found;
      }
    }
    return '';
  }

  private function decodeBody(string $value, int $encoding): string {
    return match ($encoding) {
      3 => (string) base64_decode($value, TRUE),
      4 => quoted_printable_decode($value),
      default => $value,
    };
  }

  private function decodeHeader(string $value): string {
    $parts = imap_mime_header_decode($value);
    $decoded = '';
    foreach ($parts as $part) {
      $decoded .= $this->ensureUtf8((string) $part->text, (string) ($part->charset ?? ''));
    }
    return trim($decoded);
  }

  private function partCharset(object $part): string {
    foreach (array_merge($part->parameters ?? [], $part->dparameters ?? []) as $parameter) {
      if (!is_object($parameter)) {
        continue;
      }
      if (strcasecmp((string) ($parameter->attribute ?? ''), 'charset') === 0) {
        return trim((string) ($parameter->value ?? ''));
      }
    }
    return '';
  }

  private function ensureUtf8(string $value, string $declaredCharset = ''): string {
    if ($value === '') {
      return '';
    }

    $charset = trim($declaredCharset, " \t\n\r\0\x0B\"'");
    if ($charset !== '' && strcasecmp($charset, 'default') !== 0 && strcasecmp($charset, 'utf-8') !== 0 && strcasecmp($charset, 'utf8') !== 0) {
      $converted = @mb_convert_encoding($value, 'UTF-8', $charset);
      if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
        return $converted;
      }
    }

    if (mb_check_encoding($value, 'UTF-8')) {
      return $value;
    }

    foreach (['Windows-1252', 'ISO-8859-1'] as $fallback) {
      $converted = @mb_convert_encoding($value, 'UTF-8', $fallback);
      if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
        return $converted;
      }
    }

    return (string) iconv('UTF-8', 'UTF-8//IGNORE', $value);
  }

  /** @param array<int, object> $addresses */
  private function addresses(array $addresses): string {
    $result = [];
    foreach ($addresses as $address) {
      $mailbox = (string) ($address->mailbox ?? '');
      $host = (string) ($address->host ?? '');
      if ($mailbox !== '' && $host !== '') {
        $result[] = $mailbox . '@' . $host;
      }
    }
    return implode(', ', $result);
  }

  private function isOwnAddress(string $from): bool {
    $own = mb_strtolower(trim((string) (getenv('BREBO_MAIL_ADDRESS') ?: $this->env('USER'))));
    return $own !== '' && str_contains(mb_strtolower($from), $own);
  }

  private function env(string $suffix): string {
    return trim((string) getenv($this->envPrefix . '_' . $suffix));
  }

}
