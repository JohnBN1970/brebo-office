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

  private const MAX_ATTACHMENT_BYTES = 15728640;
  private const MAX_PDF_EVIDENCE_PAGES = 20;
  private const MAX_PAGE_TEXT = 6000;

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

  /** @return iterable<array<string, mixed>> */
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

  /** @return iterable<array<string, mixed>> */
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

    $subject = $overview && isset($overview->subject)
      ? trim($this->decodeHeader((string) $overview->subject))
      : '';
    if ($subject === '') {
      $subject = '(zonder onderwerp)';
    }
    $messageId = $overview && isset($overview->message_id) ? trim((string) $overview->message_id) : '';
    $from = $header && isset($header->from) ? $this->addresses($header->from) : '';
    $to = $header && isset($header->to) ? $this->addresses($header->to) : '';
    $cc = $header && isset($header->cc) ? $this->addresses($header->cc) : '';
    $date = $overview && isset($overview->date) ? (string) $overview->date : '';
    $timestamp = $date !== '' ? strtotime($date) : FALSE;

    // Some legacy Zoho messages have no fetchable MIME structure. That is not
    // fatal: fetchReadableBody() falls back to the complete body read-only.
    $structure = @imap_fetchstructure($stream, $uid, FT_UID);
    $bodyParts = $this->fetchReadableBody($stream, $uid, $structure ?: NULL);
    $body = $bodyParts['text'];
    $bodyHtml = $bodyParts['html'];
    if ($body === '') {
      $body = '[Geen leesbare tekstinhoud; bronbericht blijft herleidbaar via IMAP UID.]';
    }
    // Root multipart children are IMAP sections 1, 2, ... (not 1.1, 1.2).
    $attachments = $structure ? $this->extractAttachments($stream, $uid, $structure, '') : [];

    $sourceId = $messageId !== ''
      ? 'message-id:' . $messageId
      : sprintf('%s:%s:%s:%d', $this->stateKey, $this->env('USER'), $folder, $uid);

    return [
      'source_id' => $sourceId,
      'source_system' => $this->stateKey,
      'source_hash' => hash('sha256', implode("\n", [$sourceId, $subject, $body, $from, $date])),
      'thread_id' => '',
      'subject' => $subject,
      'body' => $body,
      'body_html' => $bodyHtml,
      'from' => $from,
      'to' => implode("\n", array_filter([$to, $cc])),
      'received_at' => $timestamp !== FALSE ? gmdate(DATE_ATOM, $timestamp) : gmdate(DATE_ATOM),
      'direction' => $this->isOwnAddress($from) ? 'Uitgaand' : 'Inkomend',
      'attachments' => $attachments,
    ];
  }

  /** @return array{text:string,html:string} */
  private function fetchReadableBody($stream, int $uid, ?object $structure = NULL): array {
    $structure ??= @imap_fetchstructure($stream, $uid, FT_UID) ?: NULL;
    if (!$structure) {
      return [
        'text' => trim($this->ensureUtf8((string) imap_body($stream, $uid, FT_UID | FT_PEEK))),
        'html' => '',
      ];
    }

    $plain = trim($this->findPart($stream, $uid, $structure, '', 'text/plain'));
    $html = trim($this->findPart($stream, $uid, $structure, '', 'text/html'));

    return [
      'text' => $plain !== '' ? $plain : $this->htmlToReadableText($html),
      'html' => $html,
    ];
  }

  private function htmlToReadableText(string $html): string {
    if ($html === '') {
      return '';
    }

    $withBreaks = preg_replace(
      '/<\\/?(?:address|article|aside|blockquote|br|div|footer|h[1-6]|header|hr|li|main|ol|p|pre|section|table|td|th|tr|ul)\\b[^>]*>/iu',
      "\n",
      $html,
    );
    $text = html_entity_decode(strip_tags((string) $withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/[ \\t]+\n/u", "\n", $text);
    $text = preg_replace("/\n{3,}/u", "\n\n", (string) $text);
    return trim((string) $text);
  }

  private function findPart($stream, int $uid, object $part, string $partNumber, string $targetMime): string {
    $mime = $this->mimeType($part);
    if ($mime === $targetMime && $this->attachmentFilename($part) === '') {
      $section = $partNumber !== '' ? $partNumber : '1';
      $data = (string) imap_fetchbody($stream, $uid, $section, FT_UID | FT_PEEK);
      $decoded = $this->decodeBody($data, (int) ($part->encoding ?? 0));
      return $this->ensureUtf8($decoded, $this->partCharset($part));
    }
    foreach ($part->parts ?? [] as $index => $child) {
      if (!is_object($child)) {
        continue;
      }
      $number = $this->childPartNumber($partNumber, $index);
      $found = $this->findPart($stream, $uid, $child, $number, $targetMime);
      if ($found !== '') {
        return $found;
      }
    }
    return '';
  }

  /** @return array<int, array<string, mixed>> */
  private function extractAttachments($stream, int $uid, object $part, string $partNumber): array {
    $result = [];
    $filename = $this->attachmentFilename($part);
    if ($filename !== '') {
      $section = $partNumber !== '' ? $partNumber : '1';
      $raw = (string) imap_fetchbody($stream, $uid, $section, FT_UID | FT_PEEK);
      $content = $this->decodeBody($raw, (int) ($part->encoding ?? 0));
      $mime = $this->mimeType($part);
      $attachment = [
        'filename' => $filename,
        'mime_type' => $mime,
        'sha256' => hash('sha256', $content),
        'size' => strlen($content),
        'source_part' => $section,
        'extraction_state' => 'metadata_only',
        'extracted_pages' => [],
      ];

      if (strlen($content) <= self::MAX_ATTACHMENT_BYTES) {
        if ($mime === 'application/pdf') {
          $pages = $this->extractPdfPages($content);
          if ($pages !== []) {
            $attachment['extraction_state'] = 'extracted';
            $attachment['extracted_pages'] = $pages;
          }
          else {
            $attachment['extraction_state'] = 'pdf_text_unavailable';
          }
        }
        elseif ($mime === 'text/plain' || $mime === 'text/html') {
          $text = $this->ensureUtf8($content, $this->partCharset($part));
          if ($mime === 'text/html') {
            $text = strip_tags($text);
          }
          $text = trim($text);
          if ($text !== '') {
            $attachment['extraction_state'] = 'extracted';
            $attachment['extracted_pages'] = [[
              'page' => 1,
              'text' => mb_substr($text, 0, self::MAX_PAGE_TEXT),
            ]];
          }
        }
      }
      else {
        $attachment['extraction_state'] = 'too_large';
      }
      $result[] = $attachment;
    }

    foreach ($part->parts ?? [] as $index => $child) {
      if (!is_object($child)) {
        continue;
      }
      $result = array_merge($result, $this->extractAttachments($stream, $uid, $child, $this->childPartNumber($partNumber, $index)));
    }
    return $result;
  }

  private function childPartNumber(string $parent, int $zeroBasedIndex): string {
    $child = (string) ($zeroBasedIndex + 1);
    return $parent === '' ? $child : $parent . '.' . $child;
  }

  /** @return array<int, array{page:int,text:string}> */
  private function extractPdfPages(string $content): array {
    if (!function_exists('exec')) {
      return [];
    }
    $binary = trim((string) @shell_exec('command -v pdftotext 2>/dev/null'));
    if ($binary === '') {
      return [];
    }

    $base = tempnam(sys_get_temp_dir(), 'brebo_pdf_');
    if ($base === FALSE) {
      return [];
    }
    $pdfPath = $base . '.pdf';
    $txtPath = $base . '.txt';
    @unlink($base);

    try {
      if (file_put_contents($pdfPath, $content) === FALSE) {
        return [];
      }
      $command = escapeshellarg($binary) . ' -layout ' . escapeshellarg($pdfPath) . ' ' . escapeshellarg($txtPath) . ' 2>/dev/null';
      $output = [];
      $exitCode = 1;
      exec($command, $output, $exitCode);
      if ($exitCode !== 0 || !is_file($txtPath)) {
        return [];
      }
      $text = (string) file_get_contents($txtPath);
      if (trim($text) === '') {
        return [];
      }
      return $this->selectRelevantPdfPages(explode("\f", $text));
    }
    finally {
      @unlink($pdfPath);
      @unlink($txtPath);
    }
  }

  /** @param string[] $pages
   *  @return array<int, array{page:int,text:string}>
   */
  private function selectRelevantPdfPages(array $pages): array {
    $selected = [];
    foreach ($pages as $index => $pageText) {
      $text = trim($this->ensureUtf8($pageText));
      if ($text === '') {
        continue;
      }
      $hasPostcode = preg_match('/\b[1-9][0-9]{3}\s?[A-Z]{2}\b/iu', $text) === 1;
      $hasAddressLabel = preg_match('/\b(?:adres|werkadres|locatie|projectadres|objectadres)\b/iu', $text) === 1;
      if ($index < 5 || $hasPostcode || $hasAddressLabel) {
        $selected[] = [
          'page' => $index + 1,
          'text' => mb_substr($text, 0, self::MAX_PAGE_TEXT),
        ];
      }
      if (count($selected) >= self::MAX_PDF_EVIDENCE_PAGES) {
        break;
      }
    }
    return $selected;
  }

  private function mimeType(object $part): string {
    $primary = [0 => 'text', 1 => 'multipart', 2 => 'message', 3 => 'application', 4 => 'audio', 5 => 'image', 6 => 'video', 7 => 'other'];
    $type = $primary[(int) ($part->type ?? 7)] ?? 'other';
    $subtype = strtolower((string) ($part->subtype ?? 'octet-stream'));
    return strtolower($type . '/' . $subtype);
  }

  private function attachmentFilename(object $part): string {
    foreach (['dparameters', 'parameters'] as $property) {
      $parameters = $part->{$property} ?? [];
      if (is_object($parameters)) {
        $parameters = [$parameters];
      }
      if (!is_array($parameters)) {
        continue;
      }
      foreach ($parameters as $parameter) {
        if (!is_object($parameter)) {
          continue;
        }
        $attribute = strtolower((string) ($parameter->attribute ?? ''));
        if ($attribute === 'filename' || $attribute === 'name') {
          return $this->decodeHeader((string) ($parameter->value ?? ''));
        }
      }
    }
    return '';
  }

  private function partCharset(object $part): string {
    $parameters = $part->parameters ?? [];
    if (is_object($parameters)) {
      $parameters = [$parameters];
    }
    if (!is_array($parameters)) {
      return 'UTF-8';
    }
    foreach ($parameters as $parameter) {
      if (is_object($parameter) && strtolower((string) ($parameter->attribute ?? '')) === 'charset') {
        return (string) ($parameter->value ?? 'UTF-8');
      }
    }
    return 'UTF-8';
  }

  private function decodeBody(string $data, int $encoding): string {
    return match ($encoding) {
      3 => base64_decode($data, TRUE) ?: '',
      4 => quoted_printable_decode($data),
      default => $data,
    };
  }

  private function ensureUtf8(string $value, string $charset = 'UTF-8'): string {
    if ($value === '') {
      return '';
    }
    if (strtoupper($charset) !== 'UTF-8') {
      $converted = @mb_convert_encoding($value, 'UTF-8', $charset);
      if (is_string($converted)) {
        return $converted;
      }
    }
    return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'auto');
  }

  private function decodeHeader(string $value): string {
    if ($value === '') {
      return '';
    }
    $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
    return is_string($decoded) ? trim($decoded) : trim($value);
  }

  private function addresses(mixed $addresses): string {
    if (!is_array($addresses)) {
      return '';
    }
    $result = [];
    foreach ($addresses as $address) {
      if (!is_object($address)) {
        continue;
      }
      $mailbox = (string) ($address->mailbox ?? '');
      $host = (string) ($address->host ?? '');
      if ($mailbox !== '' && $host !== '') {
        $result[] = strtolower($mailbox . '@' . $host);
      }
    }
    return implode(', ', array_values(array_unique($result)));
  }

  private function isOwnAddress(string $from): bool {
    $own = array_filter(array_map(
      static fn(string $value): string => strtolower(trim($value)),
      explode(',', $this->env('OWN_ADDRESSES')),
    ));
    if ($own === []) {
      $own = [strtolower($this->env('USER'))];
    }
    foreach ($own as $address) {
      if ($address !== '' && str_contains(strtolower($from), $address)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  private function env(string $suffix): string {
    return trim((string) getenv($this->envPrefix . '_' . $suffix));
  }
}
