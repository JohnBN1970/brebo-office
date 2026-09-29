<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface PaymentReleaseRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function invoice(int $invoiceId): ?array;

  /** @return array<string,mixed>|null */
  public function release(int $releaseId): ?array;

  /** @return array<string,mixed>|null */
  public function approvedGAccountInstruction(int $invoiceId): ?array;

  /** @param array<string,mixed> $values */
  public function insertRelease(array $values): int;

  /** @param array<string,mixed> $values */
  public function updateRelease(int $releaseId, array $values): void;

  /** @param array<string,mixed> $values */
  public function updateInvoice(int $invoiceId, array $values): void;

  /** @param array<string,mixed> $values */
  public function insertAudit(array $values): void;

}
