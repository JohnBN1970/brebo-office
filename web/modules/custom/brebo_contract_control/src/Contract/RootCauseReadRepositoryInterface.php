<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Contract;

/** Read boundary for root-cause source data. */
interface RootCauseReadRepositoryInterface {

  /** @return array<int, array<string, mixed>> */
  public function findManagementActions(): array;

}
