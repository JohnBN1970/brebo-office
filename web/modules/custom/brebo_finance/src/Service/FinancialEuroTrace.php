<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\FinancialEuroTraceRepositoryInterface;
use UnexpectedValueException;

/** Builds source-backed euro traces across procurement, invoice and payment. */
final class FinancialEuroTrace {
  public function __construct(private readonly FinancialEuroTraceRepositoryInterface $repository) {}

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
    $commitment = $this->repository->one('brebo_finance_commitment', $id);
    $lines = $this->repository->many('brebo_finance_commitment_line', 'commitment_id', $id);
    $budgetLineIds = array_values(array_unique(array_filter(array_map(static fn(array $r): int => (int) ($r['budget_line_id'] ?? 0), $lines))));
    $budgetLines = $budgetLineIds ? $this->repository->manyIn('brebo_finance_working_budget_line', 'id', $budgetLineIds) : [];
    $lineIds = array_values(array_map(static fn(array $r): int => (int) $r['id'], $lines));
    $receipts = $lineIds ? $this->repository->manyIn('brebo_finance_performance_receipt', 'commitment_line_id', $lineIds) : [];
    $invoiceLines = $lineIds ? $this->repository->manyIn('brebo_finance_purchase_invoice_line', 'commitment_line_id', $lineIds) : [];
    $invoiceIds = array_values(array_unique(array_filter(array_map(static fn(array $r): int => (int) ($r['invoice_id'] ?? 0), $invoiceLines))));
    $invoices = $invoiceIds ? $this->repository->manyIn('brebo_finance_purchase_invoice', 'id', $invoiceIds) : [];
    $releases = $invoiceIds ? $this->repository->manyIn('brebo_finance_payment_release', 'invoice_id', $invoiceIds) : [];
    return $this->assemble((int) $commitment['project_nid'], 'commitment', $id, $commitment, $lines, $budgetLines, $receipts, $invoiceLines, $invoices, $releases);
  }

  private function fromPurchaseInvoice(int $id): array {
    $invoice = $this->repository->one('brebo_finance_purchase_invoice', $id);
    $invoiceLines = $this->repository->many('brebo_finance_purchase_invoice_line', 'invoice_id', $id);
    $commitmentLineIds = array_values(array_unique(array_filter(array_map(static fn(array $r): int => (int) ($r['commitment_line_id'] ?? 0), $invoiceLines))));
    $commitmentLines = $commitmentLineIds ? $this->repository->manyIn('brebo_finance_commitment_line', 'id', $commitmentLineIds) : [];
    $commitmentIds = array_values(array_unique(array_filter(array_map(static fn(array $r): int => (int) ($r['commitment_id'] ?? 0), $commitmentLines))));
    $commitments = $commitmentIds ? $this->repository->manyIn('brebo_finance_commitment', 'id', $commitmentIds) : [];
    $receipts = $commitmentLineIds ? $this->repository->manyIn('brebo_finance_performance_receipt', 'commitment_line_id', $commitmentLineIds) : [];
    $releases = $this->repository->many('brebo_finance_payment_release', 'invoice_id', $id);
    return [
      'project_nid' => (int) $invoice['project_nid'], 'root' => ['type' => 'purchase_invoice', 'id' => $id, 'record' => $invoice],
      'commitments' => $commitments, 'commitment_lines' => $commitmentLines, 'performance_receipts' => $receipts,
      'invoice_lines' => $invoiceLines, 'invoices' => [$invoice], 'payment_releases' => $releases,
      'audit' => $this->repository->audit((int) $invoice['project_nid'], ['purchase_invoice','purchase_invoice_line','payment_release'], array_merge([$id], array_map(static fn(array $r): int => (int) $r['id'], $invoiceLines), array_map(static fn(array $r): int => (int) $r['id'], $releases))),
      'trace_complete' => $commitmentLines !== [] && $receipts !== [] && $releases !== [],
      'missing_links' => array_values(array_filter([$commitmentLines === [] ? 'commitment' : NULL, $receipts === [] ? 'verified_performance' : NULL, $releases === [] ? 'payment_release' : NULL])),
    ];
  }

  private function fromPaymentRelease(int $id): array {
    $release = $this->repository->one('brebo_finance_payment_release', $id);
    return $this->fromPurchaseInvoice((int) $release['invoice_id']) + ['requested_root' => ['type' => 'payment_release', 'id' => $id]];
  }

  private function assemble(int $projectNid, string $type, int $id, array $record, array $lines, array $budgetLines, array $receipts, array $invoiceLines, array $invoices, array $releases): array {
    $ids = array_merge([$id], array_map(static fn(array $r): int => (int) $r['id'], array_merge($lines, $invoiceLines, $invoices, $releases)));
    return [
      'project_nid' => $projectNid, 'root' => ['type' => $type, 'id' => $id, 'record' => $record],
      'budget_lines' => $budgetLines, 'commitment_lines' => $lines, 'performance_receipts' => $receipts,
      'invoice_lines' => $invoiceLines, 'invoices' => $invoices, 'payment_releases' => $releases,
      'audit' => $this->repository->audit($projectNid, ['commitment','commitment_line','purchase_invoice','purchase_invoice_line','payment_release'], $ids),
      'trace_complete' => $budgetLines !== [] && $receipts !== [] && $invoices !== [] && $releases !== [],
      'missing_links' => array_values(array_filter([$budgetLines === [] ? 'working_budget_line' : NULL, $receipts === [] ? 'verified_performance' : NULL, $invoices === [] ? 'purchase_invoice' : NULL, $releases === [] ? 'payment_release' : NULL])),
    ];
  }

}
