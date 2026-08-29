<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use Drupal\brebo_mail_intake\Service\PurchaseInvoiceMailRouterInterface;

/** Conservatively routes real invoice mail into received purchase invoices. */
final class MailPurchaseInvoiceRouter implements PurchaseInvoiceMailRouterInterface {

  private const MAILBOX = 'facturen@brebobv.nl';

  public function __construct(private readonly Connection $database) {}

  public function route(array $mail, array $attachmentEvidence, int $communicationNid): array {
    if (!$this->database->schema()->tableExists('brebo_finance_purchase_invoice')) {
      return ['state' => 'finance_unavailable'];
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

    // Invoice numbers are only unique within one supplier administration.
    // Compare the same key as the finance table's supplier_invoice unique key;
    // otherwise two unrelated suppliers using invoice "2026-001" would collide.
    $existing = $this->database->select('brebo_finance_purchase_invoice', 'i')
      ->fields('i', ['id', 'supplier_ref', 'invoice_number'])
      ->condition('supplier_ref', $from)
      ->condition('invoice_number', $invoiceNumber)
      ->execute()
      ->fetchAllAssoc('id');
    if (count($existing) === 1) {
      return ['state' => 'duplicate', 'invoice_id' => (int) array_key_first($existing)];
    }
    if (count($existing) > 1) {
      return ['state' => 'communication_only', 'reason' => 'ambiguous_existing_supplier_invoice'];
    }

    $date = $this->invoiceDate($text, (string) ($mail['received_at'] ?? ''));
    $supplier = $this->supplierName($from);
    $sourceHash = trim((string) ($mail['source_hash'] ?? ''));
    if ($sourceHash === '') {
      $sourceHash = hash('sha256', (string) ($mail['source_id'] ?? '') . "\n" . $invoiceNumber . "\n" . $from);
    }
    $now = time();
    $invoiceId = (int) $this->database->insert('brebo_finance_purchase_invoice')->fields([
      'project_nid' => 0,
      'commitment_id' => NULL,
      'moneybird_id' => NULL,
      'supplier_ref' => substr($from, 0, 255),
      'supplier_name' => substr($supplier, 0, 255),
      'invoice_number' => substr($invoiceNumber, 0, 128),
      'invoice_date' => $date,
      'due_date' => NULL,
      'status' => 'received',
      'match_status' => 'unmatched',
      'amount_ex_vat' => $amounts['ex'],
      'vat_amount' => $amounts['vat'],
      'amount_inc_vat' => $amounts['inc'],
      'g_account_amount' => 0,
      'regular_account_amount' => $amounts['inc'],
      'currency' => 'EUR',
      'source_hash' => substr($sourceHash, 0, 64),
      'created' => $now,
      'created_by' => NULL,
      'changed' => $now,
      'changed_by' => NULL,
    ])->execute();

    if ($this->database->schema()->tableExists('brebo_finance_audit')) {
      $this->database->insert('brebo_finance_audit')->fields([
        'project_nid' => 0,
        'entity_type' => 'purchase_invoice',
        'entity_id' => $invoiceId,
        'action' => 'mail_invoice_received',
        'payload' => json_encode(['communication_nid' => $communicationNid, 'mailbox' => self::MAILBOX], JSON_THROW_ON_ERROR),
        'reason' => 'Deterministically identified supplier invoice received through the dedicated invoice mailbox.',
        'created' => $now,
        'created_by' => NULL,
      ])->execute();
    }

    return ['state' => 'created', 'invoice_id' => $invoiceId];
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
    $ts = strtotime($receivedAt);
    return gmdate('Y-m-d', $ts === FALSE ? time() : $ts);
  }

  private function supplierName(string $email): string {
    $domain = substr(strrchr($email, '@') ?: '', 1);
    return $domain !== '' ? $domain : 'Onbekende leverancier';
  }

  private function containsAddress(string $value, string $needle): bool { return in_array(strtolower($needle), $this->addresses($value), TRUE); }
  private function firstAddress(string $value): string { return $this->addresses($value)[0] ?? ''; }
  private function addresses(string $value): array { preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $m); return array_values(array_unique(array_map('strtolower', $m[0] ?? []))); }
}
