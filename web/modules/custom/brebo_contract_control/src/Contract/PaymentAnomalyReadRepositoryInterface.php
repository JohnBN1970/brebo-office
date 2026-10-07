<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Contract;

/** Read boundary for payment anomaly intelligence. */
interface PaymentAnomalyReadRepositoryInterface {

  public function hasSupplierInvoices(): bool;

  /** @return array<int, array<string, mixed>> */
  public function findThresholdPatterns(int $since): array;

  /** @return array<int, array<string, mixed>> */
  public function findRepeatExceptionPatterns(int $since): array;

  /** @return array<int, array<string, mixed>> */
  public function findDecisionPairPatterns(int $since): array;

  /** @return array<int, array<string, mixed>> */
  public function findRecentBankChangeSignals(int $since): array;

}
