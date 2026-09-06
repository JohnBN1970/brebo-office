<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_data_intake\Contract\IntakeDestinationInterface;
use Drupal\brebo_data_intake\ValueObject\IntakeDestinationResult;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/** Routes classified purchase invoices into the canonical Finance workflow. */
final class PurchaseInvoiceIntakeDestination implements IntakeDestinationInterface {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function supports(string $classification): bool {
    return in_array($classification, ['purchase_invoice', 'supplier_invoice'], TRUE);
  }

  public function route(array $envelope): IntakeDestinationResult {
    if (!$this->database->schema()->tableExists('brebo_finance_purchase_invoice')) {
      return new IntakeDestinationResult(IntakeDestinationResult::UNAVAILABLE, 'finance_unavailable');
    }

    $payload = is_array($envelope['payload'] ?? NULL) ? $envelope['payload'] : [];
    $supplierRef = trim((string) ($payload['supplier_ref'] ?? $envelope['canonical']['supplier_ref'] ?? ''));
    $supplierName = trim((string) ($payload['supplier_name'] ?? ''));
    $invoiceNumber = trim((string) ($payload['invoice_number'] ?? ''));
    $invoiceDate = trim((string) ($payload['invoice_date'] ?? ''));
    $currency = strtoupper(trim((string) ($payload['currency'] ?? 'EUR')));
    $amountExVat = $this->number($payload['amount_ex_vat'] ?? NULL);
    $vatAmount = $this->number($payload['vat_amount'] ?? NULL);
    $amountIncVat = $this->number($payload['amount_inc_vat'] ?? NULL);

    if ($supplierRef === '' || $invoiceNumber === '' || $invoiceDate === '' || $amountExVat === NULL || $vatAmount === NULL || $amountIncVat === NULL) {
      return new IntakeDestinationResult(IntakeDestinationResult::REVIEW_REQUIRED, 'required_purchase_invoice_fields_missing');
    }
    if (abs(($amountExVat + $vatAmount) - $amountIncVat) > 0.03) {
      return new IntakeDestinationResult(IntakeDestinationResult::REVIEW_REQUIRED, 'purchase_invoice_amounts_not_balanced');
    }

    $lines = $this->normalizeLines($payload['lines'] ?? []);
    if ($lines === NULL) {
      return new IntakeDestinationResult(IntakeDestinationResult::REVIEW_REQUIRED, 'purchase_invoice_lines_invalid');
    }
    if ($lines !== []) {
      $lineExVat = array_sum(array_column($lines, 'amount_ex_vat'));
      $lineVat = array_sum(array_column($lines, 'vat_amount'));
      $lineIncVat = array_sum(array_column($lines, 'amount_inc_vat'));
      if (abs($lineExVat - $amountExVat) > 0.03 || abs($lineVat - $vatAmount) > 0.03 || abs($lineIncVat - $amountIncVat) > 0.03) {
        return new IntakeDestinationResult(IntakeDestinationResult::REVIEW_REQUIRED, 'purchase_invoice_line_totals_not_balanced');
      }
    }

    $sourceRecordId = trim((string) ($envelope['source_record_id'] ?? ''));
    $sourceHash = $sourceRecordId !== '' ? hash('sha256', ($envelope['source'] ?? 'unknown') . "\n" . $sourceRecordId) : NULL;
    if ($sourceHash !== NULL) {
      $replayedInvoiceId = $this->database->select('brebo_finance_purchase_invoice', 'i')
        ->fields('i', ['id'])
        ->condition('source_hash', $sourceHash)
        ->range(0, 1)
        ->execute()
        ->fetchField();
      if ($replayedInvoiceId !== FALSE) {
        return new IntakeDestinationResult(IntakeDestinationResult::DUPLICATE, context: ['invoice_id' => (int) $replayedInvoiceId, 'duplicate_reason' => 'source_replay']);
      }
    }

    $existing = $this->database->select('brebo_finance_purchase_invoice', 'i')
      ->fields('i', ['id'])
      ->condition('supplier_ref', $supplierRef)
      ->condition('invoice_number', $invoiceNumber)
      ->execute()
      ->fetchCol();
    if (count($existing) === 1) {
      return new IntakeDestinationResult(IntakeDestinationResult::DUPLICATE, context: ['invoice_id' => (int) $existing[0], 'duplicate_reason' => 'supplier_invoice']);
    }
    if (count($existing) > 1) {
      return new IntakeDestinationResult(IntakeDestinationResult::REVIEW_REQUIRED, 'ambiguous_existing_supplier_invoice');
    }

    $projectNid = $this->validProjectNid((int) ($envelope['canonical']['project_nid'] ?? 0));
    $now = time();
    $transaction = $this->database->startTransaction();

    try {
      $invoiceId = (int) $this->database->insert('brebo_finance_purchase_invoice')->fields([
        'project_nid' => $projectNid,
        'commitment_id' => NULL,
        'moneybird_id' => ($envelope['source'] ?? '') === 'moneybird' ? substr($sourceRecordId, 0, 128) : NULL,
        'supplier_ref' => substr($supplierRef, 0, 255),
        'supplier_name' => substr($supplierName !== '' ? $supplierName : $supplierRef, 0, 255),
        'invoice_number' => substr($invoiceNumber, 0, 128),
        'invoice_date' => $invoiceDate,
        'due_date' => $this->dateOrNull($payload['due_date'] ?? NULL),
        'status' => 'received',
        'match_status' => 'unmatched',
        'amount_ex_vat' => $amountExVat,
        'vat_amount' => $vatAmount,
        'amount_inc_vat' => $amountIncVat,
        'g_account_amount' => $this->number($payload['g_account_amount'] ?? 0) ?? 0.0,
        'regular_account_amount' => $this->number($payload['regular_account_amount'] ?? $amountIncVat) ?? $amountIncVat,
        'currency' => substr($currency !== '' ? $currency : 'EUR', 0, 3),
        'source_hash' => $sourceHash,
        'created' => $now,
        'created_by' => ($envelope['actor_uid'] ?? 0) > 0 ? (int) $envelope['actor_uid'] : NULL,
        'changed' => $now,
        'changed_by' => ($envelope['actor_uid'] ?? 0) > 0 ? (int) $envelope['actor_uid'] : NULL,
      ])->execute();

      if ($lines !== [] && $this->database->schema()->tableExists('brebo_finance_purchase_invoice_line')) {
        foreach ($lines as $line) {
          $this->database->insert('brebo_finance_purchase_invoice_line')->fields($line + [
            'invoice_id' => $invoiceId,
            'commitment_line_id' => NULL,
            'match_status' => 'unmatched',
            'variance_code' => NULL,
            'variance_amount_ex_vat' => 0,
            'review_note' => NULL,
            'created' => $now,
            'created_by' => ($envelope['actor_uid'] ?? 0) > 0 ? (int) $envelope['actor_uid'] : NULL,
            'changed' => $now,
            'changed_by' => ($envelope['actor_uid'] ?? 0) > 0 ? (int) $envelope['actor_uid'] : NULL,
          ])->execute();
        }
      }
    }
    catch (\Exception $exception) {
      $transaction->rollBack();
      $duplicateId = $this->findDuplicate($sourceHash, $supplierRef, $invoiceNumber);
      if ($duplicateId !== NULL) {
        return new IntakeDestinationResult(IntakeDestinationResult::DUPLICATE, context: ['invoice_id' => $duplicateId, 'duplicate_reason' => 'concurrent_replay']);
      }
      throw $exception;
    }
    unset($transaction);

    if ($this->database->schema()->tableExists('brebo_finance_audit')) {
      $this->database->insert('brebo_finance_audit')->fields([
        'project_nid' => $projectNid,
        'entity_type' => 'purchase_invoice',
        'entity_id' => $invoiceId,
        'action' => 'source_neutral_invoice_received',
        'payload' => json_encode([
          'source' => $envelope['source'] ?? NULL,
          'source_record_id' => $sourceRecordId,
          'classification' => $envelope['classification'] ?? NULL,
          'confidence' => $envelope['confidence'] ?? NULL,
          'canonical' => $envelope['canonical'] ?? [],
          'attachments' => $envelope['attachments'] ?? [],
          'line_count' => count($lines),
        ], JSON_THROW_ON_ERROR),
        'reason' => 'Classified source-neutral intake routed into the canonical Finance purchase-invoice workflow.',
        'created' => $now,
        'created_by' => ($envelope['actor_uid'] ?? 0) > 0 ? (int) $envelope['actor_uid'] : NULL,
      ])->execute();
    }

    return new IntakeDestinationResult(IntakeDestinationResult::CREATED, context: ['invoice_id' => $invoiceId, 'project_nid' => $projectNid, 'line_count' => count($lines)]);
  }

