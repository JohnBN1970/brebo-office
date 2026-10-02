<?php
declare(strict_types=1);
namespace Drupal\brebo_finance\Contract;
interface SupplierPerformanceRepositoryInterface {
  public function snapshotExists(int $projectNid,string $supplierRef,string $date,string $policyVersion):bool;
  /** @return list<array<string,mixed>> */ public function orders(int $projectNid,string $supplierRef):array;
  /** @param list<int|string> $commitmentIds @return list<array<string,mixed>> */ public function receipts(int $projectNid,array $commitmentIds):array;
  /** @return list<array<string,mixed>> */ public function invoices(int $projectNid,string $supplierRef):array;
  /** @param list<int> $invoiceIds */ public function invoiceVariance(array $invoiceIds):string;
  public function failureCost(int $projectNid,string $supplierRef):string;
  /** @param array<string,mixed> $snapshot @param array<string,mixed> $audit */ public function createSnapshot(array $snapshot,array $audit):int;
}
