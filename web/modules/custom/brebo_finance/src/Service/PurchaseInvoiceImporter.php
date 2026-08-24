<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;

/** Imports Moneybird purchase invoices into the operational Finance mirror. */
final class PurchaseInvoiceImporter {

  public function __construct(
    private readonly Connection $database,
    private readonly PurchaseInvoiceIntegrationClient $client,
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
        ->fields('i', ['id', 'source_hash'])
        ->condition('moneybird_id', $moneybirdId)
        ->execute()
        ->fetchAssoc();

      if (!$existing) {
        // Older/local imports may already contain the same supplier invoice but
        // not yet carry its Moneybird id. Adopt that record instead of trying
        // to create a duplicate. This mirrors the table's supplier_invoice
        // uniqueness rule and preserves BREBO-owned matching data.
        $natural = $this->database->select('brebo_finance_purchase_invoice', 'i')
          ->fields('i', ['id', 'source_hash', 'moneybird_id'])
          ->condition('supplier_ref', $fields['supplier_ref'])
          ->condition('invoice_number', $fields['invoice_number'])
          ->execute()
          ->fetchAssoc();

        if ($natural && trim((string) ($natural['moneybird_id'] ?? '')) === '') {
          $existing = $natural;
        }
      }

      if (!$existing) {
        $fields['created'] = $now;
        $this->database->insert('brebo_finance_purchase_invoice')->fields($fields)->execute();
        $result['inserted']++;
        continue;
      }

      if ((string) ($existing['source_hash'] ?? '') === $fields['source_hash']
        && trim((string) ($existing['moneybird_id'] ?? $moneybirdId)) === $moneybirdId) {
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

}
