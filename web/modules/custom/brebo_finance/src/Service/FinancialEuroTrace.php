<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use UnexpectedValueException;

/** Builds source-backed euro traces across procurement, invoice and payment. */
final class FinancialEuroTrace {
  public function __construct(private readonly Connection $database) {}

  /** @return array<string, mixed> */
  public function trace(string $entityType, int $entityId): array {
    return match ($entityType) {
      'commitment' => $this->fromCommitment($entityId),
      'purchase_invoice' => $this->fromPurchaseInvoice($entityId),
      'payment_release' => $this->fromPaymentRelease($entityId),
      default => throw new UnexpectedValueException('Unsupported financial trace entity type.'),
    };
  }

  private function fromCommitment(int $id): array {
    $commitment = $this->one('brebo_finance_commitment', $id);
    $lines = $this->many('brebo_finance_commitment_line', 'commitment_id', $id);
    $budgetLineIds = array_values(array_unique(array_filter(array_map(static fn(array $r): int => (int) ($r['budget_line_id'] ?? 0), $lines))));
    $budgetLines = $budgetLineIds ? $this->manyIn('brebo_finance_working_budget_line', 'id', $budgetLineIds) : [];
    $lineIds = array_values(array_map(static fn(array $r): int => (int) $r['id'], $lines));
    $receipts = $lineIds ? $this->manyIn('brebo_finance_performance_receipt', 'commitment_line_id', $lineIds) : [];
    $invoiceLines = $lineIds ? $this->manyIn('brebo_finance_purchase_invoice_line', 'commitment_line_id', $lineIds) : [];
    $invoiceIds = array_values(array_unique(array_filter(array_map(static fn(array $r): int => (int) ($r['invoice_id'] ?? 0), $invoiceLines))));
    $invoices = $invoiceIds ? $this->manyIn('brebo_finance_purchase_invoice', 'id', $invoiceIds) : [];
    $releases = $invoiceIds ? $this->manyIn('brebo_finance_payment_release', 'invoice_id', $invoiceIds) : [];
    return $this->assemble((int) $commitment['project_nid'], 'commitment', $id, $commitment, $lines, $budgetLines, $receipts, $invoiceLines, $invoices, $releases);
  }

  private function fromPurchaseInvoice(int $id): array {
    $invoice = $this->one('brebo_finance_purchase_invoice', $id);
    $invoiceLines = $this->many('brebo_finance_purchase_invoice_line', 'invoice_id', $id);
    $commitmentLineIds = array_values(array_unique(array_filter(array_map(static fn(array $r): int => (int) ($r['commitment_line_id'] ?? 0), $invoiceLines))));
    $commitmentLines = $commitmentLineIds ? $this->manyIn('brebo_finance_commitment_line', 'id', $commitmentLineIds) : [];
    $commitmentIds = array_values(array_unique(array_filter(array_map(static fn(array $r): int => (int) ($r['commitment_id'] ?? 0), $commitmentLines))));
    $commitments = $commitmentIds ? $this->manyIn('brebo_finance_commitment', 'id', $commitmentIds) : [];
    $receipts = $commitmentLineIds ? $this->manyIn('brebo_finance_performance_receipt', 'commitment_line_id', $commitmentLineIds) : [];
    $releases = $this->many('brebo_finance_payment_release', 'invoice_id', $id);
    return [
      'project_nid' => (int) $invoice['project_nid'], 'root' => ['type' => 'purchase_invoice', 'id' => $id, 'record' => $invoice],
      'commitments' => $commitments, 'commitment_lines' => $commitmentLines, 'performance_receipts' => $receipts,
      'invoice_lines' => $invoiceLines, 'invoices' => [$invoice], 'payment_releases' => $releases,
      'audit' => $this->audit((int) $invoice['project_nid'], ['purchase_invoice','purchase_invoice_line','payment_release'], array_merge([$id], array_map(static fn(array $r): int => (int) $r['id'], $invoiceLines), array_map(static fn(array $r): int => (int) $r['id'], $releases))),
      'trace_complete' => $commitmentLines !== [] && $receipts !== [] && $releases !== [],
      'missing_links' => array_values(array_filter([$commitmentLines === [] ? 'commitment' : NULL, $receipts === [] ? 'verified_performance' : NULL, $releases === [] ? 'payment_release' : NULL])),
    ];
  }

  private function fromPaymentRelease(int $id): array {
    $release = $this->one('brebo_finance_payment_release', $id);
    return $this->fromPurchaseInvoice((int) $release['invoice_id']) + ['requested_root' => ['type' => 'payment_release', 'id' => $id]];
  }

  private function assemble(int $projectNid, string $type, int $id, array $record, array $lines, array $budgetLines, array $receipts, array $invoiceLines, array $invoices, array $releases): array {
    $ids = array_merge([$id], array_map(static fn(array $r): int => (int) $r['id'], array_merge($lines, $invoiceLines, $invoices, $releases)));
    return [
      'project_nid' => $projectNid, 'root' => ['type' => $type, 'id' => $id, 'record' => $record],
      'budget_lines' => $budgetLines, 'commitment_lines' => $lines, 'performance_receipts' => $receipts,
      'invoice_lines' => $invoiceLines, 'invoices' => $invoices, 'payment_releases' => $releases,
      'audit' => $this->audit($projectNid, ['commitment','commitment_line','purchase_invoice','purchase_invoice_line','payment_release'], $ids),
      'trace_complete' => $budgetLines !== [] && $receipts !== [] && $invoices !== [] && $releases !== [],
      'missing_links' => array_values(array_filter([$budgetLines === [] ? 'working_budget_line' : NULL, $receipts === [] ? 'verified_performance' : NULL, $invoices === [] ? 'purchase_invoice' : NULL, $releases === [] ? 'payment_release' : NULL])),
    ];
  }

  private function one(string $table, int $id): array {
    if (!$this->database->schema()->tableExists($table)) throw new UnexpectedValueException('Financial source table does not exist.');
    $row = $this->database->select($table, 'x')->fields('x')->condition('id', $id)->execute()->fetchAssoc();
    if ($row === FALSE) throw new UnexpectedValueException('Financial source record does not exist.');
    return $row;
  }

  private function many(string $table, string $field, int $value): array {
    if (!$this->database->schema()->tableExists($table) || !$this->database->schema()->fieldExists($table, $field)) return [];
    return array_map(static fn(object $r): array => (array) $r, $this->database->select($table, 'x')->fields('x')->condition($field, $value)->execute()->fetchAll());
  }

  private function manyIn(string $table, string $field, array $values): array {
    if ($values === [] || !$this->database->schema()->tableExists($table) || !$this->database->schema()->fieldExists($table, $field)) return [];
    return array_map(static fn(object $r): array => (array) $r, $this->database->select($table, 'x')->fields('x')->condition($field, $values, 'IN')->execute()->fetchAll());
  }

  private function audit(int $projectNid, array $types, array $ids): array {
    if (!$this->database->schema()->tableExists('brebo_finance_audit') || $ids === []) return [];
    $q = $this->database->select('brebo_finance_audit', 'a')->fields('a')->condition('project_nid', $projectNid)->condition('entity_type', $types, 'IN')->condition('entity_id', array_values(array_unique($ids)), 'IN')->orderBy('created', 'ASC');
    return array_map(static fn(object $r): array => (array) $r, $q->execute()->fetchAll());
  }
}
