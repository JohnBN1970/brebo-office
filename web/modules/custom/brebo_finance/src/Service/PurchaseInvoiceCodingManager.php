<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\PurchaseInvoiceCodingRepositoryInterface;
use UnexpectedValueException;

/** Controlled coding of purchase invoices before three-way matching. */
final class PurchaseInvoiceCodingManager {

  public function __construct(private readonly PurchaseInvoiceCodingRepositoryInterface $repository) {}

  public function assignProject(int $invoiceId, int $projectNid, int $userId): void {
    if ($projectNid <= 0) {
      throw new UnexpectedValueException('A project is required.');
    }
    $invoice = $this->invoice($invoiceId);
    $now = time();
    $this->repository->updateInvoice($invoiceId, [
      'project_nid' => $projectNid,
      'commitment_id' => NULL,
      'match_status' => 'unmatched',
      'changed' => $now,
      'changed_by' => $userId,
    ]);
    $this->repository->updateInvoiceLines($invoiceId, [
      'commitment_line_id' => NULL,
      'match_status' => 'unmatched',
      'variance_code' => NULL,
      'variance_amount_ex_vat' => 0,
      'changed' => $now,
      'changed_by' => $userId,
    ]);
    $this->audit($projectNid, 'purchase_invoice', $invoiceId, 'project_coded', ['previous_project_nid' => (int) $invoice['project_nid']], $userId);
  }

  public function upsertLine(int $invoiceId, int $lineNumber, array $values, int $userId): int {
    $invoice = $this->invoice($invoiceId);
    if ($lineNumber <= 0) {
      throw new UnexpectedValueException('Invoice line number must be positive.');
    }
    $description = trim((string) ($values['description'] ?? ''));
    if ($description === '') {
      throw new UnexpectedValueException('Invoice line description is required.');
    }
    foreach (['quantity', 'unit_price_ex_vat', 'amount_ex_vat', 'vat_rate', 'vat_amount', 'amount_inc_vat'] as $field) {
      if (!isset($values[$field]) || !is_numeric($values[$field])) {
        throw new UnexpectedValueException('Invoice line contains an invalid numeric value.');
      }
    }
    $now = time();
    $fields = [
      'description' => substr($description, 0, 512),
      'quantity' => (string) $values['quantity'],
      'unit' => substr(trim((string) ($values['unit'] ?? '')), 0, 32) ?: NULL,
      'unit_price_ex_vat' => (string) $values['unit_price_ex_vat'],
      'amount_ex_vat' => (string) $values['amount_ex_vat'],
      'vat_code' => substr(trim((string) ($values['vat_code'] ?? '')), 0, 32) ?: 'NL_21',
      'vat_rate' => (string) $values['vat_rate'],
      'vat_amount' => (string) $values['vat_amount'],
      'amount_inc_vat' => (string) $values['amount_inc_vat'],
      'match_status' => 'unmatched',
      'variance_code' => NULL,
      'variance_amount_ex_vat' => 0,
      'review_note' => isset($values['review_note']) ? trim((string) $values['review_note']) : NULL,
      'changed' => $now,
      'changed_by' => $userId,
    ];
    $existing = $this->repository->invoiceLineId($invoiceId, $lineNumber);
    if ($existing) {
      $this->repository->updateLine((int) $existing, $fields);
      $lineId = (int) $existing;
    }
    else {
      $fields += ['invoice_id' => $invoiceId, 'line_number' => $lineNumber, 'commitment_line_id' => NULL, 'created' => $now, 'created_by' => $userId];
      $lineId = $this->repository->createLine($fields);
    }
    $this->markInvoiceUnmatched($invoiceId, $now, $userId);
    $this->audit((int) $invoice['project_nid'], 'purchase_invoice_line', $lineId, 'invoice_line_coded', ['invoice_id' => $invoiceId, 'line_number' => $lineNumber], $userId);
    return $lineId;
  }

  public function linkCommitmentLine(int $invoiceId, int $invoiceLineId, int $commitmentLineId, int $userId): void {
    $invoice = $this->invoice($invoiceId);
    $projectNid = (int) $invoice['project_nid'];
    if ($projectNid <= 0) {
      throw new UnexpectedValueException('Code the invoice to a project before linking an order line.');
    }
    if (!$this->repository->invoiceOwnsLine($invoiceId, $invoiceLineId)) {
      throw new UnexpectedValueException('Invoice line does not belong to this invoice.');
    }
    $order = $this->repository->commitmentLineForProject($commitmentLineId, $projectNid);
    if ($order === NULL) {
      throw new UnexpectedValueException('Commitment line does not belong to the coded project.');
    }
    $now = time();
    $this->repository->updateLine($invoiceLineId, ['commitment_line_id' => $commitmentLineId, 'match_status' => 'unmatched', 'variance_code' => NULL, 'variance_amount_ex_vat' => 0, 'changed' => $now, 'changed_by' => $userId]);
    $this->repository->updateInvoice($invoiceId, ['commitment_id' => (int) $order['commitment_id'], 'match_status' => 'unmatched', 'changed' => $now, 'changed_by' => $userId]);
    $this->audit($projectNid, 'purchase_invoice_line', $invoiceLineId, 'commitment_line_coded', ['commitment_line_id' => $commitmentLineId], $userId);
  }

  private function invoice(int $invoiceId): array {
    $invoice = $this->repository->invoice($invoiceId);
    if ($invoice === NULL) {
      throw new UnexpectedValueException('Purchase invoice does not exist.');
    }
    return $invoice;
  }

  private function markInvoiceUnmatched(int $invoiceId, int $now, int $userId): void {
    $this->repository->updateInvoice($invoiceId, ['match_status' => 'unmatched', 'changed' => $now, 'changed_by' => $userId]);
  }

  private function audit(int $projectNid, string $entityType, int $entityId, string $action, array $payload, int $userId): void {
    $this->repository->appendAudit(['project_nid' => $projectNid, 'entity_type' => $entityType, 'entity_id' => $entityId, 'action' => $action, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'reason' => 'Purchase invoice coding workbench.', 'created' => time(), 'created_by' => $userId]);
  }
}
