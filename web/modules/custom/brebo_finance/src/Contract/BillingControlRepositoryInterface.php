<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Persistence boundary for billing instalments and the sales-invoice mirror. */
interface BillingControlRepositoryInterface {

  /** @param callable():mixed $callback */
  public function transactional(callable $callback): mixed;

  public function approvedContractExists(int $contractId, int $projectNid): bool;

  /** @param array<string,mixed> $fields
   *  @param list<array<string,string>> $lines
   */
  public function createInstalment(array $fields, array $lines, int $projectNid, int $actorUid, int $now): int;

  /** @return array<string,mixed>|null */
  public function instalment(int $instalmentId): ?array;

  /** @param array<string,mixed> $fields */
  public function updateInstalment(int $instalmentId, array $fields): void;

  public function instalmentStatusForProject(int $instalmentId, int $projectNid): ?string;

  /** @return array{id:int,source_hash:string,recorded_at:int}|null */
  public function salesInvoiceByMoneybirdId(string $moneybirdId): ?array;

  /** @param array<string,mixed> $fields
   *  @param list<array<string,string>> $lines
   */
  public function saveSalesInvoice(?int $existingId, array $fields, array $lines, int $projectNid, ?int $instalmentId, string $sourceStatus, int $actorUid, int $now): int;

}
