<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

/**
 * Turns already-extracted attachment text into traceable mail evidence.
 *
 * Attachments are evidence, never canonical truth. Every fragment remains tied
 * to its filename, page, MIME type and content hash where available.
 */
final class AttachmentEvidenceExtractor {

  /**
   * @param array<string, mixed> $mail
   *
   * @return array{context_text:string,evidence:array<int,array<string,mixed>>,attachment_count:int,usable_count:int,basis:string}
   */
  public function extract(array $mail): array {
    $attachments = is_array($mail['attachments'] ?? NULL) ? $mail['attachments'] : [];
    $evidence = [];
    $contextParts = [];
    $usableAttachments = [];

    foreach ($attachments as $index => $attachment) {
      if (!is_array($attachment)) {
        continue;
      }

      $filename = $this->clean($attachment['filename'] ?? $attachment['name'] ?? ('bijlage-' . ($index + 1)));
      $mime = strtolower($this->clean($attachment['mime_type'] ?? $attachment['mime'] ?? 'application/octet-stream'));
      $hash = $this->clean($attachment['sha256'] ?? $attachment['content_hash'] ?? '');
      $state = $this->clean($attachment['extraction_state'] ?? 'unknown');
      $pages = is_array($attachment['extracted_pages'] ?? NULL) ? $attachment['extracted_pages'] : [];

      if ($pages === [] && trim((string) ($attachment['extracted_text'] ?? '')) !== '') {
        $pages = [[
          'page' => NULL,
          'text' => (string) $attachment['extracted_text'],
        ]];
      }

      $attachmentUsable = FALSE;
      foreach ($pages as $pageIndex => $page) {
        if (!is_array($page)) {
          continue;
        }
        $text = $this->normalizeText((string) ($page['text'] ?? ''));
        if ($text === '') {
          continue;
        }

        $pageNumber = isset($page['page']) && is_numeric($page['page'])
          ? max(1, (int) $page['page'])
          : ($pages === [] ? NULL : $pageIndex + 1);
        $sourceLabel = $pageNumber !== NULL
          ? sprintf('Bijlage: %s, pagina %d', $filename, $pageNumber)
          : sprintf('Bijlage: %s', $filename);

        $evidence[] = [
          'filename' => $filename,
          'page' => $pageNumber,
          'mime_type' => $mime,
          'sha256' => $hash,
          'extraction_state' => $state,
          'text' => $text,
          'source_label' => $sourceLabel,
          'confidence' => $state === 'extracted' ? 0.90 : 0.70,
          'canonical_truth' => FALSE,
        ];
        $contextParts[] = '[' . $sourceLabel . "]\n" . $text;
        $attachmentUsable = TRUE;
      }

      if ($attachmentUsable) {
        $usableAttachments[$filename . '|' . $hash] = TRUE;
      }
    }

    $usableCount = count($usableAttachments);
    return [
      'context_text' => implode("\n\n", $contextParts),
      'evidence' => $evidence,
      'attachment_count' => count($attachments),
      'usable_count' => $usableCount,
      'basis' => $usableCount > 0
        ? sprintf('%d bijlage(n) leverden traceerbare tekstbewijzen; bijlagen gelden niet als canonieke waarheid.', $usableCount)
        : 'Geen bruikbare geëxtraheerde bijlage-inhoud beschikbaar.',
    ];
  }

  private function normalizeText(string $text): string {
    $text = str_replace("\0", '', $text);
    $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
    $text = preg_replace('/\R{3,}/u', "\n\n", $text) ?? $text;
    return trim($text);
  }

  private function clean(mixed $value): string {
    return trim((string) ($value ?? ''));
  }

}
