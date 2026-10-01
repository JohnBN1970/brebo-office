<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/**
 * Resolves project identity for Finance without coupling domain services to Drupal entities.
 */
interface ProjectReferenceGatewayInterface {

  public function exists(int $projectId): bool;

}
