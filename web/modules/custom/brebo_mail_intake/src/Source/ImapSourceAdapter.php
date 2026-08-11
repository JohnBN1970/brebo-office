<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Source;

use Drupal\Core\State\StateInterface;

/**
 * Provider-neutral read-only IMAP source for BREBO Mail Intake.
 *
 * Designed for the permanent info@brebobv.nl mailbox and for staged Zoho
 * migration. The adapter never deletes, moves, flags or marks source mail read.
 */
final class ImapSourceAdapter implements MailSourceAdapterInterface {

  public function __construct(
    private readonly StateInterface $state,
  ) {}

  public function isConfigured(): bool {
    foreach (['BREBO_IMAP_HOST', 'BREBO_IMAP_USER', 'BREBO_IMAP_PASSWORD', 'BREBO_MAIL_INTAKE_UID'] as $name) {
      if (trim((string) getenv($name)) === '') {
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

    $host = trim((string) getenv('BREBO_IMAP_HOST'));
    $port = max(1, (int) (getenv('BREBO_IMAP_PORT') ?: 993));
    $folder = trim((string) (getenv('BREBO_IMAP_FOLDER') ?: 'INBOX'));
    $flags = trim((string) (getenv('BREBO_IMAP_FLAGS') ?: '/imap/ssl'));
    $mailbox = sprintf('{%s:%d%s}%s', $host, $port, $flags, $folder);

    $stream = @imap_open(
      $mailbox,
      (string) getenv('BREBO_IMAP_USER'),
      (string) getenv('BREBO_IMAP_PASSWORD'),
      OP_READONLY,
    );
    if ($stream === FALSE) {
      throw new \RuntimeException('IMAP-bron kon niet read-only worden geopend.');
    }

    try {
      $lastUid = (int) $this->state->get('brebo_mail_intake.imap_last_uid', 0);
      $criteria = $lastUid > 0 ? 'UID ' . ($lastUid + 1) . ':*' : 'ALL';
      $uids = imap_search($stream, $criteria, SE_UID) ?: [];
      sort($uids, SORT_NUMERIC);

      $limit = max(1, min(500, (int) (getenv('BREBO_IMAP_BATCH_LIMIT') ?: 100)));
      if (count($uids) > $limit) {
        $uids = array_slice($uids, 0, $limit);
      }

      $maxUid = $lastUid;
      foreach ($uids as $uid) {
        $uid = (int) $uid;
        if ($uid <= 0) {
          continue;
        }
        $message = $this->normalize($stream, $uid);
        $maxUid = max($maxUid, $uid);
        yield $message;
      }

      if ($maxUid > $lastUid) {
        $this->state->set('brebo_mail_intake.imap_last_uid', $maxUid);
      }
    }
    finally {
      imap_close($stream, CL_EXPUNGE);
    }
  }

  /** @return array<string, mixed> */
  private function normalize($stream, int $uid): array {
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

    $sourceId = $messageId !== '' ? 'message-id:' . $messageId : sprintf('imap:%s:%s:%d', (string) getenv('BREBO_IMAP_USER'), (string) (getenv('BREBO_IMAP_FOLDER') ?: 'INBOX'), $uid);

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
      return trim((string) imap_body($stream, $uid, FT_UID | FT_PEEK));
    }

    $plain = $this->findPart($stream, $uid, $structure, '1', 'text/plain');
    if ($plain !== '') {
      return trim($plain);
    }
    $html = $this->findPart($stream, $uid, $structure, '1', 'text/html');
    return $html !== '' ? trim(strip_tags($html)) : trim((string) imap_body($stream, $uid, FT_UID | FT_PEEK));
  }

  private function findPart($stream, int $uid, object $part, string $partNumber, string $targetMime): string {
    $mime = strtolower(($part->type === 0 ? 'text' : 'application') . '/' . ($part->subtype ?? 'plain'));
    if ($mime === $targetMime) {
      $data = (string) imap_fetchbody($stream, $uid, $partNumber, FT_UID | FT_PEEK);
      return $this->decodeBody($data, (int) ($part->encoding ?? 0));
    }
    foreach ($part->parts ?? [] as $index => $child) {
      if (!is_object($child)) {
        continue;
      }
      $number = isset($part->parts) ? $partNumber . '.' . ($index + 1) : (string) ($index + 1);
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
      $decoded .= (string) $part->text;
    }
    return trim($decoded);
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
    $own = mb_strtolower(trim((string) (getenv('BREBO_MAIL_ADDRESS') ?: getenv('BREBO_IMAP_USER'))));
    return $own !== '' && str_contains(mb_strtolower($from), $own);
  }

}