  private function normalizeLines(mixed $rawLines): ?array {
    if ($rawLines === NULL || $rawLines === '') {
      return [];
    }
    if (!is_array($rawLines)) {
      return NULL;
    }
    $normalized = [];
    $seen = [];
    foreach ($rawLines as $index => $raw) {
      if (!is_array($raw)) {
        return NULL;
      }
      $lineNumber = (int) ($raw['line_number'] ?? ($index + 1));
      $description = trim((string) ($raw['description'] ?? ''));
      $quantity = $this->number($raw['quantity'] ?? NULL);
      $unitPrice = $this->number($raw['unit_price_ex_vat'] ?? NULL);
      $lineExVat = $this->number($raw['amount_ex_vat'] ?? NULL);
      $vatRate = $this->number($raw['vat_rate'] ?? NULL);
      $lineVat = $this->number($raw['vat_amount'] ?? NULL);
      $lineIncVat = $this->number($raw['amount_inc_vat'] ?? NULL);
      if ($lineNumber <= 0 || isset($seen[$lineNumber]) || $description === '' || $quantity === NULL || $unitPrice === NULL || $lineExVat === NULL || $vatRate === NULL || $lineVat === NULL || $lineIncVat === NULL) {
        return NULL;
      }
      if (abs(($lineExVat + $lineVat) - $lineIncVat) > 0.03) {
        return NULL;
      }
      $seen[$lineNumber] = TRUE;
      $normalized[] = [
        'line_number' => $lineNumber,
        'description' => substr($description, 0, 512),
        'quantity' => $quantity,
        'unit' => ($unit = trim((string) ($raw['unit'] ?? ''))) !== '' ? substr($unit, 0, 32) : NULL,
        'unit_price_ex_vat' => $unitPrice,
        'amount_ex_vat' => $lineExVat,
        'vat_code' => substr(trim((string) ($raw['vat_code'] ?? 'UNKNOWN')) ?: 'UNKNOWN', 0, 32),
        'vat_rate' => $vatRate,
        'vat_amount' => $lineVat,
        'amount_inc_vat' => $lineIncVat,
      ];
    }
    return $normalized;
  }

