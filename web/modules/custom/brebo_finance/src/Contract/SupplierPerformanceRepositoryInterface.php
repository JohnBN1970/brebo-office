<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Persistence boundary for immutable supplier performance scoring. */
interface SupplierPerformanceRepositoryInterface {

  public function snapshotExists(int $projectNid, string $supplierRef, string $snapshotDate, string $policyVersion): bool;

  /** @return list<array<string,mixed>> */
  public function orders(int $projectNid, string $supplierRef): array;

  /** @param list<int|string> $commitmentIds
   *  @return list<array<string,mixed>>
   */
  public function receipts(int $projectNid, array $commitmentIds): array;

  /** @return list<array<string,mixed>> */
  public function invoices(int $projectNid, string $supplierRef): array;

  /** @param list<int> $invoiceIds */
  public function invoiceVariance(array $invoiceIds): string;

  public function failureCost(int $projectNid, string $supplierRef): string;

  /** @param array<string,mixed> $fields */
  public function createSnapshot(array $fields): int;

  /** @param array<string,mixed> $fields */
  public function appendAudit(array $fields): void;

}
