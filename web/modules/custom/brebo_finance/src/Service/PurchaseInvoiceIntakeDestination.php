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

    $existing = $this->database->select('brebo_finance_purchase_invoice', 'i')
      ->fields('i', ['id'])
      ->condition('supplier_ref', $supplierRef)
      ->condition('invoice_number', $invoiceNumber)
      ->execute()
      ->fetchCol();
    if (count($existing) === 1) {
      return new IntakeDestinationResult(IntakeDestinationResult::DUPLICATE, context: ['invoice_id' => (int) $existing[0]]);
    }
    if (count($existing) > 1) {
      return new IntakeDestinationResult(IntakeDestinationResult::REVIEW_REQUIRED, 'ambiguous_existing_supplier_invoice');
    }

    $projectNid = $this->validProjectNid((int) ($envelope['canonical']['project_nid'] ?? 0));
    $sourceRecordId = trim((string) ($envelope['source_record_id'] ?? ''));
    $sourceHash = hash('sha256', ($envelope['source'] ?? 'unknown') . "\n" . $sourceRecordId);
    $now = time();

    $invoiceId = (int) $this->database->insert('brebo_finance_purchase_invoice')->fields([
      'project_nid' => $projectNid,
      'commitment_id' => NULL,
      'moneybird_id' => ($envelope['source'] ?? '') === 'moneybird' ? substr($sourceRecordId, 0, 255) : NULL,
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
      'source_hash' => substr($sourceHash, 0, 64),
      'created' => $now,
      'created_by' => ($envelope['actor_uid'] ?? 0) > 0 ? (int) $envelope['actor_uid'] : NULL,
      'changed' => $now,
      'changed_by' => ($envelope['actor_uid'] ?? 0) > 0 ? (int) $envelope['actor_uid'] : NULL,
    ])->execute();

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
        ], JSON_THROW_ON_ERROR),
        'reason' => 'Classified source-neutral intake routed into the canonical Finance purchase-invoice workflow.',
        'created' => $now,
        'created_by' => ($envelope['actor_uid'] ?? 0) > 0 ? (int) $envelope['actor_uid'] : NULL,
      ])->execute();
    }

    return new IntakeDestinationResult(IntakeDestinationResult::CREATED, context: ['invoice_id' => $invoiceId, 'project_nid' => $projectNid]);
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
