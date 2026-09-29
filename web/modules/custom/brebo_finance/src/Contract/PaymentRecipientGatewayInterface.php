<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface PaymentRecipientGatewayInterface {
  /** @return array<string,mixed> */
  public function snapshot(int $invoiceId,int $userId): array;
  public function unchanged(int $invoiceId,string $recipientHash,int $userId): bool;
}
