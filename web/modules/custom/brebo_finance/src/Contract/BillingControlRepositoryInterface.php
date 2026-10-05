<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Persistence boundary for billing instalments and sales-invoice mirror writes. */
interface BillingControlRepositoryInterface {

  /** @return array{id:int,project_nid:int,status:string}|null */
  public function approvedContract(int $contractId): ?array;

  /** @param array<string,mixed> $fields
   *  @param list<array<string,string>> $lines
   */
  public function createInstalment(array $fields, array $lines, int $projectNid, int $actorUid, int $now): int;

  /** @return array<string,mixed>|null */
  public function instalment(int $instalmentId): ?array;

  /** @return array<string,mixed>|null */
  public function instalmentForProject(int $instalmentId, int $projectNid): ?array;

  /** @param array<string,mixed> $fields */
  public function updateInstalment(int $instalmentId, array $fields): void;

  /** @return array{id:int,source_hash:string,recorded_at:int}|null */
  public function salesInvoiceByMoneybirdId(string $moneybirdId): ?array;

  /** @param array<string,mixed> $fields
   *  @param list<array<string,string>> $lines
   */
  public function persistSalesInvoice(
    ?int $existingId,
    array $fields,
    array $lines,
    int $projectNid,
    ?int $instalmentId,
    string $status,
    int $actorUid,
    int $now,
  ): int;

}
