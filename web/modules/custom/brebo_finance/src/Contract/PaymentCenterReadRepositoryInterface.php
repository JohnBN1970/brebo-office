<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Read boundary for payment-center screens and preparation forms. */
interface PaymentCenterReadRepositoryInterface {

  /** @return list<array<string,mixed>> */
  public function paymentBatches(int $limit = 50): array;

  /** @return list<array<string,mixed>> */
  public function bankReconciliations(int $limit = 50): array;

  /** @return list<array<string,mixed>> */
  public function approvedPaymentReleases(int $limit = 100): array;

}
