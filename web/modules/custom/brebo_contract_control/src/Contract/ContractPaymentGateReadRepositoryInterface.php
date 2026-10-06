<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Contract;

interface ContractPaymentGateReadRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function award(int $awardId): ?array;

  /** @return array<string,mixed>|null */
  public function invoice(int $invoiceId): ?array;

}
