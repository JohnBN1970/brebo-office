<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Contract;

/** Read boundary for control-effectiveness source data. */
interface ControlEffectivenessReadRepositoryInterface {

  /** @return array<int, array<string, mixed>> */
  public function findManagementActions(): array;

}
