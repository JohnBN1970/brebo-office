<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/**
 * Mutates project lifecycle without exposing the canonical project store.
 */
interface ProjectLifecycleGatewayInterface {

  /**
   * @return array{before:string, changed:bool}
   */
  public function transition(int $projectId, string $targetStatus): array;

}
