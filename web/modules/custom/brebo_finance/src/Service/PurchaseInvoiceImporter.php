<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use RuntimeException;
use UnexpectedValueException;

/** Imports Moneybird purchase invoices into the operational Finance mirror. */
final class PurchaseInvoiceImporter {

  public function __construct(
    private readonly Connection $database,
    private readonly PurchaseInvoiceIntegrationClient $client,
    private readonly ?VatCalculator $decimal = NULL,
  ) {}

  /** @return array{received:int,inserted:int,updated:int,unchanged:int} */
  public function sync(): array {
    $received = $this->client->fetchAll();
    $result = ['received' => count($received), 'inserted' => 0, 'updated' => 0, 'unchanged' => 0];
    $now = time();

    foreach ($received as $source) {
      $moneybirdId = trim((string) ($source['id'] ?? ''));
      if ($moneybirdId === '') {
        continue;
      }

      $amountEx = $this->number($source['total_price_excl_tax'] ?? 0);
      $amountInc = $this->number($source['total_price_incl_tax'] ?? $amountEx);
      $fields = [
        'project_nid' => 0,
        'commitment_id' => NULL,
        'moneybird_id' => $moneybirdId,
        'supplier_ref' => $this->nullable($source['contact_id'] ?? NULL),
        'supplier_name' => $this->supplierName($source),
        'invoice_number' => $this->invoiceNumber($source, $moneybirdId),
        'invoice_date' => $this->date($source['date'] ?? NULL) ?? date('Y-m-d'),
        'due_date' => $this->date($source['due_date'] ?? NULL),
        'status' => $this->status((string) ($source['state'] ?? 'received')),
        'match_status' => 'unmatched',
        'amount_ex_vat' => $amountEx,
        'vat_amount' => round($amountInc - $amountEx, 4),
        'amount_inc_vat' => $amountInc,
        'g_account_amount' => 0,
        'regular_account_amount' => $amountInc,
        'currency' => substr((string) ($source['currency'] ?? 'EUR'), 0, 3),
        'source_hash' => hash('sha256', json_encode($source, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)),
        'changed' => $now,
      ];

      $existing = $this->database->select('brebo_finance_purchase_invoice', 'i')
        ->fields('i', ['id', 'source_hash', 'moneybird_id'])
        ->condition('moneybird_id', $moneybirdId)
        ->execute()
        ->fetchAssoc();

      if (!$existing) {
        // The table itself defines supplier_ref + invoice_number as unique.
        // Treat that natural key as authoritative too, regardless of whether a
        // previous import already populated another/legacy Moneybird id.
        $existing = $this->database->select('brebo_finance_purchase_invoice', 'i')
          ->fields('i', ['id', 'source_hash', 'moneybird_id'])
          ->condition('supplier_ref', $fields['supplier_ref'])
          ->condition('invoice_number', $fields['invoice_number'])
          ->execute()
          ->fetchAssoc();
      }

      if (!$existing) {
        $fields['created'] = $now;
        $this->database->insert('brebo_finance_purchase_invoice')->fields($fields)->execute();
        $result['inserted']++;
        continue;
      }

      if ((string) ($existing['source_hash'] ?? '') === $fields['source_hash']
        && trim((string) ($existing['moneybird_id'] ?? '')) === $moneybirdId) {
        $result['unchanged']++;
        continue;
      }

      // Preserve BREBO-owned project/order matching when refreshing Moneybird data.
      unset($fields['project_nid'], $fields['commitment_id'], $fields['match_status']);
      $this->database->update('brebo_finance_purchase_invoice')
        ->fields($fields)
        ->condition('id', (int) $existing['id'])
        ->execute();
      $result['updated']++;
    }

    return $result;
  }

