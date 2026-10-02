<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface PurchaseInvoiceControlRepositoryInterface {
  public function available(): bool;
  /** @return array<string,mixed>|null */
  public function invoice(int $invoiceId): ?array;
  /** @return list<array<string,mixed>> */
  public function lines(int $invoiceId): array;
  /** @return array<string,mixed>|null */
  public function latestPaymentRelease(int $invoiceId): ?array;
  /** @return array<string,mixed>|null */
  public function latestGAccountInstruction(int $invoiceId): ?array;
}
