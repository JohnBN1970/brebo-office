<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_data_intake\Service\SourceNeutralIntakeManager;
use Drupal\brebo_mail_intake\Service\PurchaseInvoiceMailRouterInterface;

/** Classifies invoice mail, then hands it to the source-neutral intake pipeline. */
final class MailPurchaseInvoiceRouter implements PurchaseInvoiceMailRouterInterface {

  private const MAILBOX = 'facturen@brebobv.nl';

  public function __construct(private readonly ?SourceNeutralIntakeManager $intakeManager) {}

  public function route(array $mail, array $attachmentEvidence, int $communicationNid): array {
    if ($this->intakeManager === NULL) {
      return ['state' => 'intake_unavailable', 'reason' => 'source_neutral_intake_service_not_enabled'];
    }
    if (!$this->containsAddress((string) ($mail['to'] ?? ''), self::MAILBOX)) {
      return ['state' => 'not_invoice_mailbox'];
    }

    $subject = trim((string) ($mail['subject'] ?? ''));
    $body = trim((string) ($mail['body'] ?? ''));
    $evidenceText = trim((string) ($attachmentEvidence['context_text'] ?? ''));
    $text = trim($subject . "\n" . $body . "\n" . $evidenceText);
    $lower = mb_strtolower($text);

    if ($this->isCollectionMessage($lower)) {
      return ['state' => 'financial_correspondence', 'reason' => 'collection_or_reminder'];
    }
    if (!$this->looksLikeInvoice($lower, $mail)) {
      return ['state' => 'communication_only', 'reason' => 'insufficient_invoice_evidence'];
    }

    $invoiceNumber = $this->invoiceNumber($text);
    $amounts = $this->amounts($text);
    $from = $this->firstAddress((string) ($mail['from'] ?? ''));
    if ($invoiceNumber === '' || $from === '' || $amounts === NULL) {
      return ['state' => 'communication_only', 'reason' => 'required_invoice_fields_missing'];
    }

    $sourceRecordId = trim((string) ($mail['source_id'] ?? $mail['source_hash'] ?? ''));
    $attachments = is_array($mail['attachments'] ?? NULL) ? $mail['attachments'] : [];
    if ($attachmentEvidence !== []) {
      $attachments[] = ['type' => 'mail_attachment_evidence', 'evidence' => $attachmentEvidence];
    }

    $result = $this->intakeManager->intake([
      'source' => 'email',
      'source_record_id' => $sourceRecordId,
      'classification' => 'purchase_invoice',
      'confidence' => 1.0,
      'canonical' => ['supplier_ref' => $from],
      'payload' => [
        'supplier_ref' => $from,
        'supplier_name' => $this->supplierName($from),
        'invoice_number' => $invoiceNumber,
        'invoice_date' => $this->invoiceDate($text, (string) ($mail['received_at'] ?? '')),
        'amount_ex_vat' => $amounts['ex'],
        'vat_amount' => $amounts['vat'],
        'amount_inc_vat' => $amounts['inc'],
        'regular_account_amount' => $amounts['inc'],
        'currency' => 'EUR',
        'communication_nid' => $communicationNid,
        'mailbox' => self::MAILBOX,
      ],
      'attachments' => $attachments,
      'received_at' => $this->timestamp((string) ($mail['received_at'] ?? '')),
    ]);

    $destination = is_array($result['destination'] ?? NULL) ? $result['destination'] : [];
    return $destination !== [] ? $destination : $result;
  }

  private function isCollectionMessage(string $text): bool {
    foreach (['betalingsherinnering', 'herinnering', 'aanmaning', 'sommatie', 'incasso', 'payment reminder', 'reminder', 'past due', 'overdue notice', 'laatste waarschuwing'] as $term) {
      if (str_contains($text, $term)) return TRUE;
    }
    return FALSE;
  }

  private function looksLikeInvoice(string $text, array $mail): bool {
    $invoiceCue = str_contains($text, 'factuur') || str_contains($text, 'invoice') || str_contains($text, 'creditnota') || str_contains($text, 'credit note');
    if (!$invoiceCue) return FALSE;
    foreach (($mail['attachments'] ?? []) as $attachment) {
      if (is_array($attachment) && strtolower((string) ($attachment['mime_type'] ?? '')) === 'application/pdf') return TRUE;
    }
    return preg_match('/\b(factuurnummer|factuur\s*nr\.?|invoice\s*(number|no\.?))\b/iu', $text) === 1;
  }