  /**
   * Creates an auditable recipient snapshot from the current Moneybird source.
   *
   * The returned hash deliberately excludes the verification timestamp so the
   * same recipient data remains hash-stable across repeated checks. A later
   * IBAN/name/BIC/contact change therefore produces a different hash and can
   * invalidate a prepared payment run before final release.
   *
   * @return array<string, mixed>
   */
  public function paymentRecipientSnapshot(int $invoiceId, int $actorUid = 0): array {
    $invoice = $this->database->select('brebo_finance_purchase_invoice', 'i')
      ->fields('i', ['id', 'project_nid', 'moneybird_id', 'supplier_ref', 'supplier_name', 'invoice_number'])
      ->condition('id', $invoiceId)
      ->execute()
      ->fetchAssoc();
    if ($invoice === FALSE) {
      throw new UnexpectedValueException('Purchase invoice does not exist.');
    }

    $moneybirdId = trim((string) ($invoice['moneybird_id'] ?? ''));
    if ($moneybirdId === '') {
      throw new RuntimeException('Purchase invoice has no Moneybird source id; recipient verification is blocked.');
    }

    $source = NULL;
    foreach ($this->client->fetchAll() as $candidate) {
      if (trim((string) ($candidate['id'] ?? '')) === $moneybirdId) {
        $source = $candidate;
        break;
      }
    }
    if (!is_array($source)) {
      throw new RuntimeException('Current Moneybird purchase invoice could not be reloaded for recipient verification.');
    }

    $contactId = trim((string) ($source['contact_id'] ?? ''));
    $expectedContactId = trim((string) ($invoice['supplier_ref'] ?? ''));
    if ($expectedContactId !== '' && $contactId !== '' && !hash_equals($expectedContactId, $contactId)) {
      throw new RuntimeException('Moneybird supplier contact changed since the invoice was mirrored; payment is blocked pending review.');
    }

    $contact = $source['supplier_contact'] ?? NULL;
    if (!is_array($contact)) {
      throw new RuntimeException('Moneybird supplier contact has no usable payment data.');
    }

    $iban = $this->normaliseIban((string) ($contact['sepa_iban'] ?? ''));
    if ($iban === '' || !$this->validIban($iban)) {
      throw new RuntimeException('Moneybird supplier contact has no valid SEPA IBAN.');
    }

    $accountName = trim((string) ($contact['sepa_iban_account_name'] ?? ''));
    if ($accountName === '') {
      $accountName = trim((string) ($source['supplier_name'] ?? $invoice['supplier_name'] ?? ''));
    }
    if ($accountName === '') {
      throw new RuntimeException('Moneybird supplier contact has no verifiable account holder name.');
    }

    $canonical = [
      'invoice_id' => (int) $invoice['id'],
      'moneybird_id' => $moneybirdId,
      'contact_id' => $contactId,
      'supplier_name' => trim((string) ($source['supplier_name'] ?? $invoice['supplier_name'] ?? '')),
      'invoice_number' => trim((string) ($invoice['invoice_number'] ?? '')),
      'iban' => $iban,
      'account_name' => $accountName,
      'bic' => strtoupper(trim((string) ($contact['sepa_bic'] ?? ''))),
      'sepa_active' => ($contact['sepa_active'] ?? FALSE) === TRUE,
      'moneybird_version' => $source['version'] ?? NULL,
    ];
    $hash = hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
    $snapshot = $canonical + [
      'recipient_hash' => $hash,
      'verified_at' => time(),
    ];

    if ($this->database->schema()->tableExists('brebo_finance_audit')) {
      $this->database->insert('brebo_finance_audit')->fields([
        'project_nid' => (int) ($invoice['project_nid'] ?? 0),
        'entity_type' => 'purchase_invoice',
        'entity_id' => (int) $invoice['id'],
        'action' => 'payment_recipient_verified',
        'before_hash' => NULL,
        'after_hash' => $hash,
        'payload' => json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        'reason' => 'Current Moneybird supplier recipient snapshot captured before controlled payment preparation.',
        'created' => time(),
        'created_by' => $actorUid ?: NULL,
      ])->execute();
    }

    return $snapshot;
  }

  /** Returns TRUE only while the current Moneybird recipient equals a sealed snapshot. */
  public function paymentRecipientUnchanged(int $invoiceId, string $expectedHash, int $actorUid = 0): bool {
    $expectedHash = strtolower(trim($expectedHash));
    if (!preg_match('/^[a-f0-9]{64}$/', $expectedHash)) {
      throw new UnexpectedValueException('Recipient hash must be a SHA-256 value.');
    }
    $current = $this->paymentRecipientSnapshot($invoiceId, $actorUid);
    return hash_equals($expectedHash, (string) $current['recipient_hash']);
  }

  private function supplierName(array $source): string {
    $name = trim((string) ($source['supplier_name'] ?? ''));
    return $name !== '' ? substr($name, 0, 255) : 'Onbekende leverancier';
  }

  private function invoiceNumber(array $source, string $moneybirdId): string {
    $reference = trim((string) ($source['reference'] ?? ''));
    return substr($reference !== '' ? $reference : 'MB-' . $moneybirdId, 0, 128);
  }

  private function status(string $state): string {
    return match ($state) {
      'paid' => 'paid',
      'late' => 'late',
      'open', 'pending_payment' => 'open',
      'new', 'saved' => 'received',
      default => 'received',
    };
  }

  private function number(mixed $value): float {
    return is_numeric($value) ? (float) $value : 0.0;
  }

  private function nullable(mixed $value): ?string {
    $value = trim((string) ($value ?? ''));
    return $value === '' ? NULL : substr($value, 0, 255);
  }

  private function date(mixed $value): ?string {
    $value = trim((string) ($value ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : NULL;
  }

  private function normaliseIban(string $iban): string {
    return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $iban));
  }

  private function validIban(string $iban): bool {
    if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)) {
      return FALSE;
    }
    $rearranged = substr($iban, 4) . substr($iban, 0, 4);
    $numeric = '';
    foreach (str_split($rearranged) as $character) {
      $numeric .= ctype_alpha($character) ? (string) (ord($character) - 55) : $character;
    }
    $remainder = 0;
    foreach (str_split($numeric) as $digit) {
      $remainder = (($remainder * 10) + (int) $digit) % 97;
    }
    return $remainder === 1;
  }

}