  private function findDuplicate(?string $sourceHash, string $supplierRef, string $invoiceNumber): ?int {
    if ($sourceHash !== NULL) {
      $id = $this->database->select('brebo_finance_purchase_invoice', 'i')->fields('i', ['id'])->condition('source_hash', $sourceHash)->range(0, 1)->execute()->fetchField();
      if ($id !== FALSE) {
        return (int) $id;
      }
    }
    $id = $this->database->select('brebo_finance_purchase_invoice', 'i')->fields('i', ['id'])->condition('supplier_ref', $supplierRef)->condition('invoice_number', $invoiceNumber)->range(0, 1)->execute()->fetchField();
    return $id !== FALSE ? (int) $id : NULL;
  }

  private function validProjectNid(int $projectNid): int {
    if ($projectNid <= 0) {
      return 0;
    }
    $project = $this->entityTypeManager->getStorage('node')->load($projectNid);
    return $project !== NULL && $project->bundle() === 'brebo_project' ? $projectNid : 0;
  }

  private function number(mixed $value): ?float {
    if ($value === NULL || $value === '') {
      return NULL;
    }
    if (is_string($value)) {
      $value = str_replace(' ', '', trim($value));
      if (str_contains($value, ',') && str_contains($value, '.')) {
        $value = str_replace('.', '', $value);
      }
      $value = str_replace(',', '.', $value);
    }
    return is_numeric($value) ? round((float) $value, 4) : NULL;
  }

  private function dateOrNull(mixed $value): ?string {
    $value = trim((string) $value);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : NULL;
  }

}