  private function invoiceNumber(string $text): string {
    $patterns = [
      '/(?:factuurnummer|factuur\s*nr\.?|invoice\s*(?:number|no\.?))\s*[:#-]?\s*([A-Z0-9][A-Z0-9._\/-]{2,40})/iu',
      '/\b(?:factuur|invoice)\s*[:#-]?\s*([A-Z0-9][A-Z0-9._\/-]{3,40})\b/iu',
    ];
    foreach ($patterns as $pattern) if (preg_match($pattern, $text, $m) === 1) return trim((string) $m[1]);
    return '';
  }

  /** @return array{ex:float,vat:float,inc:float}|null */
  private function amounts(string $text): ?array {
    $inc = $this->labelAmount($text, ['totaal incl. btw', 'totaal inclusief btw', 'te betalen', 'amount due', 'total incl. vat', 'total including vat']);
    $ex = $this->labelAmount($text, ['totaal excl. btw', 'totaal exclusief btw', 'subtotal', 'total excl. vat', 'total excluding vat']);
    $vat = $this->vatAmount($text);
    if ($inc === NULL) return NULL;
    if ($ex === NULL && $vat !== NULL) $ex = round($inc - $vat, 4);
    if ($vat === NULL && $ex !== NULL) $vat = round($inc - $ex, 4);
    if ($ex === NULL || $vat === NULL || $ex < 0 || $vat < 0 || $inc < 0) return NULL;
    if (abs(($ex + $vat) - $inc) > 0.03) return NULL;
    return ['ex' => $ex, 'vat' => $vat, 'inc' => $inc];
  }

  private function vatAmount(string $text): ?float {
    $amount = '([0-9][0-9. ]*,[0-9]{2}|[0-9][0-9, ]*\.[0-9]{2})';
    $patterns = [
      '/(?:^|\R)\s*(?:btw[- ]bedrag|vat amount)\s*[:€ ]*' . $amount . '/imu',
      '/(?:^|\R)\s*(?:btw|vat)(?:\s+[0-9]+(?:[.,][0-9]+)?\s*%)?\s*[:€ ]+' . $amount . '/imu',
    ];
    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $text, $m) === 1) return $this->money((string) $m[1]);
    }
    return NULL;
  }

  private function labelAmount(string $text, array $labels): ?float {
    foreach ($labels as $label) {
      if (preg_match('/' . preg_quote($label, '/') . '\s*[:€ ]*([0-9][0-9. ]*,[0-9]{2}|[0-9][0-9, ]*\.[0-9]{2})/iu', $text, $m) === 1) return $this->money((string) $m[1]);
    }
    return NULL;
  }

  private function money(string $value): float {
    $value = str_replace(' ', '', trim($value));
    if (str_contains($value, ',') && str_contains($value, '.')) $value = str_replace('.', '', $value);
    $value = str_replace(',', '.', $value);
    return round((float) $value, 4);
  }

  private function invoiceDate(string $text, string $receivedAt): string {
    if (preg_match('/(?:factuurdatum|invoice\s*date)\s*[: ]+([0-3]?\d)[-.\/]([01]?\d)[-.\/](20\d{2})/iu', $text, $m) === 1) return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
    return gmdate('Y-m-d', $this->timestamp($receivedAt));
  }

  private function timestamp(string $value): int {
    $timestamp = strtotime($value);
    return $timestamp === FALSE ? time() : $timestamp;
  }

  private function supplierName(string $email): string {
    $domain = substr(strrchr($email, '@') ?: '', 1);
    return $domain !== '' ? $domain : 'Onbekende leverancier';
  }

  private function containsAddress(string $value, string $needle): bool { return in_array(strtolower($needle), $this->addresses($value), TRUE); }
  private function firstAddress(string $value): string { return $this->addresses($value)[0] ?? ''; }
  private function addresses(string $value): array { preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $m); return array_values(array_unique(array_map('strtolower', $m[0] ?? []))); }
}
